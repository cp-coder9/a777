<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Freelancer;

use Architex\Platform\Freelancer\JobTicketMarketplaceException;
use Architex\Platform\Freelancer\JobTicketMarketplacePolicy;
use PHPUnit\Framework\TestCase;

final class JobTicketMarketplacePolicyTest extends TestCase
{
    public function testRoleCapabilitiesFailClosed():void
    {
        $policy=new JobTicketMarketplacePolicy();

        self::assertSame(['post'=>true,'accept'=>false,'expand'=>false,'supervise'=>false],$policy->roleCapabilities('client'));
        self::assertSame(['post'=>true,'accept'=>true,'expand'=>true,'supervise'=>true],$policy->roleCapabilities('registered_bep'));
        self::assertSame(['post'=>false,'accept'=>true,'expand'=>false,'supervise'=>false],$policy->roleCapabilities('freelancer_candidate'));
        self::assertSame(['post'=>true,'accept'=>true,'expand'=>true,'supervise'=>false],$policy->roleCapabilities('contractor'));

        $this->expectException(JobTicketMarketplaceException::class);
        $this->expectExceptionMessage('job_ticket_role_invalid');
        $policy->roleCapabilities('independent_freelancer');
    }

    public function testNormalisesPublicAndDirectTicketsWithFeeDisclosure():void
    {
        $policy=new JobTicketMarketplacePolicy();
        $public=$policy->normaliseTicket([
            'posterType'=>'client','title'=>'House alterations - architect required',
            'description'=>'Prepare architectural working drawings for a residential alteration.',
            'deliverables'=>['Working drawings','Municipal submission pack'],'budgetMin'=>50000,'budgetMax'=>150000,
            'currency'=>'ZAR','deadline'=>'2026-12-15','location'=>'Sandton, Johannesburg','phase'=>'design',
            'requiredRegistrations'=>['SACAP'],'invitationType'=>'public','invitedUserIds'=>[],
            'platformFeePercent'=>5.0,'platformFeeDisclosure'=>'Architex platform fee: 5% of an accepted quotation; payment processing is not claimed.',
        ]);

        self::assertSame('public',$public['invitationType']);
        self::assertSame(['Municipal submission pack','Working drawings'],$public['deliverables']);
        self::assertSame(50000.0,$public['budget']['min']);
        self::assertSame(5.0,$public['platformFee']['percent']);

        $direct=$policy->normaliseTicket([...$public,
            'budgetMin'=>50000,'budgetMax'=>150000,'currency'=>'ZAR','invitationType'=>'direct',
            'invitedUserIds'=>['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            'platformFeePercent'=>5.0,'platformFeeDisclosure'=>$public['platformFee']['disclosure'],
        ]);
        self::assertSame(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],$direct['invitedUserIds']);

        foreach([
            [...$public,'budgetMin'=>200000,'budgetMax'=>100000,'platformFeePercent'=>5.0,'platformFeeDisclosure'=>$public['platformFee']['disclosure']],
            [...$public,'invitationType'=>'direct','invitedUserIds'=>[],'budgetMin'=>50000,'budgetMax'=>150000,'platformFeePercent'=>5.0,'platformFeeDisclosure'=>$public['platformFee']['disclosure']],
            [...$public,'posterType'=>'freelancer_candidate','budgetMin'=>50000,'budgetMax'=>150000,'platformFeePercent'=>5.0,'platformFeeDisclosure'=>$public['platformFee']['disclosure']],
        ]as$invalid){try{$policy->normaliseTicket($invalid);self::fail('Invalid ticket must fail closed.');}catch(JobTicketMarketplaceException$e){self::assertStringStartsWith('job_ticket_',$e->reason);}}
    }

    public function testClassificationIsDeterministicAdvisoryAndOverrideIsExplicit():void
    {
        $policy=new JobTicketMarketplacePolicy();
        $signals=['title'=>'Production drawings and BIM support','description'=>'Revit working drawing production','deliverables'=>['CAD drawings','Drawing register'],'requiredRegistrations'=>[]];
        $first=$policy->classify($signals);$second=$policy->classify(array_reverse($signals,true));

        self::assertSame('production_drawings',$first['primaryCategory']);
        self::assertSame('candidate',$first['requiredCompetence']);
        self::assertSame('registered_architect_or_engineer',$first['suggestedSupervisorDiscipline']);
        self::assertSame($first,$second);
        self::assertSame('deterministic_advisory',$first['classifiedBy']);
        self::assertFalse($first['professionalDecision']);

        $override=$policy->normaliseClassificationOverride($first,[
            'primaryCategory'=>'architectural_design','subCategory'=>'working_drawings',
            'requiredCompetence'=>'registered_professional','requiredRegistrations'=>['SACAP'],
            'suggestedSupervisorDiscipline'=>null,'reason'=>'Lead architect confirmed the appointment scope after human review.',
        ]);
        self::assertSame('human_override_after_advisory',$override['classifiedBy']);
        self::assertSame($first['sha256'],$override['replacesSha256']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$override['sha256']);
    }

    public function testDirectVisibilityAndMatchingUsePublicEvidenceOnly():void
    {
        $policy=new JobTicketMarketplacePolicy();
        self::assertTrue($policy->canViewTicket('public',[],null));
        self::assertTrue($policy->canViewTicket('direct',['a'], 'a'));
        self::assertFalse($policy->canViewTicket('direct',['a'], 'b'));
        self::assertFalse($policy->canViewTicket('direct',['a'], null));

        $classification=$policy->classify(['title'=>'Structural engineer','description'=>'Structural design','deliverables'=>['Foundation design'],'requiredRegistrations'=>['ECSA']]);
        $matches=$policy->matchRecipients($classification,'Johannesburg',[
            ['userId'=>'b','role'=>'registered_bep','disciplines'=>['structural_engineering'],'serviceAreas'=>['Johannesburg'],'verificationStatus'=>'verified','capacityStatus'=>'available'],
            ['userId'=>'a','role'=>'registered_bep','disciplines'=>['structural_engineering'],'serviceAreas'=>['Cape Town'],'verificationStatus'=>'unverified','capacityStatus'=>'limited'],
            ['userId'=>'private','role'=>'registered_bep','disciplines'=>['structural_engineering'],'serviceAreas'=>['Johannesburg'],'verificationStatus'=>'unverified','capacityStatus'=>'limited','privateRegistrationNumber'=>'SECRET'],
        ]);
        self::assertSame('b',$matches[0]['userId']);
        self::assertGreaterThan($matches[1]['score'],$matches[0]['score']);
        self::assertArrayNotHasKey('privateRegistrationNumber',$matches[0]);
        self::assertTrue($matches[0]['advisoryOnly']);
        self::assertFalse($matches[0]['automaticAward']);
    }

    public function testCompleteSupervisionMapAndCompatibilityAreFailClosed():void
    {
        $policy=new JobTicketMarketplacePolicy();
        self::assertSame('registered_architect',$policy->supervisionRule('candidate_architect')['supervisorDiscipline']);
        self::assertSame('registered_architect_or_engineer',$policy->supervisionRule('draughtsperson_bim_modeller')['supervisorDiscipline']);
        self::assertTrue($policy->supervisorCompatible('draughtsperson_bim_modeller','registered_architect'));
        self::assertTrue($policy->supervisorCompatible('draughtsperson_bim_modeller','registered_engineer'));
        self::assertFalse($policy->supervisorCompatible('candidate_architect','registered_engineer'));

        foreach(['independent_architect','candidate_magic']as$unknown){try{$policy->supervisionRule($unknown);self::fail('Unknown discipline must fail closed.');}catch(JobTicketMarketplaceException$e){self::assertSame('freelancer_discipline_invalid',$e->reason);}}
    }

    public function testCanonicalEvidenceAndLifecycleGatesRejectFalseClaims():void
    {
        $policy=new JobTicketMarketplacePolicy();
        $quote=$policy->canonicalEvidence('quotation',['amount'=>125000.0,'currency'=>'ZAR','scope'=>'Architectural service','timeline'=>'6 weeks']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$quote['sha256']);
        self::assertSame($quote,$policy->canonicalEvidence('quotation',['timeline'=>'6 weeks','scope'=>'Architectural service','currency'=>'ZAR','amount'=>125000.0]));

        $payment=$policy->normalisePaymentEvidence(['reference'=>'EFT-REF-001','paidAt'=>'2026-08-31','note'=>'Payment evidence recorded from the payer remittance.']);
        self::assertSame('recorded_evidence_not_bank_confirmation',$payment['verificationBoundary']);

        $gate=$policy->externalIssueGate(['routeLocked'=>true,'currentRevisionSignedOff'=>false,'engagementActive'=>true]);
        self::assertFalse($gate['eligible']);
        self::assertSame(['current_revision_supervisor_signoff_required'],$gate['failures']);

        foreach([
            fn()=>$policy->canonicalEvidence('bank_payment_confirmed',['reference'=>'x']),
            fn()=>$policy->normalisePaymentEvidence(['reference'=>'AI paid automatically','paidAt'=>'2026-08-31','note'=>'Bank confirmed and guaranteed.']),
            fn()=>$policy->completionGate(['deliverablesSignedOff'=>true,'claimsResolved'=>false,'humanAccepted'=>true]),
        ]as$operation){try{$operation();self::fail('Misleading or incomplete evidence must fail closed.');}catch(JobTicketMarketplaceException$e){self::assertNotSame('',$e->reason);}}
    }
}

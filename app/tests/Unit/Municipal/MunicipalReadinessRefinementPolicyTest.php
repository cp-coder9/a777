<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Municipal;

use Architex\Platform\Municipal\MunicipalReadinessException;
use Architex\Platform\Municipal\MunicipalReadinessRefinementPolicy;
use PHPUnit\Framework\TestCase;

final class MunicipalReadinessRefinementPolicyTest extends TestCase
{
    public function testNormalisesTheCompletePropertyAndApplicationProfile():void
    {
        $profile=(new MunicipalReadinessRefinementPolicy())->normaliseProfile([
            'applicationType'=>'departure_relaxation','erf'=>'1234','farm'=>'Farm Alpha','portion'=>'7',
            'sectionalTitle'=>'SS 88/2026','address'=>'12 Example Road, Sandton','municipality'=>'City of Johannesburg',
            'zoning'=>'Residential 1','landUseAssumptions'=>'Coverage relaxation may be required.',
            'overlays'=>['urban development zone','airport funnel'],'servitudes'=>['stormwater servitude'],
            'heritageFlag'=>true,'environmentalFlag'=>false,'ownershipConfirmed'=>true,'agentAuthorised'=>true,
            'publicParticipationRequired'=>true,'noticeRequired'=>true,'municipalFeeStatus'=>'placeholder',
        ]);

        self::assertSame('departure_relaxation',$profile['applicationType']);
        self::assertSame(['airport funnel','urban development zone'],$profile['overlays']);
        self::assertTrue($profile['heritageFlag']);
        self::assertSame('placeholder',$profile['municipalFeeStatus']);
    }

    public function testRejectsUnknownApplicationsAndIncompletePropertyIdentity():void
    {
        $policy=new MunicipalReadinessRefinementPolicy();
        foreach([
            ['applicationType'=>'council_magic','municipality'=>'Tshwane','address'=>'1 Main Road'],
            ['applicationType'=>'building_plan','municipality'=>'Tshwane','address'=>'1 Main Road'],
        ] as $profile){
            try{$policy->normaliseProfile($profile);self::fail('Invalid profile must fail closed.');}
            catch(MunicipalReadinessException $exception){self::assertStringStartsWith('invalid_municipal_profile',$exception->reason);}
        }
    }

    public function testWorkflowAndIssueGateCountOpenFindingsAndUnsignedRoutes():void
    {
        $policy=new MunicipalReadinessRefinementPolicy();
        $findings=[
            ['severity'=>'blocker','status'=>'open'],['severity'=>'warning','status'=>'open'],
            ['severity'=>'advisory','status'=>'resolved'],
        ];
        $routes=[
            ['requiredBeforeIssue'=>true,'status'=>'required','supervisingProfessionalRequired'=>false,'supervisorSignedOff'=>false],
            ['requiredBeforeIssue'=>true,'status'=>'signed_off','supervisingProfessionalRequired'=>true,'supervisorSignedOff'=>false],
        ];

        $state=$policy->workflow($findings,$routes,false,false);
        self::assertSame('blocked',$state['status']);
        self::assertSame(['blockers'=>1,'warnings'=>1,'advisories'=>0],$state['openFindings']);
        self::assertSame(2,$state['unsignedRequiredRoutes']);

        $gate=$policy->issueGate(['matrixVerified'=>true,'professionalReviewApproved'=>true,'authorityConfirmed'=>true],$findings,$routes);
        self::assertFalse($gate['eligible']);
        self::assertSame(['open_blocker_findings','unsigned_professional_routes','candidate_supervision_required'],$gate['failures']);
    }

    public function testBuildsCanonicalAdvisoryManifestWithoutProhibitedClaims():void
    {
        $policy=new MunicipalReadinessRefinementPolicy();
        $input=[
            'packId'=>'0123456789abcdef0123456789abcdef','packVersion'=>2,'issuedAt'=>'2026-08-30T12:00:00.000000Z',
            'principalProfessional'=>['name'=>'A. Architect','role'=>'architect','registration'=>'PrArch 12345'],
            'project'=>['id'=>'project','projectName'=>'Sandton Addition','municipality'=>'City of Johannesburg'],
            'profile'=>['applicationType'=>'building_plan','erf'=>'1234','address'=>'12 Example Road'],
            'readiness'=>['score'=>100,'status'=>'ready_for_signoff'],
            'documents'=>[['title'=>'Owner authority','revision'=>'1','sha256'=>str_repeat('a',64)]],
            'drawings'=>[['number'=>'A-001','revision'=>'2','discipline'=>'architectural','sha256'=>str_repeat('b',64)]],
            'checklist'=>[],'resolutionLog'=>[],'signoffRegister'=>[],
            'sources'=>[['ruleVersion'=>'practice-2026-1','sourceReference'=>'Official source reviewed by practice']],
            'assessmentSha256'=>str_repeat('c',64),'projectRecordId'=>'record',
        ];
        $first=$policy->buildManifest($input);$second=$policy->buildManifest(array_reverse($input,true));

        self::assertSame($first['sha256'],$second['sha256']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$first['sha256']);
        self::assertStringContainsString('does not constitute municipal approval',$first['manifest']['readinessStatement']);
        self::assertStringNotContainsString('AI-certified compliant',$first['canonicalJson']);
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Compliance;

use Architex\Platform\Compliance\ComplianceException;
use Architex\Platform\Compliance\ComplianceRuleRegistry;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class ComplianceRuleRegistryTest extends MariaDbTestCase
{
    public function testSeedsReviewRequiredRulesAndActivatesAnImmutableVerifiedVersion(): void
    {
        [$organisationId,$ownerId] = $this->seedTenant();
        $registry = new ComplianceRuleRegistry($this->pdo);

        $seed = $registry->ensureImplementationSeed($organisationId,$ownerId,'owner');
        self::assertSame('sans_10400_xa',$seed['ruleKey']);
        self::assertSame('implementation-seed-v0.1',$seed['version']);
        self::assertSame('review_required',$seed['status']);
        self::assertFalse($seed['issueEligible']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$seed['payloadSha256']);
        self::assertSame($seed['id'],$registry->ensureImplementationSeed($organisationId,$ownerId,'owner')['id']);

        $candidate = $registry->createVersion($organisationId,$ownerId,'owner',[
            'ruleKey'=>'sans_10400_xa',
            'version'=>'licensed-2021-ed2-practice-1',
            'sourceReference'=>'Licensed practice copy reviewed 2026-08-28',
            'effectiveDate'=>'2026-08-28',
            'rules'=>$seed['rules'],
        ]);
        self::assertSame('review_required',$candidate['status']);

        $verified = $registry->verify($organisationId,$ownerId,'owner',$candidate['id'],true,'Checked against the licensed source by the responsible professional.');
        self::assertSame('verified',$verified['status']);
        self::assertTrue($verified['issueEligible']);
        self::assertSame($candidate['payloadSha256'],$verified['payloadSha256']);
        self::assertSame($verified['id'],$registry->current($organisationId,'sans_10400_xa',true)['id']);
        self::assertSame('review_required',$registry->get($organisationId,$seed['id'])['status']);
        self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'compliance_rule.%'")->fetchColumn());
    }

    public function testRoleTenantDuplicateAndHumanVerificationRulesFailClosed(): void
    {
        [$organisationId,$ownerId,$memberId] = $this->seedTenant();
        [$otherOrganisationId,$otherOwnerId] = $this->seedTenant('other');
        $registry = new ComplianceRuleRegistry($this->pdo);
        $seed = $registry->ensureImplementationSeed($organisationId,$ownerId,'owner');

        foreach ([
            fn()=> $registry->createVersion($organisationId,$memberId,'member',['ruleKey'=>'sans_10400_xa','version'=>'v2','sourceReference'=>'Source','effectiveDate'=>'2026-08-28','rules'=>$seed['rules']]),
            fn()=> $registry->verify($organisationId,$ownerId,'owner',$seed['id'],false,'Reviewed'),
            fn()=> $registry->verify($organisationId,$ownerId,'owner',$seed['id'],true,''),
            fn()=> $registry->get($otherOrganisationId,$seed['id']),
            fn()=> $registry->createVersion($organisationId,$ownerId,'owner',['ruleKey'=>'sans_10400_xa','version'=>$seed['version'],'sourceReference'=>'Duplicate','effectiveDate'=>'2026-08-28','rules'=>$seed['rules']]),
        ] as $operation) {
            try { $operation(); self::fail('Compliance rule governance should fail closed.'); }
            catch (ComplianceException) { self::assertTrue(true); }
        }

        self::assertSame([], $registry->list($otherOrganisationId));
        self::assertNotSame($ownerId,$otherOwnerId);
    }

    public function testRefuseSeedIsDistinctValidatedAndNeverDirectlyVerifiable(): void
    {
        [$organisationId,$ownerId]=$this->seedTenant('refuse');$registry=new ComplianceRuleRegistry($this->pdo);
        $seed=$registry->ensureRefuseImplementationSeed($organisationId,$ownerId,'owner');
        self::assertSame('municipal_refuse',$seed['ruleKey']);
        self::assertSame('implementation-seed-v0.1',$seed['version']);
        self::assertSame('review_required',$seed['status']);
        self::assertFalse($seed['issueEligible']);
        self::assertArrayHasKey('residential_dwelling',$seed['rules']['generationRates']);
        self::assertArrayHasKey('standard_1100l',$seed['rules']['bins']);

        $candidate=$registry->createVersion($organisationId,$ownerId,'owner',[
            'ruleKey'=>'municipal_refuse','version'=>'municipal-reviewed-test-1',
            'sourceReference'=>'Disposable test transcription of an official municipal source',
            'effectiveDate'=>'2026-08-28','rules'=>$seed['rules'],
        ]);
        self::assertSame('verified',$registry->verify($organisationId,$ownerId,'owner',$candidate['id'],true,'Exact disposable source transcription reviewed.')['status']);
        try{$registry->verify($organisationId,$ownerId,'owner',$seed['id'],true,'Must remain blocked.');self::fail('A refuse implementation seed must never be verifiable.');}
        catch(ComplianceException$exception){self::assertSame('implementation_seed_not_verifiable',$exception->reason);}

        $invalid=$seed['rules'];unset($invalid['bins']);
        try{$registry->createVersion($organisationId,$ownerId,'owner',['ruleKey'=>'municipal_refuse','version'=>'invalid-1','sourceReference'=>'Invalid test','effectiveDate'=>'2026-08-28','rules'=>$invalid]);self::fail('Incomplete refuse rules must fail closed.');}
        catch(ComplianceException$exception){self::assertSame('invalid_rule_payload',$exception->reason);}
    }

    /** @return array{string,string,string} */
    private function seedTenant(string $suffix='primary'): array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$memberId=Identifier::generate();
        foreach ([[$ownerId,"compliance-owner-$suffix@example.test"],[$memberId,"compliance-member-$suffix@example.test"]] as [$id,$email]) {
            $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'email'=>$email]);
        }
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>"Compliance $suffix",'slug'=>"compliance-$suffix-".substr($organisationId,0,8)]);
        foreach ([[$ownerId,'owner'],[$memberId,'member']] as [$userId,$role]) {
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:role,'active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$userId,'role'=>$role]);
        }
        return [$organisationId,$ownerId,$memberId];
    }
}

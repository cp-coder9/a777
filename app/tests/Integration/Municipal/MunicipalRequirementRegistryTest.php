<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Municipal;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Municipal\MunicipalReadinessException;
use Architex\Platform\Municipal\MunicipalRequirementRegistry;
use Architex\Tests\Support\MariaDbTestCase;

final class MunicipalRequirementRegistryTest extends MariaDbTestCase
{
    public function testCreatesReviewSeedAndActivatesASeparateImmutableVerifiedMatrix(): void
    {
        [$organisationId,$ownerId] = $this->seedTenant();
        $registry = new MunicipalRequirementRegistry($this->pdo);

        $seed = $registry->ensureImplementationSeed($organisationId,$ownerId,'owner','City of Johannesburg');
        self::assertSame('city-of-johannesburg',$seed['municipalityKey']);
        self::assertSame('implementation-seed-v0.1',$seed['version']);
        self::assertSame('review_required',$seed['status']);
        self::assertFalse($seed['assessmentApprovalEligible']);
        self::assertCount(8,$seed['requirements']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$seed['payloadSha256']);
        self::assertSame($seed['id'],$registry->ensureImplementationSeed($organisationId,$ownerId,'owner','City of Johannesburg')['id']);

        $candidate = $registry->createVersion($organisationId,$ownerId,'owner',[
            'municipality'=>'City of Johannesburg',
            'version'=>'practice-matrix-2026-08-28',
            'sourceReference'=>'Municipal requirements reviewed from the current official submission guidance on 2026-08-28.',
            'effectiveDate'=>'2026-08-28',
            'requirements'=>$seed['requirements'],
        ]);
        self::assertSame('review_required',$candidate['status']);

        $verified = $registry->verify($organisationId,$ownerId,'owner',$candidate['id'],true,'Responsible professional checked the current source and matrix.');
        self::assertSame('verified',$verified['status']);
        self::assertTrue($verified['assessmentApprovalEligible']);
        self::assertSame($candidate['payloadSha256'],$verified['payloadSha256']);
        self::assertSame($verified['id'],$registry->current($organisationId,'City of Johannesburg',true)['id']);
        self::assertSame('review_required',$registry->get($organisationId,$seed['id'])['status']);
        self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'municipal_matrix.%'")->fetchColumn());
    }

    public function testTenantRoleDuplicateAndVerificationRulesFailClosed(): void
    {
        [$organisationId,$ownerId,$memberId] = $this->seedTenant();
        [$otherOrganisationId] = $this->seedTenant('other');
        $registry = new MunicipalRequirementRegistry($this->pdo);
        $seed = $registry->ensureImplementationSeed($organisationId,$ownerId,'owner','Tshwane');

        foreach ([
            fn()=> $registry->createVersion($organisationId,$memberId,'member',['municipality'=>'Tshwane','version'=>'v2','sourceReference'=>'Official source','effectiveDate'=>'2026-08-28','requirements'=>$seed['requirements']]),
            fn()=> $registry->verify($organisationId,$ownerId,'owner',$seed['id'],false,'Reviewed'),
            fn()=> $registry->verify($organisationId,$ownerId,'owner',$seed['id'],true,''),
            fn()=> $registry->get($otherOrganisationId,$seed['id']),
            fn()=> $registry->createVersion($organisationId,$ownerId,'owner',['municipality'=>'Tshwane','version'=>$seed['version'],'sourceReference'=>'Duplicate','effectiveDate'=>'2026-08-28','requirements'=>$seed['requirements']]),
        ] as $operation) {
            try { $operation(); self::fail('Municipal matrix governance should fail closed.'); }
            catch (MunicipalReadinessException) { self::assertTrue(true); }
        }

        self::assertSame([], $registry->list($otherOrganisationId));
    }

    /** @return array{string,string,string} */
    private function seedTenant(string $suffix='primary'): array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$memberId=Identifier::generate();
        foreach ([[$ownerId,"municipal-owner-$suffix@example.test"],[$memberId,"municipal-member-$suffix@example.test"]] as [$id,$email]) {
            $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'email'=>$email]);
        }
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>"Municipal $suffix",'slug'=>"municipal-$suffix-".substr($organisationId,0,8)]);
        foreach ([[$ownerId,'owner'],[$memberId,'member']] as [$userId,$role]) {
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:role,'active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$userId,'role'=>$role]);
        }
        return [$organisationId,$ownerId,$memberId];
    }
}

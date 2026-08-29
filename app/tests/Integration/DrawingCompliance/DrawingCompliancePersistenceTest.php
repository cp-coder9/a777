<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\DrawingCompliance;

use Architex\Platform\DrawingCompliance\DrawingComplianceException;
use Architex\Platform\DrawingCompliance\DrawingComplianceService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class DrawingCompliancePersistenceTest extends MariaDbTestCase
{
    public function testSourceVersionAndStandaloneSnapshotPersistImmutablyPerTenant(): void
    {
        [$organisation, $owner] = $this->seed('drawing-snapshot');
        [$otherOrganisation, $otherOwner] = $this->seed('drawing-other');
        $service = new DrawingComplianceService($this->pdo);
        $saved = $service->saveReview($organisation, $owner, [
            'mode'=>'standalone','title'=>'Boundary wall screen','drawingRevision'=>'P03',
            'boundaryWall'=>['unitType'=>'solid','thicknessMm'=>90,'heightM'=>1.1,'earthRetained'=>false],
            'buildingDescription'=>'Dwelling house boundary wall',
        ]);

        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $saved['snapshotSha256']);
        self::assertSame('illustrative_poc_until_verified', $saved['sourceStatus']);
        self::assertSame('fail', $saved['review']['boundaryWall']['status']);
        self::assertSame($saved, $service->review($organisation, $owner, $saved['id']));
        $this->expectException(DrawingComplianceException::class);
        $this->expectExceptionMessage('drawing_compliance_review_not_found');
        $service->review($otherOrganisation, $otherOwner, $saved['id']);
    }

    /** @return array{string,string} */
    private function seed(string $slug): array
    {
        $organisation=Identifier::generate();$owner=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$owner,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$slug,'slug'=>$slug.'-'.substr($organisation,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$owner]);
        return [$organisation,$owner];
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Trust;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Trust\TrustException;
use Architex\Platform\Trust\VerificationService;
use Architex\Tests\Support\MariaDbTestCase;

final class ProfessionalVerificationTest extends MariaDbTestCase
{
    public function testPublishesSafeDirectoryProfileAndPreservesVerificationGrades(): void
    {
        [$organisationId, $ownerId] = $this->seedTenant('trust-alpha');
        $reviewerId = $this->seedMember($organisationId, 'trust-alpha-reviewer', 'admin');
        $service = new VerificationService($this->pdo);
        $evidenceFileId = $this->seedEvidenceFile($organisationId, $ownerId);

        $profile = $service->saveProfile($organisationId, $ownerId, [
            'displayName' => 'Example Architects',
            'profession' => 'architect',
            'bio' => 'Public practice profile.',
            'serviceAreas' => ['Gauteng', 'Western Cape'],
            'directoryVisible' => true,
        ]);
        $verification = $service->submit($organisationId, $ownerId, [
            'kind' => 'professional_registration',
            'registrationBody' => 'SACAP',
            'registrationNumber' => 'PR-12345',
            'grade' => 'document_uploaded',
            'evidenceFileId' => $evidenceFileId,
            'consentGiven' => true,
            'purpose' => 'Professional directory verification',
            'retentionUntil' => '2031-08-27',
            'expiresOn' => '2030-12-31',
        ]);

        self::assertSame('document_uploaded', $verification['grade']);
        $reviewed = $service->review($organisationId, $reviewerId, $verification['id'], [
            'decision' => 'manually_reviewed',
            'note' => 'Registration document inspected.',
        ]);
        self::assertSame('manually_reviewed', $reviewed['grade']);

        $results = $service->searchDirectory('architect', 'Gauteng');
        self::assertCount(1, $results);
        self::assertSame($profile['id'], $results[0]['profileId']);
        self::assertSame('manually_reviewed', $results[0]['verificationGrade']);
        self::assertArrayNotHasKey('purpose', $results[0]);
        self::assertArrayNotHasKey('reviewNote', $results[0]);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }

    public function testRequiresPopiaMetadataAndNeverUpgradesExternalStatusWithoutProviderEvidence(): void
    {
        [$organisationId, $ownerId] = $this->seedTenant('trust-beta');
        $reviewerId = $this->seedMember($organisationId, 'trust-beta-reviewer', 'admin');
        $service = new VerificationService($this->pdo);

        try {
            $service->submit($organisationId, $ownerId, [
                'kind' => 'insurance_compliance',
                'grade' => 'document_uploaded',
                'consentGiven' => false,
                'purpose' => '',
                'retentionUntil' => '',
            ]);
            self::fail('Sensitive verification evidence must require POPIA metadata.');
        } catch (TrustException $exception) {
            self::assertSame('popia_metadata_required', $exception->reason);
        }

        $verification = $service->submit($organisationId, $ownerId, [
            'kind' => 'professional_registration',
            'grade' => 'self_declared',
            'registrationBody' => 'SACAP',
            'registrationNumber' => 'DECLARED-1',
            'consentGiven' => true,
            'purpose' => 'Credential verification',
            'retentionUntil' => '2031-08-27',
        ]);
        try {
            $service->review($organisationId, $ownerId, $verification['id'], ['decision' => 'manually_reviewed']);
            self::fail('A subject must not review their own verification.');
        } catch (TrustException $exception) {
            self::assertSame('self_review_forbidden', $exception->reason);
        }
        $this->expectException(TrustException::class);
        $this->expectExceptionMessage('external_provider_evidence_required');
        $service->review($organisationId, $reviewerId, $verification['id'], ['decision' => 'externally_verified']);
    }

    public function testReviewAndReadAreTenantScopedAndExpiredEvidenceIsLabelled(): void
    {
        [$organisationId, $ownerId] = $this->seedTenant('trust-gamma');
        [$otherOrganisationId, $otherOwnerId] = $this->seedTenant('trust-delta');
        $service = new VerificationService($this->pdo);
        $verification = $service->submit($organisationId, $ownerId, [
            'kind' => 'insurance_compliance',
            'grade' => 'self_declared',
            'consentGiven' => true,
            'purpose' => 'Project risk assessment',
            'retentionUntil' => '2031-08-27',
            'expiresOn' => '2020-01-01',
        ]);
        self::assertSame('expired', $service->listForUser($organisationId, $ownerId)[0]['effectiveGrade']);

        $this->expectException(TrustException::class);
        $this->expectExceptionMessage('verification_not_found');
        $service->review($otherOrganisationId, $otherOwnerId, $verification['id'], [
            'decision' => 'manually_reviewed',
            'note' => 'Must not cross tenant boundary.',
        ]);
    }

    /** @return array{string,string} */
    private function seedTenant(string $slug): array
    {
        $organisationId = Identifier::generate();
        $userId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")
            ->execute(['id' => $userId, 'email' => $slug . '@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")
            ->execute(['id' => $organisationId, 'name' => ucfirst($slug), 'slug' => $slug]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")
            ->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'user_id' => $userId]);
        return [$organisationId, $userId];
    }

    private function seedEvidenceFile(string $organisationId, string $userId): string
    {
        $projectId = Identifier::generate();
        $fileId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'VERIFY','Verification evidence','Internal','Internal','N/A','N/A','','architect',UNHEX(:user_id))")
            ->execute(['id' => $projectId, 'organisation_id' => $organisationId, 'user_id' => $userId]);
        $this->pdo->prepare("INSERT INTO stored_files(id,organisation_id,project_id,uploaded_by,original_name,storage_name,mime_type,byte_size,sha256) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:user_id),'registration.pdf',:storage_name,'application/pdf',4,:sha256)")
            ->execute(['id' => $fileId, 'organisation_id' => $organisationId, 'project_id' => $projectId, 'user_id' => $userId, 'storage_name' => str_repeat('a', 48), 'sha256' => hash('sha256', 'test')]);
        return $fileId;
    }

    private function seedMember(string $organisationId, string $slug, string $role): string
    {
        $userId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")
            ->execute(['id'=>$userId,'email'=>$slug . '@example.test']);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:role,'active')")
            ->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId,'role'=>$role]);
        return $userId;
    }
}

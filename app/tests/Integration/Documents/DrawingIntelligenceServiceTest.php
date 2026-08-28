<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Documents;

use Architex\Platform\Documents\DocumentException;
use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Documents\DrawingIntelligenceService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class DrawingIntelligenceServiceTest extends MariaDbTestCase
{
    public function testHumanCandidateRemainsProposedUntilExactHumanReview(): void
    {
        [$organisationId, $ownerId, $projectId] = $this->seed();
        $revision = (new DocumentService($this->pdo))->create($organisationId, $ownerId, 'owner', [
            'projectId' => $projectId,
            'title' => 'Ground floor plan',
            'documentType' => 'drawing',
            'discipline' => 'architectural',
            'phase' => 'design_development',
            'issuePurpose' => 'for_review',
            'revisionCode' => 'P01',
            'notes' => 'Controlled source revision.',
            'drawing' => ['drawingNumber' => 'A-100', 'scale' => '1:100', 'sheetType' => 'floor_plan'],
        ]);

        $candidatePayload = [
            'classification' => 'general_arrangement_plan',
            'detectedDiscipline' => 'architectural',
            'titleBlock' => [
                'drawingNumber' => 'A-100',
                'title' => 'Ground Floor Plan',
                'scale' => '1:100',
                'date' => '2026-08-28',
                'author' => 'Project architect',
            ],
            'revision' => 'P01',
            'issuePurpose' => 'for_review',
            'findings' => ['Human checked the visible title block.'],
            'suggestedNextAction' => 'Responsible professional to compare against the source drawing.',
        ];

        $service = new DrawingIntelligenceService($this->pdo);
        $candidate = $service->create($organisationId, $ownerId, 'owner', [
            'revisionId' => $revision['id'],
            'provenance' => 'human_entered',
            'providerName' => null,
            'providerReference' => null,
            'confidence' => null,
            'candidate' => $candidatePayload,
        ]);

        self::assertSame('pending_review', $candidate['status']);
        self::assertSame('human_entered', $candidate['provenance']);
        self::assertNull($candidate['confidence']);
        self::assertNull($candidate['providerName']);
        self::assertSame($candidatePayload, $candidate['candidate']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $candidate['candidateSha256']);
        self::assertFalse($candidate['metadataApplied']);

        $controlledBefore = (new DocumentService($this->pdo))->get($organisationId, $ownerId, $revision['id']);
        self::assertSame('Ground floor plan', $controlledBefore['title']);

        try {
            $service->review($organisationId, $ownerId, 'owner', $candidate['id'], 'accepted', false, 'Not confirmed.');
            self::fail('Review must require exact human confirmation.');
        } catch (DocumentException $exception) {
            self::assertSame('drawing_intelligence_review_confirmation_required', $exception->reason);
        }

        $accepted = $service->review(
            $organisationId,
            $ownerId,
            'owner',
            $candidate['id'],
            'accepted',
            true,
            'Compared every proposed field with the controlled source drawing.',
        );
        self::assertSame('accepted', $accepted['status']);
        self::assertSame($candidate['candidateSha256'], $accepted['candidateSha256']);
        self::assertFalse($accepted['metadataApplied']);
        self::assertNotNull($accepted['projectRecordId']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='drawing_intelligence_review' AND status='approved'")->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'drawing_intelligence.%'")->fetchColumn());

        $controlledAfter = (new DocumentService($this->pdo))->get($organisationId, $ownerId, $revision['id']);
        self::assertSame($controlledBefore['title'], $controlledAfter['title']);
        self::assertSame($controlledBefore['drawing'], $controlledAfter['drawing']);

        try {
            $service->review($organisationId, $ownerId, 'owner', $candidate['id'], 'rejected', true, 'Attempted second review.');
            self::fail('Reviewed candidate must be immutable.');
        } catch (DocumentException $exception) {
            self::assertSame('drawing_intelligence_not_reviewable', $exception->reason);
        }

        try {
            $service->create($organisationId, $ownerId, 'owner', [
                'revisionId' => $revision['id'],
                'provenance' => 'provider_result',
                'providerName' => null,
                'providerReference' => null,
                'confidence' => null,
                'candidate' => $candidatePayload,
            ]);
            self::fail('Provider provenance must have exact provider evidence.');
        } catch (DocumentException $exception) {
            self::assertSame('drawing_intelligence_provider_evidence_required', $exception->reason);
        }
    }

    /** @return array{string,string,string} */
    private function seed(): array
    {
        $organisationId = Identifier::generate();
        $ownerId = Identifier::generate();
        $projectId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'intelligence@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Intelligence Org',:slug,'active')")->execute(['id' => $organisationId, 'slug' => 'intelligence-' . substr($organisationId, 0, 8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:project_id),UNHEX(:organisation_id),'DI-TEST','Drawing Intelligence Project','Client','Tshwane','Erf 2','Commercial','','architect','design_development',UNHEX(:user_id))")->execute(['project_id' => $projectId, 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        return [$organisationId, $ownerId, $projectId];
    }
}

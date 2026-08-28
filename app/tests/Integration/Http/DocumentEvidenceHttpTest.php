<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class DocumentEvidenceHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedTransmittalAndHumanCandidateWorkflows(): void
    {
        [$organisationId, $ownerId, $projectId] = $this->seed();
        $documents = new DocumentService($this->pdo);
        $revision = $documents->create($organisationId, $ownerId, 'owner', [
            'projectId' => $projectId, 'title' => 'Construction plan', 'documentType' => 'drawing',
            'discipline' => 'architectural', 'phase' => 'construction_execution',
            'issuePurpose' => 'for_construction', 'revisionCode' => 'C01', 'notes' => 'Exact source.',
            'drawing' => ['drawingNumber' => 'A-201', 'sheetType' => 'floor_plan', 'scale' => '1:100'],
        ]);
        $documents->submitForReview($organisationId, $ownerId, 'owner', $revision['id'], 'Ready.');
        $documents->approve($organisationId, $ownerId, 'owner', $revision['id'], true, 'Reviewed.');
        $revision = $documents->issue($organisationId, $ownerId, 'owner', $revision['id'], true, 'Issued.');

        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void {}
        };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();
        $session = (new SessionService($this->pdo))->rotate($ownerId);
        $headers = ['Cookie' => 'architex_session=' . $session->token, 'X-Organisation-Id' => $organisationId, 'X-CSRF-Token' => $session->csrfToken];

        $draftResponse = $kernel->handle($this->json('POST', '/api/v1/document-transmittals', $headers, [
            'projectId' => $projectId, 'code' => 'TR-HTTP-01', 'title' => 'Construction issue',
            'purpose' => 'for_construction', 'recipientRoles' => ['contractor', 'architect'],
            'revisionIds' => [$revision['id']], 'notes' => 'Issue exact revision.',
        ]));
        self::assertSame(201, $draftResponse->status(), $draftResponse->body());
        $draft = $draftResponse->json()['data'];
        self::assertSame('draft', $draft['status']);
        self::assertCount(1, $kernel->handle(Request::json('GET', '/api/v1/document-transmittals?projectId=' . $projectId, $headers))->json()['data']);

        $unconfirmedIssue = $kernel->handle($this->json('POST', '/api/v1/document-transmittals/' . $draft['id'] . '/issue', $headers, ['humanConfirmed' => false, 'reason' => 'No confirmation.']));
        self::assertSame(422, $unconfirmedIssue->status(), $unconfirmedIssue->body());
        self::assertSame('human_confirmation_required', $unconfirmedIssue->json()['error']['code']);
        $issuedResponse = $kernel->handle($this->json('POST', '/api/v1/document-transmittals/' . $draft['id'] . '/issue', $headers, ['humanConfirmed' => true, 'reason' => 'Exact revisions and recipients checked.']));
        self::assertSame(200, $issuedResponse->status(), $issuedResponse->body());
        self::assertFalse($issuedResponse->json()['data']['externalDeliveryClaimed']);

        $candidateResponse = $kernel->handle($this->json('POST', '/api/v1/drawing-intelligence-candidates', $headers, [
            'revisionId' => $revision['id'], 'provenance' => 'human_entered',
            'providerName' => null, 'providerReference' => null, 'confidence' => null,
            'candidate' => [
                'classification' => 'general_arrangement_plan', 'detectedDiscipline' => 'architectural',
                'titleBlock' => ['drawingNumber' => 'A-201', 'title' => 'Construction Plan', 'scale' => '1:100', 'date' => '2026-08-28', 'author' => 'Project architect'],
                'revision' => 'C01', 'issuePurpose' => 'for_construction',
                'findings' => ['Human entered from the visible title block.'],
                'suggestedNextAction' => 'Responsible professional to compare every field.',
            ],
        ]));
        self::assertSame(201, $candidateResponse->status(), $candidateResponse->body());
        $candidate = $candidateResponse->json()['data'];
        self::assertSame('human_entered', $candidate['provenance']);
        self::assertNull($candidate['confidence']);
        self::assertFalse($candidate['metadataApplied']);
        self::assertCount(1, $kernel->handle(Request::json('GET', '/api/v1/drawing-intelligence-candidates?projectId=' . $projectId, $headers))->json()['data']);

        $unconfirmedReview = $kernel->handle($this->json('POST', '/api/v1/drawing-intelligence-candidates/' . $candidate['id'] . '/review', $headers, ['decision' => 'accepted', 'humanConfirmed' => false, 'reason' => 'No confirmation.']));
        self::assertSame(422, $unconfirmedReview->status(), $unconfirmedReview->body());
        self::assertSame('human_confirmation_required', $unconfirmedReview->json()['error']['code']);
        $reviewedResponse = $kernel->handle($this->json('POST', '/api/v1/drawing-intelligence-candidates/' . $candidate['id'] . '/review', $headers, ['decision' => 'accepted', 'humanConfirmed' => true, 'reason' => 'Compared every proposed field to the controlled drawing.']));
        self::assertSame(200, $reviewedResponse->status(), $reviewedResponse->body());
        self::assertSame('accepted', $reviewedResponse->json()['data']['status']);
        self::assertFalse($reviewedResponse->json()['data']['metadataApplied']);

        $missingCsrf = $headers;
        unset($missingCsrf['X-CSRF-Token']);
        self::assertSame(419, $kernel->handle($this->json('POST', '/api/v1/drawing-intelligence-candidates', $missingCsrf, []))->status());
        self::assertSame(404, $kernel->handle(Request::json('GET', '/api/v1/document-transmittals?projectId=' . $projectId, array_replace($headers, ['X-Organisation-Id' => Identifier::generate()])))->status());
    }

    /** @return array{string,string,string} */
    private function seed(): array
    {
        $organisationId = Identifier::generate(); $ownerId = Identifier::generate(); $projectId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'document-evidence-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Document Evidence HTTP',:slug,'active')")->execute(['id' => $organisationId, 'slug' => 'document-evidence-' . substr($organisationId, 0, 8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'DE-HTTP','Document Evidence Project','Client','Tshwane','Erf 3','Commercial','','architect','construction_execution',UNHEX(:user_id))")->execute(['id' => $projectId, 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        return [$organisationId, $ownerId, $projectId];
    }

    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function json(string $method, string $path, array $headers, array $body): Request
    {
        return Request::json($method, $path, $headers + ['Content-Type' => 'application/json'], json_encode($body === [] ? (object) [] : $body, JSON_THROW_ON_ERROR));
    }
}

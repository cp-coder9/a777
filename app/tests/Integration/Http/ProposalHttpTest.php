<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class ProposalHttpTest extends MariaDbTestCase
{
    public function testOwnerIssuesExactProposalAndRecipientAcceptsWithoutOrganisationSession(): void
    {
        [$organisationId, $ownerId, $projectId, $feeRunId] = $this->seedIssuedFeeRun();
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void {}
        };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();
        $session = (new SessionService($this->pdo))->rotate($ownerId);
        $headers = ['Cookie' => 'architex_session=' . $session->token, 'X-Organisation-Id' => $organisationId, 'X-CSRF-Token' => $session->csrfToken];

        $draft = $kernel->handle($this->jsonRequest('POST', '/api/v1/proposals', $headers, [
            'projectId' => $projectId,
            'feeRunId' => $feeRunId,
            'clientName' => 'HTTP Client',
            'clientEmail' => 'http-client@example.test',
            'validityDays' => 30,
            'assumptions' => ['Existing information is accurate.'],
            'exclusions' => ['Statutory fees.'],
            'notes' => [],
            'selectedTemplateKeys' => ['standard_sa_professional', 'architectural_services'],
            'customTerms' => [],
        ]));
        self::assertSame(201, $draft->status(), $draft->body());
        $proposalId = $draft->json()['data']['id'];

        $issued = $kernel->handle($this->jsonRequest('POST', '/api/v1/proposals/' . $proposalId . '/issue', $headers, [
            'professionalConfirmed' => true,
            'legalReviewConfirmed' => true,
            'issueReason' => 'Professional and legal review confirmed.',
        ]));
        self::assertSame(200, $issued->status(), $issued->body());
        $token = $issued->json()['data']['acceptanceToken'];

        $public = $kernel->handle(Request::json('GET', '/api/v1/proposal-acceptance/' . $token));
        self::assertSame(200, $public->status(), $public->body());
        self::assertSame('issued', $public->json()['data']['status']);
        self::assertArrayNotHasKey('clientEmail', $public->json()['data']);

        $accepted = $kernel->handle($this->jsonRequest('POST', '/api/v1/proposal-acceptance/' . $token, [], [
            'acceptedBy' => 'HTTP Client',
            'responsibilityConfirmed' => true,
        ])->withAttribute('remoteIp', '127.0.0.1')->withAttribute('userAgent', 'PHPUnit recipient'));
        self::assertSame(200, $accepted->status(), $accepted->body());
        self::assertSame('accepted', $accepted->json()['data']['status']);

        $revision = $kernel->handle($this->jsonRequest('POST', '/api/v1/proposals/' . $proposalId . '/revisions', $headers, [
            'feeRunId' => $feeRunId,
            'assumptions' => ['Updated information is accurate.'],
            'exclusions' => ['Updated statutory exclusions.'],
            'notes' => ['Recipient requested a revision.'],
            'selectedTemplateKeys' => ['standard_sa_professional', 'architectural_services'],
            'customTerms' => [],
        ]));
        self::assertSame(201, $revision->status(), $revision->body());
        self::assertSame(2, $revision->json()['data']['version']);
    }

    /** @return array{string,string,string,string} */
    private function seedIssuedFeeRun(): array
    {
        $organisationId = Identifier::generate();
        $ownerId = Identifier::generate();
        $projectId = Identifier::generate();
        $feeRunId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'proposal-http-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Proposal HTTP Practice','proposal-http-practice','active')")->execute(['id' => $organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'PROP-HTTP','HTTP Proposal Project','HTTP Client','Tshwane','Erf 14','Residential','','architect',UNHEX(:user_id))")->execute(['id' => $projectId, 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO tool_runs(id,organisation_id,user_id,tool_id,tool_version,executed_role,assignment_mode,project_id,status,input_payload,output_payload,audit_hash,audit_reason,issued_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'professional_fee_calculator','1.0.0','owner','internal_project',UNHEX(:project_id),'issued',:input,:output,:hash,'Reviewed fee',UTC_TIMESTAMP(6))")->execute([
            'id' => $feeRunId, 'organisation_id' => $organisationId, 'user_id' => $ownerId, 'project_id' => $projectId,
            'input' => json_encode(['definitionId' => Identifier::generate(), 'projectValue' => 1_000_000], JSON_THROW_ON_ERROR),
            'output' => json_encode(['totalInclVat' => 57_900], JSON_THROW_ON_ERROR),
            'hash' => hash('sha256', 'http-fee-run-snapshot'),
        ]);
        return [$organisationId, $ownerId, $projectId, $feeRunId];
    }

    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function jsonRequest(string $method, string $path, array $headers, array $body): Request
    {
        return Request::json($method, $path, $headers + ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }
}

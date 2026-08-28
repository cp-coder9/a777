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

final class SiteExecutionHttpTest extends MariaDbTestCase
{
    public function testSiteExecutionRoutesRequireAuthenticationTenantCsrfAndProfessionalAuthority(): void
    {
        [$organisationId, $ownerId, $projectId] = $this->tenant('Field HTTP', 'field-http');
        [, , $otherProjectId] = $this->tenant('Other Field HTTP', 'other-field-http');
        $memberId = $this->member($organisationId, 'field-http-member@example.test');
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void {}
        };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();
        $sessions = new SessionService($this->pdo);
        $ownerSession = $sessions->rotate($ownerId);
        $headers = ['Cookie' => 'architex_session='.$ownerSession->token, 'X-Organisation-Id' => $organisationId, 'X-CSRF-Token' => $ownerSession->csrfToken];

        $empty = $kernel->handle(Request::json('GET', '/api/v1/projects/'.$projectId.'/site-execution-workspace', $headers));
        self::assertSame(200, $empty->status(), $empty->body());
        self::assertSame([], $empty->json()['data']['dailyLogs']);
        self::assertFalse($empty->json()['data']['boundaries']['delayWarningsGrantEntitlement']);

        $log = $kernel->handle($this->json('POST', '/api/v1/projects/'.$projectId.'/site-diary', $headers, [
            'logDate' => '2026-08-29', 'weather' => 'Clear', 'labour' => ['contractor' => 8],
            'plant' => [], 'deliveries' => [], 'visitors' => [], 'delays' => [], 'safetyNotes' => [], 'humanConfirmed' => true,
        ]));
        self::assertSame(201, $log->status(), $log->body());
        self::assertSame('submitted', $log->json()['data']['status']);

        $draft = $kernel->handle($this->json('POST', '/api/v1/projects/'.$projectId.'/site-instructions', $headers, [
            'title' => 'HTTP instruction', 'instruction' => 'Proceed per reviewed detail.', 'costImpact' => 'none', 'timeImpact' => 'none',
        ]));
        self::assertSame(201, $draft->status(), $draft->body());
        $instructionId = $draft->json()['data']['id'];

        $memberSession = $sessions->rotate($memberId);
        $memberHeaders = ['Cookie' => 'architex_session='.$memberSession->token, 'X-Organisation-Id' => $organisationId, 'X-CSRF-Token' => $memberSession->csrfToken];
        $denied = $kernel->handle($this->json('POST', '/api/v1/site-instructions/'.$instructionId.'/issue', $memberHeaders, [
            'humanConfirmed' => true, 'reason' => 'General member attempts formal issue.',
        ]));
        self::assertSame(403, $denied->status(), $denied->body());
        self::assertSame('site_professional_authority_required', $denied->json()['error']['code']);

        $noCsrf = $kernel->handle($this->json('POST', '/api/v1/projects/'.$projectId.'/site-diary', array_diff_key($headers, ['X-CSRF-Token' => true]), [
            'logDate' => '2026-08-30', 'weather' => 'Clear', 'labour' => [], 'plant' => [], 'deliveries' => [],
            'visitors' => [], 'delays' => [], 'safetyNotes' => [], 'humanConfirmed' => true,
        ]));
        self::assertSame(419, $noCsrf->status(), $noCsrf->body());
        $crossTenant = $kernel->handle(Request::json('GET', '/api/v1/projects/'.$otherProjectId.'/site-execution-workspace', $headers));
        self::assertSame(404, $crossTenant->status(), $crossTenant->body());
        $anonymous = $kernel->handle(Request::json('GET', '/api/v1/projects/'.$projectId.'/site-execution-workspace'));
        self::assertSame(401, $anonymous->status(), $anonymous->body());
    }

    /** @return array{string,string,string} */
    private function tenant(string $name, string $prefix): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $prefix.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id' => $organisation, 'name' => $name, 'slug' => $prefix.'-'.substr($organisation, 0, 8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id' => Identifier::generate(), 'o' => $organisation, 'u' => $user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),:code,:name,'Client','Johannesburg','Erf 1','Residential','','architect','construction_execution',UNHEX(:u))")->execute(['p' => $project, 'o' => $organisation, 'u' => $user, 'code' => strtoupper(substr($prefix, 0, 8)), 'name' => $name.' Project']);
        return [$organisation, $user, $project];
    }

    private function member(string $organisationId, string $email): string
    {
        $user = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $email]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'member','active')")->execute(['id' => Identifier::generate(), 'o' => $organisationId, 'u' => $user]);
        return $user;
    }

    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function json(string $method, string $path, array $headers, array $body): Request
    {
        return Request::json($method, $path, $headers + ['Content-Type' => 'application/json'], json_encode((object)$body, JSON_THROW_ON_ERROR));
    }
}

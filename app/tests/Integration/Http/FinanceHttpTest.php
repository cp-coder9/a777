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

final class FinanceHttpTest extends MariaDbTestCase
{
    public function testFinanceWorkspaceAndProviderRoutesRequireAuthenticatedTenantAndCsrf(): void
    {
        [$organisationId, $ownerId, $projectId] = $this->tenant('Finance HTTP', 'finance-http');
        [$otherOrganisationId, $otherOwnerId, $otherProjectId] = $this->tenant('Other HTTP', 'other-http');
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void {}
        };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();
        $session = (new SessionService($this->pdo))->rotate($ownerId);
        $headers = ['Cookie' => 'architex_session='.$session->token, 'X-Organisation-Id' => $organisationId, 'X-CSRF-Token' => $session->csrfToken];

        $workspace = $kernel->handle(Request::json('GET', '/api/v1/projects/'.$projectId.'/finance-workspace', $headers));
        self::assertSame(200, $workspace->status(), $workspace->body());
        self::assertNull($workspace->json()['data']['baseline']);
        self::assertFalse($workspace->json()['data']['providerBoundary']['fundsHeldByArchitex']);

        $created = $kernel->handle($this->json('POST', '/api/v1/financial-providers', $headers, [
            'providerKey' => 'http-provider', 'name' => 'HTTP provider candidate', 'providerType' => 'payment_provider',
            'registered' => true, 'capabilities' => ['release', 'webhook_status'], 'configurationReference' => null,
        ]));
        self::assertSame(201, $created->status(), $created->body());
        self::assertFalse($created->json()['data']['liveConfigured']);
        self::assertArrayNotHasKey('secret', $created->json()['data']);
        $providerId = $created->json()['data']['id'];
        $webhook = $kernel->handle(Request::json('POST', '/api/v1/webhooks/finance/'.$providerId, [
            'Content-Type' => 'application/json', 'X-Architex-Provider-Timestamp' => (string)time(),
            'X-Architex-Provider-Signature' => str_repeat('a', 64),
        ], '{"eventId":"evt-http","eventType":"payment_paid","providerReference":"none","amountCents":1,"currency":"ZAR"}'));
        self::assertSame(503, $webhook->status(), $webhook->body());
        self::assertSame('financial_provider_webhook_unavailable', $webhook->json()['error']['code']);

        $noCsrf = $kernel->handle($this->json('POST', '/api/v1/financial-providers', array_diff_key($headers, ['X-CSRF-Token' => true]), [
            'providerKey' => 'forbidden-provider', 'name' => 'Forbidden', 'providerType' => 'payment_provider', 'registered' => true, 'capabilities' => ['release'], 'configurationReference' => null,
        ]));
        self::assertSame(419, $noCsrf->status(), $noCsrf->body());
        $crossTenant = $kernel->handle(Request::json('GET', '/api/v1/projects/'.$otherProjectId.'/finance-workspace', $headers));
        self::assertSame(404, $crossTenant->status(), $crossTenant->body());
        $anonymous = $kernel->handle(Request::json('GET', '/api/v1/projects/'.$projectId.'/finance-workspace'));
        self::assertSame(401, $anonymous->status(), $anonymous->body());
        self::assertNotSame($organisationId, $otherOrganisationId);
        self::assertNotSame($ownerId, $otherOwnerId);
    }

    /** @return array{string,string,string} */
    private function tenant(string $name, string $prefix): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $prefix.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id' => $organisation, 'name' => $name, 'slug' => $prefix.'-'.substr($organisation, 0, 8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id' => Identifier::generate(), 'o' => $organisation, 'u' => $user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),:code,:name,'Client','Johannesburg','Erf 1','Residential','','architect','appointment',UNHEX(:u))")->execute(['p' => $project, 'o' => $organisation, 'u' => $user, 'code' => strtoupper(substr($prefix, 0, 8)), 'name' => $name.' Project']);
        return [$organisation, $user, $project];
    }

    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function json(string $method, string $path, array $headers, array $body): Request { return Request::json($method, $path, $headers + ['Content-Type' => 'application/json'], json_encode((object)$body, JSON_THROW_ON_ERROR)); }
}

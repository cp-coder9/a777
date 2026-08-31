<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Infrastructure\AI\NvidiaNemotronException;
use Architex\Infrastructure\AI\WingmanModel;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class WingmanHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedApiPersistsConversationSuggestionAndFeedback(): void
    {
        [$organisation, $owner, $project] = $this->tenant('wingman-http');
        [$otherOrganisation, $other] = $this->tenant('wingman-http-other');
        $model = new HttpQueueWingmanModel([
            $this->modelResult('', [['id' => 'call-nav', 'name' => 'propose_navigation', 'arguments' => ['route' => 'municipal']]]),
            $this->modelResult('Open Municipal Approval Readiness when you are ready.'),
        ]);
        $kernel = $this->kernel($model);
        $sessions = new SessionService($this->pdo);
        $headers = $this->headers($organisation, $sessions->rotate($owner));
        $otherHeaders = $this->headers($otherOrganisation, $sessions->rotate($other));

        $bootstrap = $kernel->handle(Request::json('GET', '/api/v1/wingman/bootstrap?route=projects&projectId=' . $project, $headers));
        self::assertSame(200, $bootstrap->status(), $bootstrap->body());
        self::assertTrue($bootstrap->json()['data']['boundaries']['navigationRequiresClick']);

        $withoutCsrf = $headers; unset($withoutCsrf['X-CSRF-Token']);
        self::assertSame(419, $kernel->handle($this->json('POST', '/api/v1/wingman/messages', $withoutCsrf, ['prompt' => 'Guide me', 'route' => 'projects', 'projectId' => $project]))->status());

        $message = $kernel->handle($this->json('POST', '/api/v1/wingman/messages', $headers, ['prompt' => 'Guide me to submission readiness.', 'route' => 'projects', 'projectId' => $project]));
        self::assertSame(200, $message->status(), $message->body());
        $data = $message->json()['data'];
        self::assertSame('municipal', $data['suggestions'][0]['routeTarget']);

        $conversation = $kernel->handle(Request::json('GET', '/api/v1/wingman/conversations/' . $data['conversationId'], $headers));
        self::assertSame(200, $conversation->status(), $conversation->body());
        self::assertCount(2, $conversation->json()['data']['messages']);
        self::assertSame(404, $kernel->handle(Request::json('GET', '/api/v1/wingman/conversations/' . $data['conversationId'], $otherHeaders))->status());

        $feedback = $kernel->handle($this->json('POST', '/api/v1/wingman/suggestions/' . $data['suggestions'][0]['id'] . '/feedback', $headers, ['rating' => 'helpful', 'note' => 'Useful route.']));
        self::assertSame(200, $feedback->status(), $feedback->body());
        self::assertSame('helpful', $feedback->json()['data']['rating']);
    }

    public function testApiRejectsBadContextOversizedPromptAndProviderFailureWithoutLeaks(): void
    {
        [$organisation, $owner, $project] = $this->tenant('wingman-http-errors');
        $model = new HttpQueueWingmanModel([new NvidiaNemotronException('provider_unavailable')]);
        $kernel = $this->kernel($model);
        $headers = $this->headers($organisation, (new SessionService($this->pdo))->rotate($owner));

        $unauthenticated = $kernel->handle(Request::json('GET', '/api/v1/wingman/bootstrap?route=projects&projectId=' . $project, ['X-Organisation-Id' => $organisation]));
        self::assertSame(401, $unauthenticated->status());
        $denied = $kernel->handle(Request::json('GET', '/api/v1/wingman/bootstrap?route=municipal', $headers));
        self::assertSame(403, $denied->status(), $denied->body());
        $oversized = $kernel->handle($this->json('POST', '/api/v1/wingman/messages', $headers, ['prompt' => str_repeat('x', 4_001), 'route' => 'projects', 'projectId' => $project]));
        self::assertSame(400, $oversized->status(), $oversized->body());
        $unavailable = $kernel->handle($this->json('POST', '/api/v1/wingman/messages', $headers, ['prompt' => 'Help with this project.', 'route' => 'projects', 'projectId' => $project]));
        self::assertSame(503, $unavailable->status(), $unavailable->body());
        self::assertSame('provider_unavailable', $unavailable->json()['error']['code']);
        self::assertStringNotContainsString('provider body', $unavailable->body());
    }

    private function kernel(WingmanModel $model): \Architex\Http\Kernel
    {
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void {}
        };
        return (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier, wingmanModel: $model))->create();
    }

    /** @return array{string,string,string} */
    private function tenant(string $slug): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $slug . '@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id' => $organisation, 'name' => $slug, 'slug' => $slug . '-' . substr($organisation, 0, 8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation' => $organisation, 'user' => $user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:project),UNHEX(:organisation),'WHTTP','Wingman HTTP Project','Client','City','Erf 1','Office','','architect','municipal_submission',UNHEX(:user))")->execute(['project' => $project, 'organisation' => $organisation, 'user' => $user]);
        return [$organisation, $user, $project];
    }

    /** @return array<string,string> */
    private function headers(string $organisation, object $session): array
    {
        return ['Cookie' => 'architex_session=' . $session->token, 'X-Organisation-Id' => $organisation, 'X-CSRF-Token' => $session->csrfToken];
    }

    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function json(string $method, string $path, array $headers, array $body): Request
    {
        return Request::json($method, $path, $headers + ['Content-Type' => 'application/json'], json_encode((object) $body, JSON_THROW_ON_ERROR));
    }

    /** @param list<array{id:string,name:string,arguments:array<string,mixed>}> $toolCalls @return array<string,mixed> */
    private function modelResult(string $content, array $toolCalls = []): array
    {
        return ['content' => $content, 'toolCalls' => $toolCalls, 'requestId' => 'http-test', 'model' => 'nvidia/nemotron-3.5-lightning-30b-a3b', 'finishReason' => $toolCalls === [] ? 'stop' : 'tool_calls', 'usage' => ['input' => 8, 'output' => 4]];
    }
}

final class HttpQueueWingmanModel implements WingmanModel
{
    /** @param list<array<string,mixed>|\Throwable> $responses */
    public function __construct(private array $responses) {}
    public function complete(array $messages, array $tools = []): array
    {
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) throw $response;
        if (!is_array($response)) throw new \RuntimeException('Unexpected model call.');
        return $response;
    }
}

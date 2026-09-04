<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Legal\LegalAcceptanceService;
use Architex\Tests\Support\MariaDbTestCase;

final class AuthenticationHttpTest extends MariaDbTestCase
{
    public function testCompleteAuthenticationLifecyclePersistsAcrossRequestsAndEnforcesCsrf(): void
    {
        $this->publishSignupNotices();
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public ?string $token = null;
            public function isAvailable(): bool { return true; }
            public function send(string $email, string $token, string $expiresAt): void { $this->token = $token; }
        };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();
        $registrationBody = json_encode([
            'email'=>'owner@example.com','password'=>'correct horse battery staple','organisationName'=>'Practice','organisationSlug'=>'practice',
            'acceptedNoticeVersions'=>array_fill_keys(LegalAcceptanceService::SIGNUP_NOTICE_TYPES, '1.0.0'),
        ], JSON_THROW_ON_ERROR);
        $registered = $kernel->handle($this->jsonRequest('POST', '/api/v1/registrations', $registrationBody));
        self::assertSame(201, $registered->status());
        self::assertNotNull($notifier->token);
        self::assertSame('nosniff', $registered->header('X-Content-Type-Options'));

        $verified = $kernel->handle($this->jsonRequest('POST', '/api/v1/registrations/verify-email', json_encode(['token'=>$notifier->token], JSON_THROW_ON_ERROR)));
        self::assertSame(200, $verified->status());
        $loggedIn = $kernel->handle($this->jsonRequest('POST', '/api/v1/sessions', '{"email":"owner@example.com","password":"correct horse battery staple"}'));
        self::assertSame(200, $loggedIn->status());
        $cookie = explode(';', (string) $loggedIn->header('Set-Cookie'), 2)[0];
        $csrf = $loggedIn->json()['data']['csrfToken'];
        $organisationId = $loggedIn->json()['data']['identity']['memberships'][0]['organisationId'];

        $current = $kernel->handle(Request::json('GET', '/api/v1/session', ['Cookie'=>$cookie])->withAttribute('remoteIp', '127.0.0.1'));
        self::assertSame(200, $current->status());
        self::assertSame('owner@example.com', $current->json()['data']['email']);
        self::assertIsString($current->json()['data']['csrfToken']);
        $csrf = $current->json()['data']['csrfToken'];
        $projectBody = json_encode(['projectCode'=>'LIVE-001','projectName'=>'Live Project','clientName'=>'Client','municipality'=>'Tshwane','propertyReference'=>'Erf 1','propertyUse'=>'Residential','landUseNotes'=>'','leadProfessionalRole'=>'architect'], JSON_THROW_ON_ERROR);
        $projectHeaders = ['Cookie'=>$cookie,'X-CSRF-Token'=>$csrf,'X-Organisation-Id'=>$organisationId,'Content-Type'=>'application/json'];
        $createdProject = $kernel->handle(Request::json('POST', '/api/v1/projects', $projectHeaders, $projectBody)->withAttribute('remoteIp', '127.0.0.1'));
        self::assertSame(201, $createdProject->status(), json_encode($createdProject->json(), JSON_THROW_ON_ERROR));
        $projectId = $createdProject->json()['data']['projectId'];
        $readHeaders = ['Cookie'=>$cookie,'X-Organisation-Id'=>$organisationId];
        self::assertCount(1, $kernel->handle(Request::json('GET', '/api/v1/projects', $readHeaders))->json()['data']);
        self::assertSame('Live Project', $kernel->handle(Request::json('GET', '/api/v1/projects/' . $projectId, $readHeaders))->json()['data']['name']);
        $tools = $kernel->handle(Request::json('GET', '/api/v1/tools', $readHeaders))->json()['data'];
        self::assertSame(49, count($tools));
        self::assertSame(count($tools), count(array_unique(array_column($tools, 'id'))));
        self::assertSame([], $kernel->handle(Request::json('GET', '/api/v1/inbox', $readHeaders))->json()['data']);
        $missingCsrf = $kernel->handle(Request::json('DELETE', '/api/v1/session', ['Cookie'=>$cookie])->withAttribute('remoteIp', '127.0.0.1'));
        self::assertSame(419, $missingCsrf->status());
        $logout = $kernel->handle(Request::json('DELETE', '/api/v1/session', ['Cookie'=>$cookie,'X-CSRF-Token'=>$csrf])->withAttribute('remoteIp', '127.0.0.1'));
        self::assertSame(200, $logout->status());
        self::assertSame(401, $kernel->handle(Request::json('GET', '/api/v1/session', ['Cookie'=>$cookie])->withAttribute('remoteIp', '127.0.0.1'))->status());
    }

    private function jsonRequest(string $method, string $path, string $body): Request
    {
        return Request::json($method, $path, ['Content-Type'=>'application/json','User-Agent'=>'integration-test'], $body)->withAttribute('remoteIp', '127.0.0.1');
    }

    private function publishSignupNotices(): void
    {
        $insert = $this->pdo->prepare("INSERT INTO legal_notices (id,notice_type,version,title,content_sha256,published_at,effective_at) VALUES (UNHEX(:id),:type,'1.0.0',:title,:hash,UTC_TIMESTAMP(6)-INTERVAL 1 DAY,UTC_TIMESTAMP(6)-INTERVAL 1 DAY)");
        foreach (LegalAcceptanceService::SIGNUP_NOTICE_TYPES as $type) {
            $insert->execute(['id'=>Identifier::generate(),'type'=>$type,'title'=>$type,'hash'=>hash('sha256',$type)]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Legal;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Legal\LegalAcceptanceService;
use Architex\Tests\Support\MariaDbTestCase;

final class SignupNoticeCatalogueHttpTest extends MariaDbTestCase
{
    public function testAnonymousRegistrationCanReadOnlyTheCurrentSignupNoticeVersions(): void
    {
        $insert = $this->pdo->prepare("INSERT INTO legal_notices (id,notice_type,version,title,content_sha256,published_at,effective_at,retired_at) VALUES (UNHEX(:id),:type,:version,:title,:hash,UTC_TIMESTAMP(6)-INTERVAL 1 DAY,UTC_TIMESTAMP(6)-INTERVAL 1 DAY,:retired)");
        foreach (LegalAcceptanceService::SIGNUP_NOTICE_TYPES as $type) {
            $insert->execute(['id'=>Identifier::generate(),'type'=>$type,'version'=>'1.0.0','title'=>str_replace('_',' ',$type),'hash'=>hash('sha256',$type),'retired'=>null]);
        }
        $insert->execute(['id'=>Identifier::generate(),'type'=>'privacy_policy','version'=>'0.9.0','title'=>'old privacy','hash'=>hash('sha256','old'),'retired'=>'2026-01-01 00:00:00']);
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public function isAvailable(): bool { return true; }
            public function send(string $email, string $token, string $expiresAt): void {}
        };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();

        $response = $kernel->handle(Request::json('GET', '/api/v1/legal-notices/signup'));

        self::assertSame(200, $response->status());
        self::assertCount(4, $response->json()['data']);
        self::assertSame(LegalAcceptanceService::SIGNUP_NOTICE_TYPES, array_column($response->json()['data'], 'noticeType'));
        self::assertSame(['1.0.0'], array_values(array_unique(array_column($response->json()['data'], 'version'))));
    }
}

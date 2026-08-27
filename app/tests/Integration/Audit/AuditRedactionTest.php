<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Audit;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class AuditRedactionTest extends MariaDbTestCase
{
    public function testRecursivelyRedactsSecretsBeforePersistence(): void
    {
        $audit = new AuditService($this->pdo);
        $audit->append(null, null, 'security.test', 'test', Identifier::generate(), [
            'email' => 'allowed@example.test',
            'password' => 'never-store-me',
            'nested' => ['authorization' => 'Bearer secret', 'safe' => true],
        ]);
        $json = (string) $this->pdo->query('SELECT metadata FROM audit_events')->fetchColumn();
        self::assertStringNotContainsString('never-store-me', $json);
        self::assertStringNotContainsString('Bearer secret', $json);
        self::assertStringContainsString('[REDACTED]', $json);
        self::assertStringContainsString('allowed@example.test', $json);
    }
}

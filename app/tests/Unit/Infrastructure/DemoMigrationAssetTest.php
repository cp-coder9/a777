<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

final class DemoMigrationAssetTest extends TestCase
{
    public function testDemoSandboxMigrationDeclaresTheFailClosedTenantContract(): void
    {
        $path = dirname(__DIR__, 3) . '/database/migrations/0044_demo_sandbox.sql';
        self::assertFileExists($path);
        $sql = strtolower((string) file_get_contents($path));

        foreach (['demo_sandboxes', 'demo_personas', 'demo_project_role_assignments', 'demo_seed_items', 'demo_sandbox_events'] as $table) {
            self::assertStringContainsString('create table if not exists ' . $table, $sql);
        }
        foreach (['active_owner_key', "enum('active','superseded','failed')", 'canonical_payload', 'payload_sha256'] as $contract) {
            self::assertStringContainsString($contract, $sql);
        }
    }
}

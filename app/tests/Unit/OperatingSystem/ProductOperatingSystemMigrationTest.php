<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\OperatingSystem;

use PHPUnit\Framework\TestCase;

final class ProductOperatingSystemMigrationTest extends TestCase
{
    public function testReleaseGateEvidenceIsTenantScopedAndHumanReviewed(): void
    {
        $sql=(string)file_get_contents(dirname(__DIR__,3).'/database/migrations/0048_product_operating_system.sql');
        foreach(['deployment_release_gates','repo_commit','deployed_commit','build_timestamp','api_mode','route_smoke','checks','human_confirmed','organisation_id','created_by'] as $token) self::assertStringContainsString($token,$sql);
        self::assertStringContainsString('FOREIGN KEY (organisation_id) REFERENCES organisations(id)',$sql);
        self::assertStringContainsString('FOREIGN KEY (created_by) REFERENCES users(id)',$sql);
    }
}

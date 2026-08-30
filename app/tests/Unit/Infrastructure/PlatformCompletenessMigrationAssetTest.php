<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

final class PlatformCompletenessMigrationAssetTest extends TestCase
{
    public function testMigrationCreatesGovernedTenantTables(): void
    {
        $sql=file_get_contents(dirname(__DIR__,3).'/database/migrations/0051_platform_completeness.sql');
        self::assertIsString($sql);
        foreach(['platform_completeness_workstreams','platform_completeness_sections','platform_completeness_items','platform_completeness_evidence','platform_completeness_qc_reviews','platform_completeness_uploads','platform_completeness_role_assignments','platform_completeness_signoffs','platform_completeness_workstream_signoffs'] as$table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
        self::assertStringContainsString('expected_version',$sql);
        self::assertStringContainsString('stored_files',$sql);
        self::assertStringNotContainsString('DROP TABLE',$sql);
    }
}

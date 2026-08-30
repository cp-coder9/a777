<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Municipal;

use PHPUnit\Framework\TestCase;

final class MunicipalReadinessRefinementMigrationTest extends TestCase
{
    public function testMigrationOwnsTheRefinedReadinessAndImmutablePackLifecycle():void
    {
        $sql=file_get_contents(dirname(__DIR__,3).'/database/migrations/0054_municipal_readiness_refinement.sql');
        self::assertIsString($sql);
        foreach(['municipal_project_profiles','municipal_readiness_findings','municipal_professional_routes','municipal_submission_packs']as$table)self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        foreach(['application_type','profile_snapshot','profile_sha256','source_reference','human_approval_required','resolution_reason','required_before_issue','supervising_professional_required','supervisor_signed_off_at','manifest_snapshot','manifest_sha256','principal_professional','superseded_at']as$column)self::assertStringContainsString($column,$sql);
        foreach(['uq_municipal_profile_project','uq_municipal_finding_key','uq_municipal_route_key','uq_municipal_pack_version','uq_municipal_pack_assessment']as$key)self::assertStringContainsString($key,$sql);
        self::assertStringContainsString("status ENUM('issued','superseded')",$sql);
        self::assertStringNotContainsString("status ENUM('draft','issued'",$sql);
    }
}

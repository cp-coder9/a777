<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

final class NuancedQcMigrationAssetTest extends TestCase
{
    public function testMigrationCreatesGovernedTenantScopedQcTables(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/database/migrations/0053_nuanced_qc_control.sql');

        self::assertIsString($sql);
        foreach ([
            'qc_control_definitions',
            'qc_run_records',
            'qc_run_scope_items',
            'qc_category_results',
            'qc_gap_findings',
            'qc_evidence_links',
            'qc_retest_tasks',
            'qc_risk_entries',
            'qc_signoffs',
            'qc_role_assignments',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        foreach ([
            'organisation_id',
            'project_id',
            'idempotency_key',
            'expected_version',
            'snapshot_sha256',
            'human_confirmed',
            'risk_accepted_by',
            'stored_file_id',
            "'not_applicable'",
            "'external_system_handled'",
            "'accepted_risk'",
        ] as $guard) {
            self::assertStringContainsString($guard, $sql);
        }
        self::assertStringContainsString('UNIQUE KEY uq_qc_category_revision (run_id,category_key,revision)', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_qc_run_idempotency (organisation_id,idempotency_key)', $sql);
        self::assertStringNotContainsString('DROP TABLE', $sql);
    }
}

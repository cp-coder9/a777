<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\CPD;

use PHPUnit\Framework\TestCase;

final class CPDMigrationContractTest extends TestCase
{
    public function testMigrationOwnsTheCompleteGovernedCpdLifecycle(): void
    {
        $base = file_get_contents(dirname(__DIR__, 3) . '/database/migrations/0045_cpd_assessment_platform.sql');
        $commerce = file_get_contents(dirname(__DIR__, 3) . '/database/migrations/0047_cpd_commercial_ledger.sql');
        self::assertIsString($base);
        self::assertIsString($commerce);
        $sql=$base.$commerce;
        foreach ([
            'cpd_professional_body_rules', 'cpd_professional_profiles', 'cpd_content_items',
            'cpd_assessments', 'cpd_questions', 'cpd_accreditation_applications', 'cpd_courses',
            'cpd_commercial_settings', 'cpd_entitlements', 'cpd_payment_events', 'cpd_attempts',
            'cpd_attempt_answers', 'cpd_certificates', 'cpd_professional_records',
            'cpd_referral_events', 'cpd_purchase_ledger', 'cpd_payouts', 'cpd_audit_events',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql, $table);
        }
        foreach (['SACAP', 'ECSA', 'SACQSP', 'SACPCMP', 'SACPLAN', 'SACLAP', 'SAGC', 'SACPVP'] as $body) {
            self::assertStringContainsString("'{$body}'", $sql, $body);
        }
        self::assertStringContainsString('-- architex:migration-mode=ddl', $sql);
        self::assertStringContainsString('provider_event_id', $sql);
        self::assertStringContainsString('verification_code', $sql);
        self::assertStringContainsString('rule_snapshot', $sql);
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Freelancer;

use PHPUnit\Framework\TestCase;

final class JobTicketMarketplaceMigrationTest extends TestCase
{
    public function testMigrationOwnsTheCompleteTicketSupervisionAndExecutionLifecycle():void
    {
        $sql=file_get_contents(dirname(__DIR__,3).'/database/migrations/0055_freelancer_job_ticket_marketplace.sql');
        self::assertIsString($sql);
        foreach([
            'job_tickets','job_ticket_direct_invites','job_ticket_classification_revisions','job_ticket_clarifications',
            'job_ticket_quotations','job_ticket_expansions','freelancer_profiles','freelancer_supervision_routes',
            'freelancer_engagements','freelancer_timesheets','freelancer_timesheet_entries','freelancer_claims',
            'freelancer_claim_timesheets','freelancer_deliverables','freelancer_deliverable_revisions',
            'freelancer_deliverable_signoffs','freelancer_performance_reviews',
        ]as$table)self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        foreach([
            'classification_sha256','platform_fee_disclosure','automatic_award','invitation_type','recipient_user_id',
            'snapshot_sha256','equal_information','bidder_organisation_id','parent_ticket_id','supervisor_required',
            'independent_appointment_permitted','locked_at','payment_execution_claimed','payment_evidence_sha256',
            'file_manifest_sha256','external_issue_ready','signed_revision_sha256','human_accepted',
        ]as$guard)self::assertStringContainsString($guard,$sql);
        foreach([
            'idx_job_ticket_public_discovery','idx_job_ticket_direct_recipient','uq_job_ticket_quote_bidder',
            'uq_job_ticket_expansion_child','uq_freelancer_profile_user','uq_supervision_ticket_freelancer',
            'uq_freelancer_claim_timesheet','uq_freelancer_deliverable_revision','uq_freelancer_review',
        ]as$key)self::assertStringContainsString($key,$sql);
        self::assertStringContainsString("status ENUM('draft','published','suspended','withdrawn')",$sql);
        self::assertStringContainsString("status ENUM('submitted','approved','rejected','recorded_paid')",$sql);
        self::assertStringNotContainsString('DROP TABLE',$sql);
    }
}

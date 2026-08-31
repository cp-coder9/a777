<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Freelancer;

use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class JobTicketMarketplaceSchemaTest extends MariaDbTestCase
{
    public function testMariaDbEnforcesTheMarketplaceSchemaContract():void
    {
        $tables=['job_tickets','job_ticket_direct_invites','job_ticket_classification_revisions','job_ticket_clarifications','job_ticket_quotations','job_ticket_expansions','freelancer_profiles','freelancer_supervision_routes','freelancer_engagements','freelancer_timesheets','freelancer_timesheet_entries','freelancer_claims','freelancer_claim_timesheets','freelancer_deliverables','freelancer_deliverable_revisions','freelancer_deliverable_signoffs','freelancer_performance_reviews'];
        $placeholders=implode(',',array_fill(0,count($tables),'?'));
        $statement=$this->pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ($placeholders) AND engine='InnoDB' AND table_collation='utf8mb4_unicode_ci'");
        $statement->execute($tables);
        self::assertSame(count($tables),(int)$statement->fetchColumn());

        $indexes=$this->pdo->query("SELECT DISTINCT index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND index_name IN ('idx_job_ticket_public_discovery','idx_job_ticket_direct_recipient','uq_job_ticket_quote_bidder','uq_job_ticket_expansion_child','uq_freelancer_profile_user','uq_supervision_ticket_freelancer','uq_freelancer_claim_timesheet','uq_freelancer_deliverable_revision','uq_freelancer_review')")->fetchAll(\PDO::FETCH_COLUMN);
        sort($indexes,SORT_STRING);
        self::assertCount(9,$indexes);

        $foreignKeys=(int)$this->pdo->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND table_name IN ('job_tickets','job_ticket_direct_invites','job_ticket_quotations','job_ticket_expansions','freelancer_profiles','freelancer_supervision_routes','freelancer_engagements','freelancer_timesheets','freelancer_claims','freelancer_deliverables','freelancer_deliverable_revisions','freelancer_deliverable_signoffs','freelancer_performance_reviews')")->fetchColumn();
        self::assertGreaterThanOrEqual(45,$foreignKeys);

        $hashColumns=(int)$this->pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name LIKE '%sha256' AND table_name IN ('job_tickets','job_ticket_classification_revisions','job_ticket_clarifications','job_ticket_quotations','freelancer_timesheets','freelancer_claims','freelancer_deliverable_revisions','freelancer_deliverable_signoffs') AND data_type='char' AND character_maximum_length=64")->fetchColumn();
        self::assertGreaterThanOrEqual(8,$hashColumns);

        $organisation=Identifier::generate();$user=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'schema-marketplace@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Schema Marketplace','schema-marketplace','active')")->execute(['id'=>$organisation]);
        foreach([
            ["INSERT INTO freelancer_profiles(id,organisation_id,user_id,display_name,discipline,registration_status,public_summary,portfolio_refs,service_areas,capacity_status,supervisor_required,independent_appointment_permitted) VALUES(UNHEX('11111111111111111111111111111111'),UNHEX(:o),UNHEX(:u),'Invalid','candidate_architect','candidate','Invalid','[]','[]','available',0,1)",['o'=>$organisation,'u'=>$user]],
            ["INSERT INTO job_tickets(id,organisation_id,poster_user_id,poster_type,title,description,deliverables,budget_min,budget_max,currency,deadline,location,phase,required_registrations,invitation_type,platform_fee_percent,platform_fee_disclosure,classification_snapshot,classification_sha256,automatic_award) VALUES(UNHEX('22222222222222222222222222222222'),UNHEX(:o),UNHEX(:u),'client','Invalid','Invalid','[]',100,50,'ZAR','2026-12-31','Johannesburg','design','[]','public',5,'Platform fee disclosed','{}',REPEAT('a',64),1)",['o'=>$organisation,'u'=>$user]],
        ]as[$sql,$parameters]){try{$statement=$this->pdo->prepare($sql);$statement->execute($parameters);self::fail('Database guard must reject invalid marketplace state.');}catch(\PDOException){self::addToAssertionCount(1);}}
    }
}

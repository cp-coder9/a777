<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Appointment;

use Architex\Platform\Appointment\AppointmentException;
use Architex\Platform\Appointment\AppointmentService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Proposal\ProposalService;
use Architex\Tests\Support\MariaDbTestCase;

final class AppointmentServiceTest extends MariaDbTestCase
{
    public function testAcceptedProposalBecomesGovernedKickoffAndRequiresProfessionalAndHumanApproval(): void
    {
        [$organisationId, $ownerId, $projectId, $proposalId, $proposalHash, $feeRunId] = $this->seedAcceptedProposal();
        $service = new AppointmentService($this->pdo);

        $appointment = $service->createFromAcceptedProposal($organisationId, $ownerId, 'owner', $proposalId, [
            'municipality' => 'City of Tshwane',
            'province' => 'Gauteng',
            'propertyDescription' => 'Erf 15, Pretoria',
            'erfNumber' => '15',
            'landUseOrZoningKnown' => true,
            'professionalBody' => 'SACAP',
            'professionalRegistrationNumber' => 'PrArch 12345',
        ]);

        self::assertSame('pending_professional_confirmation', $appointment['status']);
        self::assertSame('blocked', $appointment['kickoffReadiness']);
        self::assertSame($proposalHash, $appointment['proposalSnapshot']['immutabilityHash']);
        self::assertSame(1, $appointment['revision']);
        self::assertSame(7, count($appointment['readinessGates']));
        self::assertSame(6, count($appointment['documents']));
        self::assertSame(3, count($appointment['initialTasks']));
        self::assertNotEmpty($appointment['recommendations']);

        $confirmed = $service->confirmProfessional($organisationId, $ownerId, 'owner', $appointment['id'], [
            'responsibilityConfirmed' => true,
            'confirmationReason' => 'Accepted scope, terms, fee and responsibility reviewed.',
        ]);
        self::assertSame('confirmed', $confirmed['status']);
        self::assertSame('ready', $confirmed['kickoffReadiness']);
        self::assertTrue($confirmed['readinessGates']['professional_confirmation']['passed']);

        try {
            $service->issueFormalAppointment($organisationId, $ownerId, 'owner', $appointment['id'], [
                'humanApprovalConfirmed' => false,
                'issueReason' => 'Issue appointment letter.',
            ]);
            self::fail('Formal issue must fail closed without human approval.');
        } catch (AppointmentException $exception) {
            self::assertSame('appointment_human_approval_required', $exception->reason);
        }

        $issued = $service->issueFormalAppointment($organisationId, $ownerId, 'owner', $appointment['id'], [
            'humanApprovalConfirmed' => true,
            'issueReason' => 'Responsible professional approved the exact appointment revision.',
        ]);
        self::assertNotNull($issued['formallyIssuedAt']);
        self::assertSame('issued', $issued['documents'][3]['status']);
        self::assertSame('appointment', $this->pdo->query("SELECT current_phase FROM projects WHERE id=UNHEX('$projectId')")->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM project_records WHERE project_id=UNHEX('$projectId') AND record_type='professional_appointment' AND status='issued'")->fetchColumn());

        $proposals = new ProposalService($this->pdo);
        $proposalRevision = $proposals->revise($organisationId, $ownerId, 'owner', $proposalId, [
            'feeRunId' => $feeRunId, 'assumptions' => ['Updated existing information is accurate.'],
            'exclusions' => ['Updated statutory exclusions.'], 'notes' => ['Scope revision after appointment.'],
            'selectedTemplateKeys' => ['standard_sa_professional', 'architectural_services'], 'customTerms' => [],
        ]);
        $issuedProposalRevision = $proposals->issue($organisationId, $ownerId, 'owner', $proposalRevision['id'], [
            'professionalConfirmed' => true, 'legalReviewConfirmed' => true, 'issueReason' => 'Revised scope reviewed.',
        ]);
        $proposals->accept($issuedProposalRevision['acceptanceToken'], 'Appointment Client', true, '127.0.0.1', 'PHPUnit');
        $appointmentRevision = $service->reviseFromAcceptedProposal($organisationId, $ownerId, 'owner', $appointment['id'], $proposalRevision['id'], $issued['projectFacts'], 'Accepted scope changed.');
        self::assertSame(2, $appointmentRevision['revision']);
        self::assertSame($appointment['id'], $appointmentRevision['supersedesAppointmentId']);
        self::assertSame($issuedProposalRevision['proposal']['auditHash'], $appointmentRevision['proposalSnapshot']['immutabilityHash']);
        self::assertSame($proposalHash, $service->get($organisationId, $ownerId, $appointment['id'])['proposalSnapshot']['immutabilityHash']);
    }

    /** @return array{string,string,string,string,string,string} */
    private function seedAcceptedProposal(): array
    {
        $organisationId = Identifier::generate();
        $ownerId = Identifier::generate();
        $projectId = Identifier::generate();
        $feeRunId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'appointment-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Appointment Practice','appointment-practice','active')")->execute(['id' => $organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'APPT-1','Appointment Project','Appointment Client','City of Tshwane','Erf 15','Residential','','architect',UNHEX(:user_id))")->execute(['id' => $projectId, 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO tool_runs(id,organisation_id,user_id,tool_id,tool_version,executed_role,assignment_mode,project_id,status,input_payload,output_payload,audit_hash,audit_reason,issued_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'professional_fee_calculator','1.0.0','owner','internal_project',UNHEX(:project_id),'issued',:input,:output,:hash,'Reviewed fee',UTC_TIMESTAMP(6))")->execute([
            'id' => $feeRunId, 'organisation_id' => $organisationId, 'user_id' => $ownerId, 'project_id' => $projectId,
            'input' => json_encode(['definitionId' => Identifier::generate(), 'projectValue' => 1_000_000], JSON_THROW_ON_ERROR),
            'output' => json_encode(['totalInclVat' => 57_900], JSON_THROW_ON_ERROR), 'hash' => hash('sha256', 'appointment-fee'),
        ]);
        $proposals = new ProposalService($this->pdo);
        $draft = $proposals->createDraft($organisationId, $ownerId, 'owner', [
            'projectId' => $projectId, 'feeRunId' => $feeRunId, 'clientName' => 'Appointment Client',
            'clientEmail' => 'appointment-client@example.test', 'validityDays' => 30,
            'assumptions' => ['Existing information is accurate.'], 'exclusions' => ['Statutory fees.'], 'notes' => [],
            'selectedTemplateKeys' => ['standard_sa_professional', 'architectural_services'], 'customTerms' => [],
        ]);
        $issued = $proposals->issue($organisationId, $ownerId, 'owner', $draft['id'], [
            'professionalConfirmed' => true, 'legalReviewConfirmed' => true, 'issueReason' => 'Reviewed.',
        ]);
        $proposals->accept($issued['acceptanceToken'], 'Appointment Client', true, '127.0.0.1', 'PHPUnit');
        return [$organisationId, $ownerId, $projectId, $draft['id'], $issued['proposal']['auditHash'], $feeRunId];
    }
}

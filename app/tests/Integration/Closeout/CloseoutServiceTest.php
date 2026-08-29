<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Closeout;

use Architex\Platform\Closeout\CloseoutException;
use Architex\Platform\Closeout\CloseoutService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class CloseoutServiceTest extends MariaDbTestCase
{
    public function testConnectedCloseoutScenarioPreservesProfessionalAndStatutoryBoundaries(): void
    {
        [$organisation, $owner, $project] = $this->tenant('Closeout', 'closeout');
        $member = $this->member($organisation, 'closeout-member@example.test');
        $file = $this->file($organisation, $owner, $project);
        $service = new CloseoutService($this->pdo);

        $snag = Identifier::generate();
        $this->pdo->prepare("INSERT INTO site_snags(id,organisation_id,project_id,location_label,description,priority,responsible_party,due_at,evidence_ids,reinspection_evidence_ids,blocks_payment,status,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),'Roof','Waterproofing incomplete','critical','Contractor',UTC_TIMESTAMP(6),'[]','[]',TRUE,'open',UNHEX(:u))")->execute(['id'=>$snag,'o'=>$organisation,'p'=>$project,'u'=>$owner]);
        $blocked = $service->assessPracticalCompletion($organisation, $owner, 'owner', $project, ['checklist'=>['worksComplete'=>true,'healthSafetyFile'=>true,'commissioningComplete'=>true],'humanConfirmed'=>true]);
        self::assertSame('blocked', $blocked['status']);
        self::assertContains('critical_snags_open', $blocked['blockers']);

        $this->pdo->prepare("UPDATE site_snags SET status='closed' WHERE id=UNHEX(:id)")->execute(['id'=>$snag]);
        $ready = $service->assessPracticalCompletion($organisation, $owner, 'owner', $project, ['checklist'=>['worksComplete'=>true,'healthSafetyFile'=>true,'commissioningComplete'=>true],'humanConfirmed'=>true]);
        self::assertSame('ready_for_issue', $ready['status']);
        try {
            $service->issuePracticalCompletion($organisation, $member, 'member', $ready['id'], ['humanConfirmed'=>true,'reason'=>'Attempted member issue.']);
            self::fail('Member issue must be denied.');
        } catch (CloseoutException $exception) {
            self::assertSame('closeout_professional_authority_required', $exception->reason);
        }
        $issued = $service->issuePracticalCompletion($organisation, $owner, 'owner', $ready['id'], ['humanConfirmed'=>true,'reason'=>'Principal agent reviewed the exact closeout evidence.']);
        self::assertSame('issued', $issued['status']);
        self::assertFalse($issued['representsMunicipalApproval']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $issued['snapshotSha256']);
        self::assertSame('client_accepted', $service->acceptPracticalCompletion($organisation, $owner, $issued['id'], ['humanConfirmed'=>true,'reason'=>'Client accepted the issued practical-completion record.'])['status']);

        $pack = $service->createHandoverPack($organisation, $owner, $project);
        foreach (['as_built','warranty','om_manual','keys_access','compliance_certificate'] as $category) {
            $service->addHandoverDocument($organisation, $owner, $pack['id'], ['category'=>$category,'title'=>str_replace('_',' ',$category),'fileId'=>$file,'documentRevisionId'=>null,'versionLabel'=>'v1']);
        }
        $pack = $service->refreshHandoverPack($organisation, $owner, $pack['id']);
        self::assertSame('ready_for_issue', $pack['status']);
        self::assertSame('issued', $service->issueHandoverPack($organisation, $owner, 'owner', $pack['id'], ['humanConfirmed'=>true,'reason'=>'Professional reviewed the indexed handover evidence.'])['status']);

        $occupation = $service->assessOccupationReadiness($organisation, $owner, $project, ['municipalCertificateFileId'=>$file,'municipalCertificateVerified'=>true,'insuranceTransitionConfirmed'=>true,'utilitiesChecklist'=>['water'=>true,'electricity'=>true,'fireSystems'=>true],'humanConfirmed'=>true]);
        self::assertSame('evidence_complete', $occupation['status']);
        self::assertFalse($occupation['representsMunicipalApproval']);
        self::assertSame('reviewed', $service->reviewOccupationReadiness($organisation, $owner, 'owner', $occupation['id'], ['humanConfirmed'=>true,'reason'=>'Internal readiness evidence reviewed; municipal authority remains external.'])['status']);

        $account = $service->assessFinalAccount($organisation, $owner, $project, ['humanConfirmed'=>true]);
        self::assertSame('blocked', $account['status']);
        self::assertContains('commercial_baseline_missing', $account['blockers']);
        self::assertFalse($account['movesMoney']);

        $period = $service->createDefectsLiabilityPeriod($organisation, $owner, $project, ['practicalCompletionId'=>$ready['id'],'startsOn'=>'2025-08-28','endsOn'=>'2026-08-28','humanConfirmed'=>true]);
        $defect = $service->reportDefect($organisation, $owner, $project, ['liabilityPeriodId'=>$period['id'],'sourceSnagId'=>null,'defectType'=>'latent','title'=>'Window seal failure','description'=>'Water ingress after completion.','responsibleParty'=>'Main contractor','evidenceIds'=>[],'humanConfirmed'=>true]);
        self::assertSame('contractor_recalled', $service->recallContractor($organisation, $owner, $defect['id'], ['humanConfirmed'=>true,'reason'=>'Contractor recall issued with defect evidence.'])['status']);
        self::assertSame('corrective_action_submitted', $service->submitDefectCorrection($organisation, $owner, $defect['id'], ['correctiveEvidenceIds'=>[],'humanConfirmed'=>true])['status']);
        self::assertSame('verified_closed', $service->verifyDefect($organisation, $owner, 'owner', $defect['id'], ['humanConfirmed'=>true,'reason'=>'Professional reinspection passed.'])['status']);
        $closed = $service->closeDefectsLiabilityPeriod($organisation, $owner, 'owner', $period['id'], ['humanConfirmed'=>true,'reason'=>'Period elapsed and all recorded defects are verified closed.']);
        self::assertSame('closed', $closed['status']);
        self::assertTrue($closed['retentionReleaseRecommended']);

        $workspace = $service->workspace($organisation, $owner, $project);
        self::assertCount(2, $workspace['practicalCompletions']);
        self::assertCount(5, $workspace['handoverDocuments']);
        self::assertFalse($workspace['boundaries']['occupationReadinessIsMunicipalApproval']);
        self::assertFalse($workspace['boundaries']['closeoutMovesMoney']);
        self::assertGreaterThanOrEqual(12, (int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'closeout.%'")->fetchColumn());
    }

    public function testTenantIsolationRejectsCrossOrganisationWorkspace(): void
    {
        [$organisation, $owner] = $this->tenant('Tenant A', 'closeout-a');
        [, , $otherProject] = $this->tenant('Tenant B', 'closeout-b');
        try {
            (new CloseoutService($this->pdo))->workspace($organisation, $owner, $otherProject);
            self::fail('Cross-tenant workspace must be hidden.');
        } catch (CloseoutException $exception) {
            self::assertSame('closeout_project_not_found', $exception->reason);
        }
    }

    /** @return array{string,string,string} */
    private function tenant(string $name, string $prefix): array
    {
        $organisation=Identifier::generate();$user=Identifier::generate();$project=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$prefix.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$name,'slug'=>$prefix.'-'.substr($organisation,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),:code,:name,'Client','Johannesburg','Erf 1','Residential','','architect','closeout',UNHEX(:u))")->execute(['p'=>$project,'o'=>$organisation,'u'=>$user,'code'=>strtoupper(substr($prefix,0,8)),'name'=>$name.' Project']);
        return[$organisation,$user,$project];
    }
    private function member(string $organisation,string $email):string{$user=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$email]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'member','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);return$user;}
    private function file(string $organisation,string $user,string $project):string{$id=Identifier::generate();$this->pdo->prepare("INSERT INTO stored_files(id,organisation_id,project_id,uploaded_by,original_name,storage_name,mime_type,byte_size,sha256,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),'handover.pdf',:storage,'application/pdf',10,:sha,'active')")->execute(['id'=>$id,'o'=>$organisation,'p'=>$project,'u'=>$user,'storage'=>substr(hash('sha256',$id),0,48),'sha'=>hash('sha256','handover')]);return$id;}
}

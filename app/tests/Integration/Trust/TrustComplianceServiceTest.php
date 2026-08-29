<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Trust;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Trust\TrustComplianceService;
use Architex\Platform\Trust\TrustException;
use Architex\Tests\Support\MariaDbTestCase;

final class TrustComplianceServiceTest extends MariaDbTestCase
{
    public function testConnectedComplianceAndPopiaScenarioRoutesRealRisks(): void
    {
        [$organisation,$owner,$project]=$this->tenant('trust-compliance');
        $reviewer=$this->member($organisation,'trust-reviewer@example.test','admin');
        $file=$this->file($organisation,$owner,$project);
        $service=new TrustComplianceService($this->pdo);

        $insurance=$service->createComplianceItem($organisation,$owner,$project,[
            'subjectType'=>'company','subjectName'=>'Example Architects','category'=>'insurance','documentType'=>'professional_indemnity',
            'authority'=>'Example Insurer','referenceNumber'=>'PI-2026-01','grade'=>'document_uploaded','evidenceFileId'=>$file,
            'coverageAmountCents'=>50000000,'requiredCoverageCents'=>100000000,'details'=>['currency'=>'ZAR'],
            'expiresOn'=>'2030-12-31','consentGiven'=>true,'purpose'=>'Project appointment risk review','retentionUntil'=>'2031-12-31','humanConfirmed'=>true,
        ]);
        self::assertSame('coverage_gap',$insurance['status']);
        self::assertSame('document_uploaded',$insurance['grade']);
        try{$service->reviewComplianceItem($organisation,$reviewer,$insurance['id'],['decision'=>'externally_verified','note'=>'Missing provider.','humanConfirmed'=>true]);self::fail('External status requires real provider evidence.');}catch(TrustException$e){self::assertSame('external_provider_evidence_required',$e->reason);}
        $external=$service->reviewComplianceItem($organisation,$reviewer,$insurance['id'],['decision'=>'externally_verified','provider'=>'Example Insurer verification desk','providerReference'=>'VERIFY-8891','note'=>'Provider response recorded.','humanConfirmed'=>true]);
        self::assertSame('externally_verified',$external['grade']);
        self::assertSame('coverage_gap',$external['status']);

        $professional=$service->createComplianceItem($organisation,$owner,$project,[
            'subjectType'=>'professional','subjectName'=>'Responsible architect','category'=>'professional_registration','documentType'=>'registration_certificate',
            'authority'=>'SACAP','referenceNumber'=>'PR-12345','grade'=>'self_declared','details'=>['registrationCategory'=>'Professional Architect'],
            'expiresOn'=>'2030-12-31','consentGiven'=>true,'purpose'=>'Project team competency review','retentionUntil'=>'2031-12-31','humanConfirmed'=>true,
        ]);
        self::assertSame('current',$professional['status']);

        $company=$service->createComplianceItem($organisation,$owner,$project,[
            'subjectType'=>'company','subjectName'=>'Example Architects','category'=>'company_document','documentType'=>'cipc_registration',
            'authority'=>'CIPC','referenceNumber'=>'2026/123456/07','grade'=>'self_declared','details'=>['entityType'=>'private_company'],
            'expiresOn'=>null,'consentGiven'=>true,'purpose'=>'Supplier onboarding','retentionUntil'=>'2031-12-31','humanConfirmed'=>true,
        ]);
        self::assertSame('current',$company['status']);

        $contractor=$service->createComplianceItem($organisation,$owner,$project,[
            'subjectType'=>'contractor','subjectName'=>'Example Contractor','category'=>'contractor_supplier','documentType'=>'coida_letter_of_good_standing',
            'authority'=>'Compensation Fund','referenceNumber'=>'COIDA-2026','grade'=>'document_uploaded','evidenceFileId'=>$file,'details'=>['trade'=>'general_building'],
            'expiresOn'=>'2020-01-01','consentGiven'=>true,'purpose'=>'Project contractor compliance','retentionUntil'=>'2031-12-31','humanConfirmed'=>true,
        ]);
        self::assertSame('expired',$contractor['status']);

        $activity=$service->createProcessingActivity($organisation,$owner,$project,[
            'activityName'=>'Professional credential verification','purpose'=>'Confirm project-team eligibility','lawfulBasis'=>'consent',
            'dataSubjects'=>['professionals'],'dataCategories'=>['identity','registration'],'recipients'=>['authorised practice administrators'],
            'safeguards'=>'Role-based access and private evidence storage.','retentionUntil'=>'2031-12-31','ownerUserId'=>$owner,'humanConfirmed'=>true,
        ]);
        self::assertSame('active',$activity['status']);

        $request=$service->createDataSubjectRequest($organisation,$owner,$project,[
            'requestType'=>'access','requesterReference'=>'DSR-2026-001','receivedOn'=>'2026-08-29','dueOn'=>'2026-09-28','scope'=>'Professional verification data held by the practice.','humanConfirmed'=>true,
        ]);
        self::assertSame('fulfilled',$service->transitionDataSubjectRequest($organisation,$owner,'owner',$request['id'],['status'=>'fulfilled','reason'=>'Identity checked and secure response issued outside this system.','responseSummary'=>'Recorded data export supplied through the approved secure channel.','humanConfirmed'=>true])['status']);

        $breach=$service->reportBreach($organisation,$owner,$project,[
            'detectedAt'=>'2026-08-29T08:00:00Z','summary'=>'Private verification attachment was exposed to an unintended authorised-project participant.',
            'dataCategories'=>['identity','registration'],'subjectsAffected'=>1,'riskRating'=>'high','regulatorNotificationRequired'=>true,'humanConfirmed'=>true,
        ]);
        self::assertSame('open',$breach['status']);
        self::assertSame('contained',$service->transitionBreach($organisation,$owner,'owner',$breach['id'],['status'=>'contained','reason'=>'Access revoked and affected record isolated.','humanConfirmed'=>true])['status']);
        try{$service->transitionBreach($organisation,$owner,'owner',$breach['id'],['status'=>'closed','reason'=>'Attempted closure without notification evidence.','humanConfirmed'=>true]);self::fail('A required regulator notification must be evidenced before closure.');}catch(TrustException$e){self::assertSame('popia_regulator_notification_evidence_required',$e->reason);}
        self::assertSame('closed',$service->transitionBreach($organisation,$owner,'owner',$breach['id'],['status'=>'closed','reason'=>'Containment complete and external notification timestamp recorded.','regulatorNotifiedAt'=>'2026-08-29T10:00:00Z','humanConfirmed'=>true])['status']);

        $workspace=$service->workspace($organisation,$owner,$project);
        self::assertCount(4,$workspace['complianceItems']);
        self::assertCount(1,$workspace['processingActivities']);
        self::assertCount(1,$workspace['dataSubjectRequests']);
        self::assertCount(1,$workspace['breaches']);
        self::assertGreaterThanOrEqual(3,count($workspace['risks']));
        self::assertFalse($workspace['boundaries']['externalChecksSimulated']);
        self::assertFalse($workspace['boundaries']['regulatorNotificationSent']);
        try{$service->resolveRisk($organisation,$owner,'owner',$workspace['risks'][0]['id'],['reason'=>'Narrative only.','humanConfirmed'=>true]);self::fail('A source gap must not be cleared without replacement evidence.');}catch(TrustException$e){self::assertSame('trust_replacement_evidence_required',$e->reason);}
        $replacement=$service->createComplianceItem($organisation,$owner,$project,[
            'subjectType'=>'company','subjectName'=>'Example Architects','category'=>'insurance','documentType'=>'professional_indemnity',
            'authority'=>'Example Insurer','referenceNumber'=>'PI-2026-02','grade'=>'document_uploaded','evidenceFileId'=>$file,
            'coverageAmountCents'=>150000000,'requiredCoverageCents'=>100000000,'details'=>['currency'=>'ZAR','supersedes'=>$insurance['id']],
            'expiresOn'=>'2031-12-31','consentGiven'=>true,'purpose'=>'Replacement project appointment risk review','retentionUntil'=>'2032-12-31','humanConfirmed'=>true,
        ]);
        self::assertSame('current',$replacement['status']);
        self::assertSame('resolved',$service->resolveRisk($organisation,$owner,'owner',$workspace['risks'][0]['id'],['reason'=>'Replacement coverage evidence was reviewed and linked by the responsible administrator.','resolutionEvidenceItemId'=>$replacement['id'],'humanConfirmed'=>true])['status']);
        self::assertGreaterThanOrEqual(1,(int)$this->pdo->query("SELECT COUNT(*) FROM trust_agent_recommendations WHERE status='resolved'")->fetchColumn());
        self::assertGreaterThanOrEqual(7,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='trust_verification_compliance'")->fetchColumn());
        self::assertGreaterThanOrEqual(4,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type LIKE 'trust.%' OR event_type LIKE 'popia.%'")->fetchColumn());
        self::assertGreaterThanOrEqual(10,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'trust.%' OR action LIKE 'popia.%'")->fetchColumn());
    }

    public function testWorkspaceIsTenantScoped(): void
    {
        [$organisation,$owner]=$this->tenant('trust-scope-a');[,,$otherProject]=$this->tenant('trust-scope-b');
        try{(new TrustComplianceService($this->pdo))->workspace($organisation,$owner,$otherProject);self::fail('Another tenant project must remain hidden.');}catch(TrustException$e){self::assertSame('trust_project_not_found',$e->reason);}
    }

    /** @return array{string,string,string} */
    private function tenant(string$slug):array{$o=Identifier::generate();$u=Identifier::generate();$p=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u,'email'=>$slug.'@example.test']);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$o,'name'=>$slug,'slug'=>$slug.'-'.substr($o,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),'TRUST','Trust Project','Client','City','Erf 1','Office','','architect','design_development',UNHEX(:u))")->execute(['p'=>$p,'o'=>$o,'u'=>$u]);return[$o,$u,$p];}
    private function member(string$o,string$email,string$role):string{$u=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u,'email'=>$email]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u,'role'=>$role]);return$u;}
    private function file(string$o,string$u,string$p):string{$id=Identifier::generate();$this->pdo->prepare("INSERT INTO stored_files(id,organisation_id,project_id,uploaded_by,original_name,storage_name,mime_type,byte_size,sha256) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),'trust.pdf',:stored,'application/pdf',4,:sha)")->execute(['id'=>$id,'o'=>$o,'p'=>$p,'u'=>$u,'stored'=>str_repeat('b',48),'sha'=>hash('sha256','trust')]);return$id;}
}

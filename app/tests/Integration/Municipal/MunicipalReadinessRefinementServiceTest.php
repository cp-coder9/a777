<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Municipal;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Municipal\MunicipalReadinessException;
use Architex\Platform\Municipal\MunicipalReadinessRefinementService;
use Architex\Tests\Support\MariaDbTestCase;

final class MunicipalReadinessRefinementServiceTest extends MariaDbTestCase
{
    public function testPersistsProfileMaterialisesWorkflowAndIssuesImmutablePack():void
    {
        [$organisation,$owner,$candidate,$project,$assessment]=$this->seedWorkflow('ONE');
        $service=new MunicipalReadinessRefinementService($this->pdo);
        $profile=$service->upsertProfile($organisation,$owner,'owner',$project,$this->profile());
        self::assertSame(1,$profile['version']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$profile['profileSha256']);

        $workflow=$service->materialiseAssessment($organisation,$owner,$assessment);
        self::assertSame('blocked',$workflow['workflow']['status']);self::assertCount(2,$workflow['findings']);self::assertCount(2,$workflow['professionalRoutes']);
        $blocker=array_values(array_filter($workflow['findings'],static fn(array$f):bool=>$f['severity']==='blocker'))[0];
        $service->resolveFinding($organisation,$owner,'owner',$assessment,$blocker['id'],['action'=>'resolved','reason'=>'Owner authority evidence was reviewed and accepted.']);

        $routes=$service->workflow($organisation,$owner,$assessment)['professionalRoutes'];
        $architect=array_values(array_filter($routes,static fn(array$r):bool=>$r['discipline']==='architect'))[0];
        $structural=array_values(array_filter($routes,static fn(array$r):bool=>$r['discipline']==='structural_engineer'))[0];
        $service->assignRoute($organisation,$owner,'owner',$assessment,$architect['id'],['assignedUserId'=>$owner]);
        $service->signOffRoute($organisation,$owner,'owner',$assessment,$architect['id'],['humanConfirmed'=>true,'declaration'=>'I accept professional responsibility for the architectural submission evidence.']);
        $service->assignRoute($organisation,$owner,'owner',$assessment,$structural['id'],['assignedUserId'=>$candidate,'supervisorUserId'=>$owner]);
        $service->signOffRoute($organisation,$candidate,'member',$assessment,$structural['id'],['humanConfirmed'=>true,'declaration'=>'Prepared under the appointed supervisor.']);

        $this->expectReason(fn()=>$service->issuePack($organisation,$owner,'owner',$assessment,$this->issueInput()),'professional_review_required');
        $this->insertReview($organisation,$assessment,$owner);
        $this->expectReason(fn()=>$service->issuePack($organisation,$owner,'owner',$assessment,$this->issueInput()),'candidate_supervision_required');
        $service->signOffRoute($organisation,$owner,'owner',$assessment,$structural['id'],['humanConfirmed'=>true,'supervisorConfirmed'=>true,'supervisorDeclaration'=>'I supervised and accept the structural submission evidence.']);

        $pack=$service->issuePack($organisation,$owner,'owner',$assessment,$this->issueInput());
        self::assertSame(1,$pack['packVersion']);self::assertSame('issued',$pack['status']);self::assertSame('architex-backed-submission-pack',$pack['manifest']['standard']);
        self::assertStringContainsString('does not constitute municipal approval',$pack['manifest']['readinessStatement']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$pack['manifestSha256']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='architex_backed_submission_pack'")->fetchColumn());
        self::assertGreaterThanOrEqual(8,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'municipal_%'")->fetchColumn());
        $this->expectReason(fn()=>$service->issuePack($organisation,$owner,'owner',$assessment,$this->issueInput()),'municipal_pack_assessment_already_issued');
    }

    public function testNewAssessmentSupersedesPriorPackAndTenantReadsFailClosed():void
    {
        [$organisation,$owner,,$project,$assessment]=$this->seedWorkflow('TWO',false);
        $service=new MunicipalReadinessRefinementService($this->pdo);$service->upsertProfile($organisation,$owner,'owner',$project,$this->profile());
        $this->makeReady($service,$organisation,$owner,$assessment);$first=$service->issuePack($organisation,$owner,'owner',$assessment,$this->issueInput());
        $secondAssessment=$this->cloneAssessment($organisation,$project,$assessment,$owner);$this->makeReady($service,$organisation,$owner,$secondAssessment);$second=$service->issuePack($organisation,$owner,'owner',$secondAssessment,$this->issueInput());
        self::assertSame(2,$second['packVersion']);self::assertSame('superseded',$service->getPack($organisation,$owner,$first['id'])['status']);
        self::assertSame('issued',$service->getPack($organisation,$owner,$second['id'])['status']);

        [$otherOrganisation,$otherOwner]=$this->seedWorkflow('OTHER',false);
        $this->expectReason(fn()=>$service->getPack($otherOrganisation,$otherOwner,$second['id']),'municipal_pack_not_found');
    }

    private function makeReady(MunicipalReadinessRefinementService $service,string$o,string$u,string$a):void
    {
        $workflow=$service->materialiseAssessment($o,$u,$a);foreach($workflow['findings']as$f)if($f['severity']==='blocker')$service->resolveFinding($o,$u,'owner',$a,$f['id'],['action'=>'resolved','reason'=>'Reviewed exact evidence and resolved the blocker.']);
        foreach($service->workflow($o,$u,$a)['professionalRoutes']as$r){$service->assignRoute($o,$u,'owner',$a,$r['id'],['assignedUserId'=>$u,'supervisorUserId'=>$r['supervisingProfessionalRequired']?$u:null]);$service->signOffRoute($o,$u,'owner',$a,$r['id'],['humanConfirmed'=>true,'declaration'=>'Reviewed and accepted exact discipline evidence.','supervisorConfirmed'=>$r['supervisingProfessionalRequired'],'supervisorDeclaration'=>'Supervision confirmed for exact evidence.']);}
        $this->insertReview($o,$a,$u);
    }

    /** @return array{string,string,string,string,string} */
    private function seedWorkflow(string$code,bool$supervision=true):array
    {
        $o=Identifier::generate();$owner=Identifier::generate();$candidate=Identifier::generate();$p=Identifier::generate();$m=Identifier::generate();$a=Identifier::generate();
        foreach([[$owner,strtolower($code).'-owner@refinement.test'],[$candidate,strtolower($code).'-candidate@refinement.test']]as[$id,$email])$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'email'=>$email]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$o,'name'=>$code,'slug'=>strtolower($code).'-'.substr($o,0,8)]);
        foreach([[$owner,'owner'],[$candidate,'member']]as[$id,$role])$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$id,'role'=>$role]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:o),:code,:name,'Client','City of Johannesburg','Erf 1234','Residential 1','','architect','municipal_submission',UNHEX(:u))")->execute(['id'=>$p,'o'=>$o,'code'=>$code,'name'=>"$code Municipal Pack",'u'=>$owner]);
        $requirements=json_encode([['category'=>'project_passport','items'=>[['id'=>'owner_authority','label'=>'Owner authority','owner'=>'client']]]],JSON_THROW_ON_ERROR);$this->pdo->prepare("INSERT INTO municipal_requirement_versions(id,organisation_id,municipality_key,municipality_name,version,status,source_reference,effective_date,requirements_payload,payload_sha256,created_by,reviewed_by,reviewed_at,review_reason) VALUES(UNHEX(:id),UNHEX(:o),'city-of-johannesburg','City of Johannesburg','practice-2026-1','verified','Official source reviewed by practice','2026-08-30',:requirements,:sha,UNHEX(:created),UNHEX(:reviewed),UTC_TIMESTAMP(6),'Verified exact source')")->execute(['id'=>$m,'o'=>$o,'requirements'=>$requirements,'sha'=>hash('sha256',$requirements),'created'=>$owner,'reviewed'=>$owner]);
        $snapshot=['project'=>['id'=>$p,'projectCode'=>$code,'projectName'=>"$code Municipal Pack",'clientName'=>'Client','municipality'=>'City of Johannesburg','propertyReference'=>'Erf 1234','propertyUse'=>'Residential 1','phase'=>'municipal_submission'],'matrix'=>['id'=>$m,'version'=>'practice-2026-1','status'=>'verified','sourceReference'=>'Official source reviewed by practice','effectiveDate'=>'2026-08-30','payloadSha256'=>hash('sha256',$requirements)],'overallScore'=>76,'checks'=>[['id'=>'owner_authority','category'=>'client_authority','label'=>'Owner authority','owner'=>'client','status'=>'missing','evidenceSource'=>'Project Passport'],['id'=>'energy_review','category'=>'nbr_sans_advisory','label'=>'Energy review','owner'=>'energy_consultant','status'=>'requires_professional_review','evidenceSource'=>'Manual verification required']], 'professionalRouting'=>[['discipline'=>'architect','status'=>'required','reason'=>'Architect owns the drawing package.','teamStatus'=>'project_baseline','professionalApprovalRequired'=>true,'supervisingProfessionalRequired'=>false],['discipline'=>'structural_engineer','status'=>'required','reason'=>'Load-bearing changes require structural input.','teamStatus'=>'active','professionalApprovalRequired'=>true,'supervisingProfessionalRequired'=>$supervision]],'readyForProfessionalSubmissionReview'=>true,'professionalApprovalEligible'=>true,'municipalSubmissionCreated'=>false,'advisoryOnly'=>true];$json=json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);
        $this->pdo->prepare("INSERT INTO municipal_readiness_assessments(id,organisation_id,project_id,matrix_version_id,complexity,overall_score,ready_for_professional_review,assessment_snapshot,snapshot_sha256,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:m),'medium',76,1,:snapshot,:sha,UNHEX(:u))")->execute(['id'=>$a,'o'=>$o,'p'=>$p,'m'=>$m,'snapshot'=>$json,'sha'=>hash('sha256',$json),'u'=>$owner]);return[$o,$owner,$candidate,$p,$a];
    }

    private function cloneAssessment(string$o,string$p,string$from,string$u):string{$id=Identifier::generate();$this->pdo->prepare("INSERT INTO municipal_readiness_assessments(id,organisation_id,project_id,matrix_version_id,complexity,overall_score,ready_for_professional_review,assessment_snapshot,snapshot_sha256,created_by) SELECT UNHEX(:id),organisation_id,project_id,matrix_version_id,complexity,overall_score,ready_for_professional_review,assessment_snapshot,snapshot_sha256,UNHEX(:u) FROM municipal_readiness_assessments WHERE id=UNHEX(:from) AND organisation_id=UNHEX(:o) AND project_id=UNHEX(:p)")->execute(['id'=>$id,'u'=>$u,'from'=>$from,'o'=>$o,'p'=>$p]);return$id;}
    private function insertReview(string$o,string$a,string$u):void{$this->pdo->prepare("INSERT INTO municipal_readiness_reviews(id,organisation_id,assessment_id,decision,reason,assessment_sha256,reviewed_by) SELECT UNHEX(:id),UNHEX(:o),UNHEX(:assessment),'approved_for_professional_submission_review','Reviewed exact readiness evidence.',snapshot_sha256,UNHEX(:u) FROM municipal_readiness_assessments WHERE id=UNHEX(:source_assessment) ON DUPLICATE KEY UPDATE reason=VALUES(reason)")->execute(['id'=>Identifier::generate(),'o'=>$o,'assessment'=>$a,'u'=>$u,'source_assessment'=>$a]);}
    /** @return array<string,mixed> */private function profile():array{return['applicationType'=>'building_plan','erf'=>'1234','farm'=>null,'portion'=>null,'sectionalTitle'=>null,'address'=>'12 Example Road, Sandton','municipality'=>'City of Johannesburg','zoning'=>'Residential 1','landUseAssumptions'=>'Zoning assumed from reviewed property record.','overlays'=>[],'servitudes'=>[],'heritageFlag'=>false,'environmentalFlag'=>false,'ownershipConfirmed'=>true,'agentAuthorised'=>false,'publicParticipationRequired'=>false,'noticeRequired'=>false,'municipalFeeStatus'=>'placeholder'];}
    /** @return array<string,mixed> */private function issueInput():array{return['humanConfirmed'=>true,'principalProfessional'=>['name'=>'A. Architect','role'=>'architect','registration'=>'PrArch 12345'],'issueReason'=>'Exact assessment, routes, findings and evidence reviewed before issue.'];}
    private function expectReason(callable$operation,string$reason):void{try{$operation();self::fail("Expected $reason");}catch(MunicipalReadinessException$e){self::assertSame($reason,$e->reason);}}
}

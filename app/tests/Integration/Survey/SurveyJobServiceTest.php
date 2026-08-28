<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Survey;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Survey\SurveyJobException;
use Architex\Platform\Survey\SurveyJobService;
use Architex\Tests\Support\MariaDbTestCase;

final class SurveyJobServiceTest extends MariaDbTestCase
{
    public function testSurveyEvidenceFeesAndProfessionalIssueAreControlled():void
    {
        [$o,$u,$p]=$this->seed();$service=new SurveyJobService($this->pdo);
        $job=$service->create($o,$u,'owner',['projectId'=>$p,'jobNumber'=>'SUR-2026-01','surveyType'=>'topographical','title'=>'Development topographical survey','responsibleRole'=>'land_surveyor','coordinateReferenceSystem'=>'Hartebeesthoek94 / Lo29','verticalDatum'=>'MSL','sourceBasis'=>[['title'=>'Geomatics Profession Act 19 of 2013','url'=>'https://www.gov.za/documents/geomatics-profession-act','retrievedAt'=>'2026-08-28']]]);self::assertSame('draft',$job['status']);self::assertFalse($job['surveyorGeneralApprovalClaimed']);
        $p1=$service->addObservation($o,$u,'owner',$job['id'],1,['pointCode'=>'CP01','observationType'=>'control_point','easting'=>'1000.125','northing'=>'2000.250','elevation'=>'125.400','status'=>'verified','sourceReference'=>'FIELD-BOOK-01','sourceUrl'=>'https://example.test/survey/field-book-01','observedAt'=>'2026-08-28','payload'=>['humanEntered'=>true]]);self::assertSame(2,$p1['jobVersion']);
        try{$service->addObservation($o,$u,'owner',$job['id'],1,['pointCode'=>'STALE','observationType'=>'control_point','easting'=>'0','northing'=>'0','elevation'=>'0','status'=>'verified','sourceReference'=>'STALE','sourceUrl'=>'https://example.test/stale','observedAt'=>'2026-08-28','payload'=>[]]);self::fail('Stale writes must fail.');}catch(SurveyJobException$e){self::assertSame('survey_version_conflict',$e->reason);}
        $service->addObservation($o,$u,'owner',$job['id'],2,['pointCode'=>'CP02','observationType'=>'control_point','easting'=>'1100.500','northing'=>'2100.750','elevation'=>'126.050','status'=>'verified','sourceReference'=>'FIELD-BOOK-01','sourceUrl'=>'https://example.test/survey/field-book-01','observedAt'=>'2026-08-28','payload'=>['humanEntered'=>true]]);
        $service->addDeliverable($o,$u,'owner',$job['id'],3,['deliverableType'=>'topographical_plan','title'=>'Topographical survey plan P01','status'=>'complete','externalReference'=>'TOPO-P01','sourceUrl'=>'https://example.test/survey/topo-p01','payload'=>['signedOutsideArchitex'=>true]]);
        $fees=$service->estimateFees($o,$u,'owner',$job['id'],4,['fieldDays'=>'2.00','fieldDayRateCents'=>150000,'officeDays'=>'1.50','officeDayRateCents'=>120000,'travelDistanceKm'=>'80.00','travelRateCentsPerKm'=>650,'complexityAllowanceCents'=>25000,'statutoryFeeCents'=>45000,'statutorySourceTitle'=>'Chief Surveyor-General prescribed fees','statutorySourceUrl'=>'https://csg.dlrrd.gov.za/fees.htm','statutorySourceRetrievedAt'=>'2026-08-28']);self::assertSame(557000,$fees['privateProfessionalFeeCents']);self::assertSame(45000,$fees['statutoryFeeCents']);self::assertSame(602000,$fees['totalFeeCents']);
        try{$service->issue($o,$u,'owner',$job['id'],5,false,'land_surveyor','Exact observations, deliverable and fees reviewed.','SURVEY-ISSUE-01');self::fail('Human confirmation required.');}catch(SurveyJobException$e){self::assertSame('survey_issue_confirmation_required',$e->reason);}
        $issued=$service->issue($o,$u,'owner',$job['id'],5,true,'land_surveyor','Exact sourced observations, deliverable and separated fees reviewed.','SURVEY-ISSUE-01');self::assertSame('issued',$issued['status']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued['snapshotSha256']);self::assertFalse($issued['surveyorGeneralApprovalClaimed']);self::assertNotNull($issued['projectRecordId']);
    }

    public function testSurveyTenantIsolationFailsClosed():void
    {
        [$o,$u,$p]=$this->seed();[$other,$otherUser]=$this->seed();$service=new SurveyJobService($this->pdo);$job=$service->create($o,$u,'owner',['projectId'=>$p,'jobNumber'=>'SUR-ISO-01','surveyType'=>'boundary','title'=>'Boundary evidence','responsibleRole'=>'land_surveyor','coordinateReferenceSystem'=>'Hartebeesthoek94 / Lo29','verticalDatum'=>'MSL','sourceBasis'=>[['title'=>'Land Survey Act 8 of 1997','url'=>'https://csg.dlrrd.gov.za/Act8Of1997.pdf','retrievedAt'=>'2026-08-28']]]);try{$service->get($other,$otherUser,$job['id']);self::fail('Cross-tenant read must fail.');}catch(SurveyJobException$e){self::assertSame('survey_job_not_found',$e->reason);}
    }

    /** @return array{string,string,string} */private function seed():array{$suffix=substr(Identifier::generate(),0,8);$o=Identifier::generate();$u=Identifier::generate();$reviewer=Identifier::generate();$p=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:u),:email,'hash','active',UTC_TIMESTAMP(6)),(UNHEX(:reviewer),:reviewer_email,'hash','active',UTC_TIMESTAMP(6))")->execute(['u'=>$u,'email'=>"surveyor-$suffix@example.test",'reviewer'=>$reviewer,'reviewer_email'=>"survey-reviewer-$suffix@example.test"]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:o),:name,:slug,'active')")->execute(['o'=>$o,'name'=>"Survey $suffix",'slug'=>"survey-$suffix"]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u]);$this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,registration_body,registration_number,popia_consent_at,processing_purpose,retention_until,reviewed_by,reviewed_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'professional_registration','manually_reviewed','SAGC',:number,UTC_TIMESTAMP(6),'Survey issue test',DATE_ADD(UTC_DATE(),INTERVAL 1 YEAR),UNHEX(:reviewer),UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u,'number'=>"GPrLS-$suffix",'reviewer'=>$reviewer]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),:code,:name,'Client','Tshwane','Erf 70','Mixed use','Survey coordination','land_surveyor','feasibility',UNHEX(:u))")->execute(['p'=>$p,'o'=>$o,'code'=>"SUR-$suffix",'name'=>"Survey project $suffix",'u'=>$u]);return[$o,$u,$p];}
}

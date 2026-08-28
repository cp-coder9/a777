<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Meeting;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Meeting\MeetingException;
use Architex\Platform\Meeting\MeetingService;
use Architex\Tests\Support\MariaDbTestCase;

final class MeetingServiceTest extends MariaDbTestCase
{
    public function testSchedulingRejectsAProjectPhaseThatCannotBeIssued():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProject();
        $input=$this->validInput($projectId);$input['phase']='design';

        try{(new MeetingService($this->pdo))->schedule($organisationId,$ownerId,'owner',$input);self::fail('Unsupported phases must fail before a meeting is persisted.');}
        catch(MeetingException $e){self::assertSame('meeting_phase_invalid',$e->reason);}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM governed_meetings')->fetchColumn());
    }

    public function testConsentReviewAndPublicationGatesProduceImmutableMinutesAndOnlyApprovedWritebacks():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProject();
        $service=new MeetingService($this->pdo);
        $meeting=$service->schedule($organisationId,$ownerId,'owner',[
            'projectId'=>$projectId,'title'=>'Municipal readiness review','meetingType'=>'design_coordination','phase'=>'appointment',
            'startsAt'=>'2030-08-28 09:00:00','durationMinutes'=>60,
            'agenda'=>[['title'=>'Fire review','owner'=>'Lead architect','minutes'=>30],['title'=>'Submission readiness','owner'=>'Project manager','minutes'=>30]],
            'attendees'=>[['identity'=>'chair@example.test','role'=>'chair'],['identity'=>'fire@example.test','role'=>'attendee']],
            'policy'=>['recordingRequested'=>true,'transcriptionRequested'=>true,'consentRequired'=>true,'retentionDays'=>90,'confidentiality'=>'project_confidential'],
        ]);
        self::assertSame('scheduled',$meeting['status']);
        self::assertCount(2,$meeting['agenda']);

        $service->recordConsent($organisationId,$ownerId,'owner',$meeting['id'],'chair@example.test',true);
        try{$service->start($organisationId,$ownerId,'owner',$meeting['id']);self::fail('Every attendee must consent before recording starts.');}catch(MeetingException $e){self::assertSame('meeting_consent_incomplete',$e->reason);}
        $service->recordConsent($organisationId,$ownerId,'owner',$meeting['id'],'fire@example.test',true);
        self::assertSame('live',$service->start($organisationId,$ownerId,'owner',$meeting['id'])['status']);

        $review=$service->conclude($organisationId,$ownerId,'owner',$meeting['id'],[
            'transcript'=>[['time'=>'00:01:10','speaker'=>'Chair','text'=>'Fire review is required before submission.','confidence'=>0.97]],
            'minutes'=>[['type'=>'discussion','text'=>'The team reviewed the municipal fire blocker.','source'=>'00:01:10']],
            'outcomes'=>[
                ['type'=>'decision','title'=>'Hold municipal issue pending fire review','owner'=>'Lead architect','due'=>'2030-09-01','destination'=>'project_record','source'=>'00:01:10'],
                ['type'=>'action','title'=>'Obtain marked-up fire review','owner'=>'Fire engineer','due'=>'2030-08-30','destination'=>'action_centre','source'=>'00:01:10'],
                ['type'=>'action','title'=>'Issue unreviewed submission','owner'=>'Lead architect','due'=>'2030-08-29','destination'=>'action_centre','source'=>'00:01:10'],
            ],
        ]);
        self::assertSame('review_required',$review['status']);
        try{$service->publishMinutes($organisationId,$ownerId,'owner',$meeting['id'],['reviewerConfirmed'=>true,'publishReason'=>'Reviewed.']);self::fail('Pending outcomes must block publication.');}catch(MeetingException $e){self::assertSame('meeting_outcomes_pending',$e->reason);}
        $service->reviewOutcome($organisationId,$ownerId,'owner',$meeting['id'],0,'accepted','Decision verified against transcript.');
        $service->reviewOutcome($organisationId,$ownerId,'owner',$meeting['id'],1,'accepted','Action owner and due date verified.');
        $service->reviewOutcome($organisationId,$ownerId,'owner',$meeting['id'],2,'rejected','Not authorised by the meeting.');
        $published=$service->publishMinutes($organisationId,$ownerId,'owner',$meeting['id'],['reviewerConfirmed'=>true,'publishReason'=>'Chair reviewed transcript, minutes and outcomes.']);
        self::assertSame('published',$published['status']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$published['minutes']['auditHash']);
        self::assertSame(2,(int)$this->pdo->query("SELECT COUNT(*) FROM meeting_outcome_writebacks WHERE meeting_id=UNHEX('{$meeting['id']}')")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE project_id=UNHEX('$projectId') AND record_type='meeting_minutes' AND status='issued'")->fetchColumn());

        $correction=$service->correctMinutes($organisationId,$ownerId,'owner',$meeting['id'],'Correct attendee role label.');
        self::assertSame(2,$correction['revision']);
        self::assertSame('draft',$correction['status']);
        self::assertSame('published',$service->get($organisationId,$ownerId,$meeting['id'])['status']);
    }

    /** @return array{string,string,string} */
    private function seedProject():array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'meeting-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Meeting Practice','meeting-practice','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'MTG-1','Meeting Project','Meeting Client','Tshwane','Erf 20','Residential','','architect','appointment',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }

    /** @return array<string,mixed> */
    private function validInput(string $projectId):array{return['projectId'=>$projectId,'title'=>'Valid governed meeting','meetingType'=>'design_coordination','phase'=>'appointment','startsAt'=>'2030-08-28 09:00:00','durationMinutes'=>60,'agenda'=>[['title'=>'Review','owner'=>'Chair','minutes'=>60]],'attendees'=>[['identity'=>'chair@example.test','role'=>'chair']],'policy'=>['recordingRequested'=>true,'transcriptionRequested'=>true,'consentRequired'=>true,'retentionDays'=>90,'confidentiality'=>'project_confidential']];}
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class MeetingHttpTest extends MariaDbTestCase
{
    public function testMeetingLifecycleRoutesRemainTenantScopedAndHumanPublished():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProject();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$session=(new SessionService($this->pdo))->rotate($ownerId);$headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];
        $created=$kernel->handle($this->json('POST','/api/v1/meetings',$headers,['projectId'=>$projectId,'title'=>'HTTP governed meeting','meetingType'=>'design_coordination','phase'=>'appointment','startsAt'=>'2030-08-28 09:00:00','durationMinutes'=>45,'agenda'=>[['title'=>'Review','owner'=>'Chair','minutes'=>45]],'attendees'=>[['identity'=>'chair@example.test','role'=>'chair']],'policy'=>['recordingRequested'=>true,'transcriptionRequested'=>true,'consentRequired'=>true,'retentionDays'=>90,'confidentiality'=>'project_confidential']]));
        self::assertSame(201,$created->status(),$created->body());$id=$created->json()['data']['id'];
        $blockedStart=$kernel->handle($this->json('POST',"/api/v1/meetings/$id/start",$headers,[]));
        self::assertSame(422,$blockedStart->status(),$blockedStart->body());
        self::assertSame('Record every attendee consent before starting this recorded or transcribed meeting.',$blockedStart->json()['error']['message']);
        self::assertSame(200,$kernel->handle($this->json('POST',"/api/v1/meetings/$id/consents",$headers,['attendeeIdentity'=>'chair@example.test','consented'=>true]))->status());
        $startRequest=$this->json('POST',"/api/v1/meetings/$id/start",$headers,[]);
        self::assertSame('{}',$startRequest->body());
        self::assertSame([],$startRequest->jsonBody());
        $started=$kernel->handle($startRequest);
        self::assertSame(200,$started->status(),$started->body());
        self::assertSame('live',$started->json()['data']['status']);
        $review=$kernel->handle($this->json('POST',"/api/v1/meetings/$id/conclude",$headers,['transcript'=>[['time'=>'00:00:01','speaker'=>'Chair','text'=>'Decision discussed.','confidence'=>0.99]],'minutes'=>[['type'=>'discussion','text'=>'Decision discussed.','source'=>'00:00:01']],'outcomes'=>[['type'=>'decision','title'=>'Hold issue','owner'=>'Chair','due'=>'2030-08-30','destination'=>'project_record','source'=>'00:00:01']]]));
        self::assertSame('review_required',$review->json()['data']['status']);
        self::assertSame(200,$kernel->handle($this->json('POST',"/api/v1/meetings/$id/outcomes/0/review",$headers,['state'=>'accepted','reason'=>'Verified.']))->status());
        $published=$kernel->handle($this->json('POST',"/api/v1/meetings/$id/minutes/publish",$headers,['reviewerConfirmed'=>true,'publishReason'=>'Chair reviewed exact sources.']));
        self::assertSame(200,$published->status(),$published->body());self::assertSame('published',$published->json()['data']['status']);
        $list=$kernel->handle(Request::json('GET','/api/v1/meetings?projectId='.$projectId,$headers));self::assertSame(200,$list->status(),$list->body());self::assertCount(1,$list->json()['data']);
    }

    /** @return array{string,string,string} */
    private function seedProject():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'meeting-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Meeting HTTP','meeting-http','active')")->execute(['id'=>$organisationId]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'MTG-HTTP','Meeting HTTP Project','Client','Tshwane','Erf 21','Residential','','architect','appointment',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);return[$organisationId,$ownerId,$projectId];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function json(string $method,string $path,array $headers,array $body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body===[]?(object)[]:$body,JSON_THROW_ON_ERROR));}
}

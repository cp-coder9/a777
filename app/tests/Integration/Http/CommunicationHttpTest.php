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

final class CommunicationHttpTest extends MariaDbTestCase
{
    public function testContextualDraftSendAndPhaseAssistantRoutesUseAuthenticatedProjectScope():void
    {
        [$organisationId,$ownerId,$memberId,$projectId]=$this->seedProject();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$session=(new SessionService($this->pdo))->rotate($ownerId);$headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];
        $thread=$kernel->handle($this->json('POST','/api/v1/communication-threads',$headers,['projectId'=>$projectId,'title'=>'HTTP project group','phase'=>'design_development','channel'=>'project_group','visibility'=>'project_team','participantUserIds'=>[$memberId]]));self::assertSame(201,$thread->status(),$thread->body());$threadId=$thread->json()['data']['id'];
        $draft=$kernel->handle($this->json('POST',"/api/v1/communication-threads/$threadId/drafts",$headers,['body'=>'Confirm consultant coordination.','phase'=>'design_development','captureType'=>'consultant_query','context'=>['moduleKey'=>'drawings','sourceObjectType'=>'rfi','sourceObjectId'=>'RFI-HTTP-1','title'=>'Consultant coordination','status'=>'open','summary'=>'Consultant response is outstanding.','persistencePolicy'=>'source_object_record','auditPolicy'=>'record_summary','linkedFileIds'=>[]]]));self::assertSame(201,$draft->status(),$draft->body());$messageId=$draft->json()['data']['id'];self::assertSame('draft',$draft->json()['data']['status']);
        $sent=$kernel->handle($this->json('POST',"/api/v1/communications/messages/$messageId/send",$headers,['humanConfirmed'=>true]));self::assertSame('sent',$sent->json()['data']['status']);
        $messages=$kernel->handle(Request::json('GET',"/api/v1/communication-threads/$threadId/messages?captureType=consultant_query",$headers));self::assertSame(200,$messages->status(),$messages->body());self::assertCount(1,$messages->json()['data']);
        $assistant=$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/communication-assistant?phase=design_development",$headers));self::assertSame('Suggested prompts',$assistant->json()['data']['heading']);
    }

    /** @return array{string,string,string,string} */
    private function seedProject():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$memberId=Identifier::generate();$projectId=Identifier::generate();foreach([[$ownerId,'communication-http-owner@example.test'],[$memberId,'communication-http-member@example.test']]as[$id,$email])$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'email'=>$email]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Communication HTTP','communication-http','active')")->execute(['id'=>$organisationId]);foreach([[$ownerId,'owner'],[$memberId,'member']]as[$id,$role])$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:role,'active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$id,'role'=>$role]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'COM-HTTP','Communication HTTP','Client','Tshwane','Erf 32','Commercial','','architect','design_development',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);return[$organisationId,$ownerId,$memberId,$projectId];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */private function json(string $method,string $path,array $headers,array $body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body===[]?(object)[]:$body,JSON_THROW_ON_ERROR));}
}

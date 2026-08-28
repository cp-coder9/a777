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

final class SpecForgeHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedSpecForgeWorkflowPersistsAndIssuesThroughHttp():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$session=(new SessionService($this->pdo))->rotate($ownerId);$headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];
        $missing=$headers;unset($missing['X-CSRF-Token']);self::assertSame(419,$kernel->handle($this->json('POST',"/api/v1/projects/$projectId/specforge-workspaces",$missing,['title'=>'Blocked','revisionCode'=>'P01','issuePurpose'=>'for_tender']))->status());
        $created=$kernel->handle($this->json('POST',"/api/v1/projects/$projectId/specforge-workspaces",$headers,['title'=>'HTTP tender specification','revisionCode'=>'T01','issuePurpose'=>'for_tender']));self::assertSame(201,$created->status(),$created->body());$workspace=$created->json()['data'];
        $sectionResponse=$kernel->handle($this->json('POST','/api/v1/specforge-workspaces/'.$workspace['id'].'/sections',$headers,['expectedVersion'=>1,'code'=>'09','title'=>'Finishes','discipline'=>'architectural','ownerRole'=>'architect','reviewerRole'=>'quantity_surveyor','approverRole'=>'architect']));self::assertSame(201,$sectionResponse->status(),$sectionResponse->body());$section=$sectionResponse->json()['data'];
        $itemResponse=$kernel->handle($this->json('POST','/api/v1/specforge-workspaces/'.$workspace['id'].'/items',$headers,['expectedVersion'=>2,'sectionId'=>$section['id'],'code'=>'FIN-001','title'=>'Lobby wall tile','room'=>'Lobby','package'=>'Finishes','budgetAllowanceCents'=>1000000,'estimatedCostCents'=>950000,'leadTimeDays'=>21,'requiresClientDecision'=>false,'ownerRole'=>'architect','reviewerRole'=>'quantity_surveyor','approverRole'=>'architect']));self::assertSame(201,$itemResponse->status(),$itemResponse->body());$item=$itemResponse->json()['data'];
        $approved=$kernel->handle($this->json('POST','/api/v1/specforge-items/'.$item['id'].'/decisions',$headers,['action'=>'approve','actingRole'=>'architect','humanConfirmed'=>true,'reason'=>'Exact product and cost reviewed.']));self::assertSame(200,$approved->status(),$approved->body());self::assertSame('approved',$approved->json()['data']['status']);
        $issued=$kernel->handle($this->json('POST','/api/v1/specforge-workspaces/'.$workspace['id'].'/issue',$headers,['expectedVersion'=>4,'humanConfirmed'=>true,'professionalRole'=>'architect','issueReason'=>'Responsible professional reviewed exact issue.']));self::assertSame(200,$issued->status(),$issued->body());self::assertSame('issued',$issued->json()['data']['status']);self::assertFalse($issued->json()['data']['externalDeliveryClaimed']);
        $list=$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/specforge-workspaces",$headers));self::assertSame(200,$list->status(),$list->body());self::assertCount(1,$list->json()['data']);
        $show=$kernel->handle(Request::json('GET','/api/v1/specforge-workspaces/'.$workspace['id'],$headers));self::assertSame(200,$show->status(),$show->body());self::assertSame($issued->json()['data']['snapshotSha256'],$show->json()['data']['snapshotSha256']);
        self::assertSame(404,$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/specforge-workspaces",array_replace($headers,['X-Organisation-Id'=>Identifier::generate()])))->status());
    }

    /** @return array{string,string,string} */private function seed():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'specforge-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'SpecForge HTTP',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'specforge-http-'.substr($organisationId,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),'SPEC-HTTP','SpecForge HTTP project','Client','Tshwane','Erf 1','Commercial','','architect','design_development',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'user'=>$ownerId]);return[$organisationId,$ownerId,$projectId];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */private function json(string$m,string$p,array$headers,array$body):Request{return Request::json($m,$p,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}

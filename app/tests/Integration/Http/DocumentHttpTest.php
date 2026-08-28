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

final class DocumentHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedDocumentWorkflowAndReadiness(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$session=(new SessionService($this->pdo))->rotate($ownerId);$headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];
        $created=$kernel->handle($this->json('POST','/api/v1/documents',$headers,['projectId'=>$projectId,'title'=>'HTTP drawing','documentType'=>'drawing','discipline'=>'architectural','phase'=>'design_development','issuePurpose'=>'for_review','revisionCode'=>'P01','notes'=>'HTTP draft.','drawing'=>['drawingNumber'=>'A-HTTP-01','sheetType'=>'floor_plan','scale'=>'1:100']]));self::assertSame(201,$created->status(),$created->body());$id=$created->json()['data']['id'];
        self::assertSame(200,$kernel->handle($this->json('POST',"/api/v1/documents/$id/draft",$headers,['notes'=>'HTTP draft edited.']))->status());
        self::assertSame(200,$kernel->handle($this->json('POST',"/api/v1/documents/$id/submit-review",$headers,['reason'=>'Ready for review.']))->status());
        self::assertSame(422,$kernel->handle($this->json('POST',"/api/v1/documents/$id/approve",$headers,['humanConfirmed'=>false,'reason'=>'Reviewed.']))->status());
        self::assertSame(200,$kernel->handle($this->json('POST',"/api/v1/documents/$id/approve",$headers,['humanConfirmed'=>true,'reason'=>'Exact drawing reviewed.']))->status());
        $issued=$kernel->handle($this->json('POST',"/api/v1/documents/$id/issue",$headers,['humanConfirmed'=>true,'reason'=>'Issued by responsible professional.','issuePurpose'=>'for_construction']));self::assertSame(200,$issued->status(),$issued->body());self::assertSame('issued',$issued->json()['data']['status']);
        $revised=$kernel->handle($this->json('POST',"/api/v1/documents/$id/revisions",$headers,['revisionCode'=>'C02','reason'=>'Coordination changed.','notes'=>'Revised HTTP drawing.','issuePurpose'=>'for_review','drawing'=>['drawingNumber'=>'A-HTTP-01','sheetType'=>'floor_plan','scale'=>'1:50']]));self::assertSame(201,$revised->status(),$revised->body());self::assertSame($id,$revised->json()['data']['previousRevisionId']);
        $list=$kernel->handle(Request::json('GET','/api/v1/documents?projectId='.$projectId,$headers));self::assertSame(200,$list->status(),$list->body());self::assertCount(2,$list->json()['data']);
        $readiness=$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/document-readiness",$headers));self::assertSame(200,$readiness->status(),$readiness->body());self::assertFalse($readiness->json()['data']['construction_issue']['ready']);
    }

    /** @return array{string,string,string} */private function seed():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'document-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Document HTTP','document-http','active')")->execute(['id'=>$organisationId]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'DOC-HTTP','HTTP Document Project','HTTP Client','Tshwane','Erf 111','Commercial','','architect','design_development',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);return[$organisationId,$ownerId,$projectId];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */private function json(string$method,string$path,array$headers,array$body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}

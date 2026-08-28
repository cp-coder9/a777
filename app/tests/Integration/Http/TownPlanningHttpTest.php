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

final class TownPlanningHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedPlanningWorkflowPersistsThroughHttp():void
    {
        [$organisationId,$ownerId,$projectId]=$this->seed();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$session=(new SessionService($this->pdo))->rotate($ownerId);$headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];
        $blocked=$headers;unset($blocked['X-CSRF-Token']);self::assertSame(419,$kernel->handle($this->json('POST',"/api/v1/projects/$projectId/planning-applications",$blocked,['title'=>'Blocked']))->status());
        $created=$kernel->handle($this->json('POST',"/api/v1/projects/$projectId/planning-applications",$headers,['applicationNumber'=>'APP-HTTP-1','applicationType'=>'subdivision','title'=>'Subdivision application','municipality'=>'City of Tshwane','propertyReference'=>'Erf 50','responsibleRole'=>'architect','sourceBasis'=>[['title'=>'Verified scheme extract','url'=>'https://example.test/scheme','retrievedAt'=>'2026-08-28']]]));self::assertSame(201,$created->status(),$created->body());$app=$created->json()['data'];
        $entry=$kernel->handle($this->json('POST','/api/v1/planning-applications/'.$app['id'].'/entries',$headers,['expectedVersion'=>1,'entryType'=>'document','title'=>'Submission pack P01','status'=>'complete','required'=>true,'basis'=>'Municipal checklist','payload'=>['documentRevision'=>'P01']]));self::assertSame(201,$entry->status(),$entry->body());
        $submitted=$kernel->handle($this->json('POST','/api/v1/planning-applications/'.$app['id'].'/submit',$headers,['expectedVersion'=>2,'humanConfirmed'=>true,'professionalRole'=>'architect','reason'=>'Exact P01 evidence pack reviewed and handoff recorded.','externalReference'=>'TSH-HTTP-1']));self::assertSame(200,$submitted->status(),$submitted->body());self::assertSame('submission',$submitted->json()['data']['stage']);
        $list=$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/planning-applications",$headers));self::assertSame(200,$list->status(),$list->body());self::assertCount(1,$list->json()['data']);
        $csv=$kernel->handle(Request::json('GET','/api/v1/planning-applications/'.$app['id'].'/exports/csv',$headers));self::assertSame(200,$csv->status());self::assertSame('text/csv',$csv->header('Content-Type'));self::assertStringContainsString('APP-HTTP-1',$csv->body());self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',(string)$csv->header('X-Content-Sha256'));
        $portfolio=$kernel->handle(Request::json('GET','/api/v1/planning-applications',$headers));self::assertSame(200,$portfolio->status(),$portfolio->body());self::assertCount(1,$portfolio->json()['data']);
        self::assertSame(404,$kernel->handle(Request::json('GET','/api/v1/planning-applications/'.$app['id'],array_replace($headers,['X-Organisation-Id'=>Identifier::generate()])))->status());
    }

    /** @return array{string,string,string} */private function seed():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'planning-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Planning HTTP',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'planning-http-'.substr($organisationId,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),'PLAN-HTTP','Planning HTTP project','Client','Tshwane','Erf 50','Residential','','architect','design_development',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'user'=>$ownerId]);return[$organisationId,$ownerId,$projectId];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */private function json(string$m,string$p,array$headers,array$body):Request{return Request::json($m,$p,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}

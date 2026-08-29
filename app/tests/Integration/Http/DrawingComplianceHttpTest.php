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

final class DrawingComplianceHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedSourceChecklistReviewReadExportAndToolboxRoutesShareOneService():void
    {
        [$organisation,$owner,$project]=$this->seed();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$session=(new SessionService($this->pdo))->rotate($owner);$headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisation,'X-CSRF-Token'=>$session->csrfToken];
        $sources=$kernel->handle(Request::json('GET','/api/v1/drawing-compliance/sources',$headers));self::assertSame(200,$sources->status(),$sources->body());self::assertSame('illustrative_poc_until_verified',$sources->json()['data'][0]['status']);
        $check=$kernel->handle($this->json('POST','/api/v1/drawing-compliance/checklist',$headers,['buildingDescription'=>'Synagogue alterations','seatingCounts'=>['hall'=>220],'availableDrawingTypes'=>['site_plan']]));self::assertSame(200,$check->status(),$check->body());self::assertSame('A2',$check->json()['data']['occupancy']['classification']['class']);
        $payload=['mode'=>'project','projectId'=>$project,'title'=>'HTTP drawing screen','drawingRevision'=>'A101-P03','responsibleRole'=>'architect','boundaryWall'=>['unitType'=>'solid','thicknessMm'=>90,'heightM'=>1.1,'earthRetained'=>false],'buildingDescription'=>'Dwelling boundary wall'];
        $saved=$kernel->handle($this->json('POST','/api/v1/drawing-compliance/reviews',$headers,$payload));self::assertSame(201,$saved->status(),$saved->body());$id=$saved->json()['data']['id'];self::assertSame('pending',$saved->json()['data']['professionalReviewStatus']);
        $read=$kernel->handle(Request::json('GET','/api/v1/drawing-compliance/reviews/'.$id,$headers));self::assertSame(200,$read->status(),$read->body());self::assertSame($saved->json()['data']['snapshotSha256'],$read->json()['data']['snapshotSha256']);
        $export=$kernel->handle(Request::json('GET','/api/v1/drawing-compliance/reviews/'.$id.'/export',$headers));self::assertSame(200,$export->status(),$export->body());self::assertSame('application/json',$export->header('Content-Type'));self::assertSame(hash('sha256',$export->body()),$export->header('X-Content-Sha256'));
        $definitions=$kernel->handle(Request::json('GET','/api/v1/toolbox/definitions',$headers));self::assertSame(200,$definitions->status(),$definitions->body());self::assertContains('sa_council_drawing_compliance_navigator',array_column($definitions->json()['data'],'id'));
    }
    /** @return array{string,string,string} */private function seed():array{$o=Identifier::generate();$u=Identifier::generate();$p=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'drawing-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Drawing HTTP',:slug,'active')")->execute(['id'=>$o,'slug'=>'drawing-http-'.substr($o,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),'DC-HTTP','Drawing HTTP Project','Client','Johannesburg','Erf 10','Residential','Review','architect','municipal_submission',UNHEX(:u))")->execute(['p'=>$p,'o'=>$o,'u'=>$u]);return[$o,$u,$p];}
    /** @param array<string,string>$headers @param array<string,mixed>$body */private function json(string$method,string$path,array$headers,array$body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}

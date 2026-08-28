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

final class EngineeringToolboxHttpTest extends MariaDbTestCase
{
    public function testConcreteEngineeringDefinitionsRunIssueAndPersistThroughAuthenticatedHttp(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProject();
        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};
        $kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$session=(new SessionService($this->pdo))->rotate($ownerId);
        $headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];

        $definitions=$kernel->handle(Request::json('GET','/api/v1/toolbox/definitions',$headers));
        self::assertSame(200,$definitions->status(),$definitions->body());$items=[];foreach($definitions->json()['data']as$item)$items[$item['id']]=$item;
        $engineeringIds=array_values(array_filter(array_keys($items),static fn(string$id):bool=>($items[$id]['category']??null)==='engineering'));sort($engineeringIds);
        self::assertSame([
            'beam_quick_check','concrete_quantity_calculator','finishes_quantity_calculator','labour_productivity_calculator','masonry_quantity_calculator','rational_runoff_calculator','tender_rate_build_up_calculator','voltage_drop_calculator',
        ],$engineeringIds);
        self::assertSame('/toolbox/standalone/beam-quick-check',$items['beam_quick_check']['route']);
        self::assertTrue($items['beam_quick_check']['governance']['requiresProfessionalConfirmation']);

        $input=['catchmentAreaM2'=>12000,'runoffCoefficient'=>0.75,'rainfallIntensityMmPerHour'=>85];
        $completed=$kernel->handle($this->jsonRequest('POST','/api/v1/toolbox/runs',$headers,['toolId'=>'rational_runoff_calculator','input'=>$input,'assignment'=>['mode'=>'internal_project','projectId'=>$projectId],'issueImmediately'=>false]));
        self::assertSame(201,$completed->status(),$completed->body());self::assertSame('completed',$completed->json()['data']['status']);self::assertNull($completed->json()['data']['auditHash']);

        $rejected=$kernel->handle($this->jsonRequest('POST','/api/v1/toolbox/runs',$headers,['toolId'=>'rational_runoff_calculator','input'=>$input,'assignment'=>['mode'=>'internal_project','projectId'=>$projectId],'issueImmediately'=>true,'issueReason'=>'Reviewed.']));
        self::assertSame(422,$rejected->status(),$rejected->body());self::assertSame('professional_confirmation_required',$rejected->json()['error']['code']);

        $input['professionalConfirmation']=['confirmed'=>true,'role'=>'civil_engineer','responsibilityStatement'=>'I reviewed the exact catchment, coefficient, intensity, assumptions, result and limitations.'];
        $unverified=$kernel->handle($this->jsonRequest('POST','/api/v1/toolbox/runs',$headers,['toolId'=>'rational_runoff_calculator','input'=>$input,'assignment'=>['mode'=>'internal_project','projectId'=>$projectId],'issueImmediately'=>true,'issueReason'=>'Civil professional reviewed this exact coordination calculation.']));
        self::assertSame(422,$unverified->status(),$unverified->body());self::assertSame('professional_authority_not_verified',$unverified->json()['error']['code']);
        $this->seedProfessionalAuthority($organisationId,$ownerId,'civil_engineer','professional_registration');
        $issued=$kernel->handle($this->jsonRequest('POST','/api/v1/toolbox/runs',$headers,['toolId'=>'rational_runoff_calculator','input'=>$input,'assignment'=>['mode'=>'internal_project','projectId'=>$projectId],'issueImmediately'=>true,'issueReason'=>'Civil professional reviewed this exact coordination calculation.']));
        self::assertSame(201,$issued->status(),$issued->body());$run=$issued->json()['data'];self::assertSame('issued',$run['status']);self::assertSame('civil_engineer',$run['output']['professionalRoleRequired']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$run['auditHash']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='toolbox' AND record_type='tool_run'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action='tool_run.issued'")->fetchColumn());

        $history=$kernel->handle(Request::json('GET','/api/v1/toolbox/runs?limit=10',$headers));self::assertSame(200,$history->status(),$history->body());self::assertSame($run['id'],$history->json()['data'][0]['id']);
        $exports=$kernel->handle(Request::json('GET','/api/v1/toolbox/runs/'.$run['id'].'/exports',$headers));self::assertSame(200,$exports->status(),$exports->body());self::assertSame(['json','csv','html'],array_column($exports->json()['data'],'format'));
    }

    /** @return array{string,string,string} */
    private function seedProject():array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'engineering-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Engineering HTTP Practice',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'engineering-'.substr($organisationId,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'ENG-HTTP','Engineering HTTP Project','Client','Tshwane','Erf 21','Commercial','','civil_engineer',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);return[$organisationId,$ownerId,$projectId];
    }
    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function jsonRequest(string$method,string$path,array$headers,array$body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
    private function seedProfessionalAuthority(string$organisationId,string$userId,string$profession,string$kind):void
    {
        $reviewerId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$reviewerId,'email'=>'http-reviewer-'.substr($reviewerId,0,8).'@example.test']);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'admin','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$reviewerId]);
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'Verified engineering professional',:profession,'HTTP test profile','[]')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId,'profession'=>$profession]);
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,popia_consent_at,processing_purpose,retention_until,reviewed_by,reviewed_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:kind,'manually_reviewed',UTC_TIMESTAMP(6),'Engineering toolbox HTTP authority test',DATE_ADD(UTC_DATE(),INTERVAL 1 YEAR),UNHEX(:reviewer_id),UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId,'kind'=>$kind,'reviewer_id'=>$reviewerId]);
    }
}

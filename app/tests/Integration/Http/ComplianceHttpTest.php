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

final class ComplianceHttpTest extends MariaDbTestCase
{
    public function testGovernedSourcesAndVerifiedCalculatorRunAreAvailableThroughAuthenticatedHttp():void
    {
        [$organisationId,$ownerId,$memberId,$projectId]=$this->seedTenant();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$sessions=new SessionService($this->pdo);$ownerSession=$sessions->rotate($ownerId);$memberSession=$sessions->rotate($memberId);$owner=$this->headers($organisationId,$ownerSession->token,$ownerSession->csrfToken);$member=$this->headers($organisationId,$memberSession->token,$memberSession->csrfToken);

        $seedResponse=$kernel->handle($this->json('POST','/api/v1/compliance-rule-versions/seed',$owner,[]));self::assertSame(201,$seedResponse->status(),$seedResponse->body());$seed=$seedResponse->json()['data'];self::assertSame('review_required',$seed['status']);self::assertFalse($seed['issueEligible']);
        $listed=$kernel->handle(Request::json('GET','/api/v1/compliance-rule-versions',$member));self::assertSame(200,$listed->status(),$listed->body());self::assertCount(1,$listed->json()['data']);

        $payload=['ruleKey'=>'sans_10400_xa','version'=>'http-licensed-test-1','sourceReference'=>'Disposable HTTP test reviewed source','effectiveDate'=>'2026-08-28','rules'=>$seed['rules']];
        self::assertSame(403,$kernel->handle($this->json('POST','/api/v1/compliance-rule-versions',$member,$payload))->status());
        $missingCsrf=$owner;unset($missingCsrf['X-CSRF-Token']);self::assertSame(419,$kernel->handle($this->json('POST','/api/v1/compliance-rule-versions',$missingCsrf,$payload))->status());
        $createdResponse=$kernel->handle($this->json('POST','/api/v1/compliance-rule-versions',$owner,$payload));self::assertSame(201,$createdResponse->status(),$createdResponse->body());$candidate=$createdResponse->json()['data'];
        $unconfirmed=$kernel->handle($this->json('POST','/api/v1/compliance-rule-versions/'.$candidate['id'].'/verify',$owner,['humanConfirmed'=>false,'verificationReason'=>'Reviewed']));self::assertSame(422,$unconfirmed->status(),$unconfirmed->body());self::assertSame('human_confirmation_required',$unconfirmed->json()['error']['code']);
        $verifiedResponse=$kernel->handle($this->json('POST','/api/v1/compliance-rule-versions/'.$candidate['id'].'/verify',$owner,['humanConfirmed'=>true,'verificationReason'=>'Responsible professional checked the licensed source.']));self::assertSame(200,$verifiedResponse->status(),$verifiedResponse->body());$verified=$verifiedResponse->json()['data'];self::assertSame('verified',$verified['status']);

        $definitions=$kernel->handle(Request::json('GET','/api/v1/toolbox/definitions',$member));self::assertSame(200,$definitions->status(),$definitions->body());self::assertContains('fenestration_xa_calculator',array_column($definitions->json()['data'],'id'));
        $run=$kernel->handle($this->json('POST','/api/v1/toolbox/runs',$member,['toolId'=>'fenestration_xa_calculator','input'=>['ruleVersionId'=>$verified['id'],'project'=>['projectName'=>'HTTP Compliance Project','reference'=>'HTTP-CMP','climateZone'=>4,'buildingType'=>'residential','storeys'=>1,'floorArea'=>100],'elements'=>[['id'=>'W01','storey'=>1,'room'=>'Living','orientation'=>'E','frameType'=>'thermal','glassArea'=>20,'wallArea'=>100,'uValue'=>3.0,'shgc'=>0.6,'shadingFactor'=>0,'description'=>'East window']]],'assignment'=>['mode'=>'internal_project','projectId'=>$projectId],'issueImmediately'=>true,'issueReason'=>'Member responsible professional review completed.']));
        self::assertSame(201,$run->status(),$run->body());self::assertSame('issued',$run->json()['data']['status']);self::assertSame($verified['payloadSha256'],$run->json()['data']['output']['sourcePayloadSha256']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$run->json()['data']['auditHash']);self::assertGreaterThan(0,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type='compliance_action'")->fetchColumn());
        self::assertSame(404,$kernel->handle(Request::json('GET','/api/v1/compliance-rule-versions',array_replace($member,['X-Organisation-Id'=>Identifier::generate()])))->status());
    }

    /** @return array{string,string,string,string} */
    private function seedTenant():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$memberId=Identifier::generate();$projectId=Identifier::generate();foreach([[$ownerId,'compliance-http-owner@example.test'],[$memberId,'compliance-http-member@example.test']]as[$id,$email])$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'email'=>$email]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Compliance HTTP',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'compliance-http-'.substr($organisationId,0,8)]);foreach([[$ownerId,'owner'],[$memberId,'member']]as[$id,$role])$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:role,'active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$id,'role'=>$role]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),'HTTP-CMP','HTTP Compliance Project','Client','Tshwane','Erf 20','Residential','','architect',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'user'=>$ownerId]);return[$organisationId,$ownerId,$memberId,$projectId];}
    /** @return array<string,string> */private function headers(string$organisationId,string$token,string$csrf):array{return['Cookie'=>'architex_session='.$token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$csrf];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */private function json(string$method,string$path,array$headers,array$body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body==[]?(object)[]:$body,JSON_THROW_ON_ERROR));}
}

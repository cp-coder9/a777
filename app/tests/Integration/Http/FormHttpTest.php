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

final class FormHttpTest extends MariaDbTestCase
{
    public function testOwnerCompletesVersionedFormWorkflowThroughAuthenticatedHttp(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedProject();
        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};
        $kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();
        $session=(new SessionService($this->pdo))->rotate($ownerId);
        $headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];

        $templates=$kernel->handle(Request::json('GET','/api/v1/form-templates',$headers));
        self::assertSame(200,$templates->status(),$templates->body());
        $templateId=$templates->json()['data'][0]['id'];

        $created=$kernel->handle($this->json('POST','/api/v1/form-drafts',$headers,['projectId'=>$projectId,'templateId'=>$templateId,'title'=>'HTTP appointment review','values'=>['scopeSummary'=>'Services through municipal submission.']]));
        self::assertSame(201,$created->status(),$created->body());
        $draftId=$created->json()['data']['id'];

        $revised=$kernel->handle($this->json('POST',"/api/v1/form-drafts/$draftId/revisions",$headers,['reason'=>'Municipality reviewed.','values'=>['municipality'=>'City of Johannesburg']]));
        self::assertSame(201,$revised->status(),$revised->body());
        self::assertSame('City of Johannesburg',$revised->json()['data']['values']['municipality']);
        $revisionId=$revised->json()['data']['id'];

        self::assertSame(200,$kernel->handle($this->json('POST',"/api/v1/form-drafts/$revisionId/submit-review",$headers,['reason'=>'Ready for review.']))->status());
        self::assertSame(422,$kernel->handle($this->json('POST',"/api/v1/form-drafts/$revisionId/approve",$headers,['humanConfirmed'=>false,'reason'=>'Reviewed.']))->status());
        self::assertSame(200,$kernel->handle($this->json('POST',"/api/v1/form-drafts/$revisionId/approve",$headers,['humanConfirmed'=>true,'reason'=>'Every field reviewed.']))->status());
        $issued=$kernel->handle($this->json('POST',"/api/v1/form-drafts/$revisionId/issue",$headers,['humanConfirmed'=>true,'reason'=>'Exact approved revision issued.']));
        self::assertSame(200,$issued->status(),$issued->body());
        self::assertSame('issued',$issued->json()['data']['status']);

        $exports=$kernel->handle(Request::json('GET',"/api/v1/form-drafts/$revisionId/exports",$headers));
        self::assertSame(200,$exports->status(),$exports->body());
        self::assertSame(['html','json'],array_column($exports->json()['data'],'format'));
        $download=$kernel->handle(Request::json('GET','/api/v1/form-exports/'.$exports->json()['data'][0]['id'],$headers));
        self::assertSame(200,$download->status(),$download->body());
        self::assertStringContainsString('HTTP appointment review',$download->json()['data']['content']);
    }

    /** @return array{string,string,string} */
    private function seedProject(): array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'form-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Form HTTP','form-http','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'FORM-HTTP','HTTP Form Project','HTTP Client','Tshwane','Erf 88','Residential','','architect','appointment',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$ownerId]);
        return[$organisationId,$ownerId,$projectId];
    }

    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function json(string $method,string $path,array $headers,array $body): Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}

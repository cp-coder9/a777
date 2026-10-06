<?php
declare(strict_types=1);
namespace Architex\Tests\Integration\Http;
use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\SessionService;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Tests\Support\MariaDbTestCase;
/** Synthetic disposable fixtures prove software transitions, not real professional evidence. */
final class ProductionNativeRoleWorkflowHttpTest extends MariaDbTestCase
{
    public function testNativeDraftsAndIndependentReviewPersistWithRoleRefusals(): void
    {
        $org=Identifier::generate();
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Synthetic role workflow fixture',:slug,'active')")->execute(['id'=>$org,'slug'=>'role-proof-'.substr($org,0,8)]);
        $users=[];$headers=[];$sessions=new SessionService($this->pdo);
        foreach(['owner','member','viewer'] as $role){
            $user=Identifier::generate();$users[$role]=$user;
            $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'test-fixture-not-a-login','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$role.'-'.substr($org,0,8).'@example.test']);
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$org,'u'=>$user,'role'=>$role]);
            $session=$sessions->rotate($user);$headers[$role]=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$org,'X-CSRF-Token'=>$session->csrfToken];
        }
        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string $email,string $token,string $expiresAt):void{}};
        $kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();
        foreach($headers as $role=>$scope){
            $registry=$kernel->handle(Request::json('GET','/api/v1/tools',$scope));self::assertSame(200,$registry->status(),$registry->body());
            $tools=array_column($registry->json()['data'],null,'id');self::assertCount(49,$tools);
            self::assertSame($role==='owner',$tools['admin_review']['access']['read']);
            self::assertSame($role!=='viewer',$tools['bom']['access']['read']);
        }
        $project=$kernel->handle($this->json('/api/v1/projects',$headers['owner'],['projectCode'=>'SYNTH-ROLE','projectName'=>'Synthetic disposable workflow','clientName'=>'Synthetic fixture only','municipality'=>'Test municipality','propertyReference'=>'Synthetic reference','propertyUse'=>'Residential','landUseNotes'=>'Not a real property','leadProfessionalRole'=>'architect']));
        self::assertSame(201,$project->status(),$project->body());$projectId=$project->json()['data']['projectId'];
        $body=['toolId'=>'boq_takeoff','input'=>['title'=>'Synthetic measurement','currency'=>'ZAR','contingencyPercent'=>0,'items'=>[['description'=>'Synthetic line','quantity'=>2,'unit'=>'m2','rate'=>3]]],'assignment'=>['mode'=>'internal_project','projectId'=>$projectId],'issueImmediately'=>false,'issueReason'=>null];
        self::assertSame(403,$kernel->handle($this->json('/api/v1/toolbox/runs',$headers['viewer'],$body))->status());
        $run=$kernel->handle($this->json('/api/v1/toolbox/runs',$headers['owner'],$body));self::assertSame(201,$run->status(),$run->body());$runId=$run->json()['data']['id'];
        $history=$kernel->handle(Request::json('GET','/api/v1/toolbox/runs?limit=100',$headers['owner']));$saved=array_column($history->json()['data'],null,'id')[$runId];self::assertSame('completed',$saved['status']);self::assertSame(600,$saved['output']['totalCents']);
        $sql=$this->pdo->prepare('SELECT status FROM tool_runs WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)');$sql->execute(['id'=>$runId,'o'=>$org]);self::assertSame('completed',$sql->fetchColumn());
        $exports=$kernel->handle(Request::json('GET','/api/v1/toolbox/runs/'.$runId.'/exports',$headers['owner']));self::assertSame(200,$exports->status(),$exports->body());self::assertCount(3,$exports->json()['data']);
        foreach($exports->json()['data'] as $artifact){
            $download=$kernel->handle(Request::json('GET','/api/v1/toolbox/exports/'.$artifact['id'],$headers['owner']));
            self::assertSame(200,$download->status(),$download->body());self::assertSame($artifact['mime_type'],$download->header('Content-Type'));self::assertSame($artifact['sha256'],hash('sha256',$download->body()));self::assertSame($artifact['sha256'],$download->header('X-Content-Sha256'));self::assertStringContainsString($artifact['filename'],$download->header('Content-Disposition'));
            self::assertSame(403,$kernel->handle(Request::json('GET','/api/v1/toolbox/exports/'.$artifact['id'],$headers['viewer']))->status());
            self::assertSame(404,$kernel->handle(Request::json('GET','/api/v1/toolbox/exports/'.$artifact['id'],$headers['member']))->status());
        }
        $inspection=['inspectionType'=>'safety','findings'=>['Synthetic test hazard; not site evidence'],'checklist'=>[],'followUps'=>[],'signOffRequired'=>true];
        self::assertSame(403,$kernel->handle($this->json('/api/v1/projects/'.$projectId.'/inspections',$headers['viewer'],$inspection))->status());
        $draft=$kernel->handle($this->json('/api/v1/projects/'.$projectId.'/inspections',$headers['member'],$inspection));self::assertSame(201,$draft->status(),$draft->body());$inspectionId=$draft->json()['data']['id'];
        $workspace=$kernel->handle(Request::json('GET','/api/v1/projects/'.$projectId.'/site-execution-workspace',$headers['viewer']));self::assertSame(200,$workspace->status(),$workspace->body());$inspections=array_column($workspace->json()['data']['inspections'],null,'id');self::assertSame('safety',$inspections[$inspectionId]['inspectionType']);self::assertSame('draft',$inspections[$inspectionId]['status']);
        $feedback=$kernel->handle($this->json('/api/v1/feedback',$headers['viewer'],['category'=>'Usability','message'=>'Synthetic viewer feedback regression only.','context'=>'Role workflow regression','projectId'=>$projectId,'clientRequestId'=>'synthetic-role-'.substr($org,0,16)]));self::assertSame(201,$feedback->status(),$feedback->body());$feedbackId=$feedback->json()['data']['id'];
        self::assertSame(200,$kernel->handle(Request::json('GET','/api/v1/feedback/'.$feedbackId,$headers['viewer']))->status());
        self::assertSame(404,$kernel->handle(Request::json('GET','/api/v1/feedback/'.$feedbackId,$headers['owner']))->status());
        $verification=['kind'=>'professional_registration','grade'=>'self_declared','registrationBody'=>'SACAP','registrationNumber'=>'SYNTHETIC-TEST-NOT-REGISTRATION','purpose'=>'Synthetic disposable regression only','consentGiven'=>true,'retentionUntil'=>'2099-01-01'];
        $submitted=$kernel->handle($this->json('/api/v1/verifications',$headers['member'],$verification));self::assertSame(201,$submitted->status(),$submitted->body());$verificationId=$submitted->json()['data']['id'];
        $review=['decision'=>'manually_reviewed','note'=>'Synthetic software-transition fixture; not professional evidence.','humanConfirmed'=>true];
        self::assertSame(403,$kernel->handle($this->json('/api/v1/verifications/'.$verificationId.'/review',$headers['member'],$review))->status());
        $reviewed=$kernel->handle($this->json('/api/v1/verifications/'.$verificationId.'/review',$headers['owner'],$review));self::assertSame(200,$reviewed->status(),$reviewed->body());self::assertSame('manually_reviewed',$reviewed->json()['data']['grade']);
        $queue=$kernel->handle(Request::json('GET','/api/v1/verification-review-queue',$headers['owner']));self::assertNotContains($verificationId,array_column($queue->json()['data'],'id'));
        $own=$kernel->handle(Request::json('GET','/api/v1/verifications',$headers['member']));self::assertSame('manually_reviewed',array_column($own->json()['data'],null,'id')[$verificationId]['grade']);
        $sql=$this->pdo->prepare('SELECT grade FROM verification_records WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)');$sql->execute(['id'=>$verificationId,'o'=>$org]);self::assertSame('manually_reviewed',$sql->fetchColumn());
        $self=$kernel->handle($this->json('/api/v1/verifications',$headers['owner'],$verification));$ownId=$self->json()['data']['id'];
        $denied=$kernel->handle($this->json('/api/v1/verifications/'.$ownId.'/review',$headers['owner'],$review));self::assertSame(422,$denied->status(),$denied->body());self::assertSame('self_review_forbidden',$denied->json()['error']['code']);
    }
    private function json(string $path,array $headers,array $body):Request{return Request::json('POST',$path,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}

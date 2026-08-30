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

final class MunicipalReadinessHttpTest extends MariaDbTestCase
{
    public function testMatrixGovernanceAndUnifiedProjectAssessmentUseAuthenticatedHttp():void
    {
        [$organisationId,$ownerId,$memberId,$projectId]=$this->seedTenant();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$sessions=new SessionService($this->pdo);$ownerSession=$sessions->rotate($ownerId);$memberSession=$sessions->rotate($memberId);$owner=$this->headers($organisationId,$ownerSession->token,$ownerSession->csrfToken);$member=$this->headers($organisationId,$memberSession->token,$memberSession->csrfToken);

        $seedResponse=$kernel->handle($this->json('POST','/api/v1/municipal-requirement-versions/seed',$owner,['municipality'=>'Tshwane']));self::assertSame(201,$seedResponse->status(),$seedResponse->body());$seed=$seedResponse->json()['data'];self::assertSame('review_required',$seed['status']);self::assertFalse($seed['assessmentApprovalEligible']);
        $listed=$kernel->handle(Request::json('GET','/api/v1/municipal-requirement-versions?municipality=Tshwane',$member));self::assertSame(200,$listed->status(),$listed->body());self::assertCount(1,$listed->json()['data']);
        self::assertSame(403,$kernel->handle($this->json('POST','/api/v1/municipal-requirement-versions/seed',$member,['municipality'=>'Tshwane']))->status());

        $profile=['applicationType'=>'building_plan','erf'=>'20','farm'=>null,'portion'=>null,'sectionalTitle'=>null,'address'=>'20 Example Street, Pretoria','municipality'=>'Tshwane','zoning'=>'Residential 1','landUseAssumptions'=>'Zoning requires professional verification.','overlays'=>[],'servitudes'=>[],'heritageFlag'=>false,'environmentalFlag'=>false,'ownershipConfirmed'=>true,'agentAuthorised'=>false,'publicParticipationRequired'=>false,'noticeRequired'=>false,'municipalFeeStatus'=>'placeholder'];
        $missingProfileCsrf=$member;unset($missingProfileCsrf['X-CSRF-Token']);self::assertSame(419,$kernel->handle($this->json('PUT',"/api/v1/projects/$projectId/municipal-profile",$missingProfileCsrf,$profile))->status());
        $savedProfile=$kernel->handle($this->json('PUT',"/api/v1/projects/$projectId/municipal-profile",$member,$profile));self::assertSame(200,$savedProfile->status(),$savedProfile->body());self::assertSame('building_plan',$savedProfile->json()['data']['applicationType']);
        self::assertSame(200,$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/municipal-profile",$owner))->status());

        $facts=['matrixVersionId'=>$seed['id'],'occupancyType'=>'single_residential','changesLoadBearing'=>true,'changesDrainageOrStormwater'=>false,'publicAccessOrAssembly'=>false,'envelopeEnergyImpact'=>true,'zoningKnown'=>false,'coverageOrParkingRisk'=>true,'boundaryOrServitudeUnclear'=>false,'heritagePotential'=>false,'environmentalSensitivity'=>false,'trafficImpact'=>false,'estimatedConstructionValueZar'=>1800000,'nbrAdvisoryReviewed'=>false,'availableSupportingDocuments'=>[],'professionalSignoffs'=>[],'clientAuthorityConfirmed'=>false];
        $missingCsrf=$member;unset($missingCsrf['X-CSRF-Token']);self::assertSame(419,$kernel->handle($this->json('POST',"/api/v1/projects/$projectId/submission-readiness",$missingCsrf,$facts))->status());
        $created=$kernel->handle($this->json('POST',"/api/v1/projects/$projectId/submission-readiness",$member,$facts));self::assertSame(201,$created->status(),$created->body());$assessment=$created->json()['data'];self::assertSame('medium',$assessment['complexity']['level']);self::assertCount(8,$assessment['categories']);self::assertFalse($assessment['readyForProfessionalSubmissionReview']);self::assertFalse($assessment['municipalSubmissionCreated']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$assessment['snapshotSha256']);self::assertArrayHasKey('refinementWorkflow',$assessment);
        $workflowResponse=$kernel->handle(Request::json('GET','/api/v1/submission-readiness/'.$assessment['id'].'/workflow',$owner));self::assertSame(200,$workflowResponse->status(),$workflowResponse->body());$workflow=$workflowResponse->json()['data'];self::assertNotEmpty($workflow['findings']);self::assertNotEmpty($workflow['professionalRoutes']);
        $finding=$workflow['findings'][0];$resolved=$kernel->handle($this->json('POST','/api/v1/submission-readiness/'.$assessment['id'].'/findings/'.$finding['id'].'/resolve',$member,['action'=>'resolved','reason'=>'Reviewed exact supporting evidence.']));self::assertSame(200,$resolved->status(),$resolved->body());
        $route=$workflow['professionalRoutes'][0];$assigned=$kernel->handle($this->json('POST','/api/v1/submission-readiness/'.$assessment['id'].'/routes/'.$route['id'].'/assign',$owner,['assignedUserId'=>$memberId]));self::assertSame(200,$assigned->status(),$assigned->body());
        $signed=$kernel->handle($this->json('POST','/api/v1/submission-readiness/'.$assessment['id'].'/routes/'.$route['id'].'/sign-off',$member,['humanConfirmed'=>true,'declaration'=>'Reviewed and accepted the exact routed evidence.']));self::assertSame(200,$signed->status(),$signed->body());
        $issue=$kernel->handle($this->json('POST','/api/v1/submission-readiness/'.$assessment['id'].'/packs/issue',$owner,['humanConfirmed'=>true,'issueReason'=>'Reviewed exact readiness evidence.','principalProfessional'=>['name'=>'HTTP Architect','role'=>'architect','registration'=>'PrArch 12345']]));self::assertSame(422,$issue->status(),$issue->body());self::assertSame('verified_municipal_matrix_required',$issue->json()['error']['code']);
        $packs=$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/municipal-submission-packs",$owner));self::assertSame(200,$packs->status(),$packs->body());self::assertSame([],$packs->json()['data']);
        $history=$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/submission-readiness",$owner));self::assertSame(200,$history->status(),$history->body());self::assertCount(1,$history->json()['data']);
        $show=$kernel->handle(Request::json('GET','/api/v1/submission-readiness/'.$assessment['id'],$owner));self::assertSame(200,$show->status(),$show->body());self::assertSame($assessment['snapshotSha256'],$show->json()['data']['snapshotSha256']);
        $approve=$kernel->handle($this->json('POST','/api/v1/submission-readiness/'.$assessment['id'].'/approve',$owner,['humanConfirmed'=>true,'reviewReason'=>'Reviewed exact readiness evidence.']));self::assertSame(422,$approve->status(),$approve->body());self::assertSame('verified_municipal_matrix_required',$approve->json()['error']['code']);
        self::assertSame(404,$kernel->handle(Request::json('GET',"/api/v1/projects/$projectId/submission-readiness",array_replace($owner,['X-Organisation-Id'=>Identifier::generate()])))->status());
    }

    /** @return array{string,string,string,string} */
    private function seedTenant():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$memberId=Identifier::generate();$projectId=Identifier::generate();foreach([[$ownerId,'municipal-http-owner@example.test'],[$memberId,'municipal-http-member@example.test']]as[$id,$email])$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'email'=>$email]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Municipal HTTP',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'municipal-http-'.substr($organisationId,0,8)]);foreach([[$ownerId,'owner'],[$memberId,'member']]as[$id,$role])$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:role,'active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$id,'role'=>$role]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),'HTTP-MUN','HTTP Municipal Project','Client','Tshwane','Erf 20','Residential','','architect','design_development',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'user'=>$ownerId]);return[$organisationId,$ownerId,$memberId,$projectId];}
    /** @return array<string,string> */private function headers(string$o,string$t,string$c):array{return['Cookie'=>'architex_session='.$t,'X-Organisation-Id'=>$o,'X-CSRF-Token'=>$c];}
    /** @param array<string,string> $headers @param array<string,mixed> $body */private function json(string$m,string$p,array$headers,array$body):Request{return Request::json($m,$p,$headers+['Content-Type'=>'application/json'],json_encode($body==[]?(object)[]:$body,JSON_THROW_ON_ERROR));}
}

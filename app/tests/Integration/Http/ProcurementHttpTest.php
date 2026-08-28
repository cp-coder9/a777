<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class ProcurementHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedBuyerAndPublicBidderBoundaries():void
    {
        [$buyerOrganisation,$buyer,$project]=$this->tenant('HTTP Buyer','http-buyer',true);
        [$sellerOrganisation,$seller]=$this->tenant('HTTP Seller','http-seller',false);
        $reviewer=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'procurement-reviewer@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$reviewer]);
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas,directory_visible) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'HTTP Builder','contractor_supplier','Reviewed marketplace profile.','[\"Gauteng\"]',1)")->execute(['id'=>Identifier::generate(),'o'=>$sellerOrganisation,'u'=>$seller]);
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,registration_body,registration_number,popia_consent_at,processing_purpose,retention_until,reviewed_by,reviewed_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'contractor_supplier_compliance','manually_reviewed','Test contractor body','HTTP-CON-1',UTC_TIMESTAMP(6),'HTTP marketplace authority',DATE_ADD(UTC_DATE(),INTERVAL 1 YEAR),UNHEX(:reviewer),UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'o'=>$sellerOrganisation,'u'=>$seller,'reviewer'=>$reviewer]);
        $documentService=new DocumentService($this->pdo);$document=$documentService->create($buyerOrganisation,$buyer,'owner',['projectId'=>$project,'title'=>'HTTP tender scope','documentType'=>'tender_pack','discipline'=>'general','phase'=>'tender_procurement','issuePurpose'=>'for_tender','revisionCode'=>'T01','notes'=>'HTTP procurement scope.']);$document=$documentService->submitForReview($buyerOrganisation,$buyer,'owner',$document['id'],'Ready.');$document=$documentService->approve($buyerOrganisation,$buyer,'owner',$document['id'],true,'Reviewed.');$document=$documentService->issue($buyerOrganisation,$buyer,'owner',$document['id'],true,'Issued for tender.');

        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$sessions=new SessionService($this->pdo);$buyerSession=$sessions->rotate($buyer);$sellerSession=$sessions->rotate($seller);$buyerHeaders=['Cookie'=>'architex_session='.$buyerSession->token,'X-Organisation-Id'=>$buyerOrganisation,'X-CSRF-Token'=>$buyerSession->csrfToken];$sellerHeaders=['Cookie'=>'architex_session='.$sellerSession->token,'X-Organisation-Id'=>$sellerOrganisation,'X-CSRF-Token'=>$sellerSession->csrfToken];
        $created=$kernel->handle($this->json('POST','/api/v1/marketplace/listings',$sellerHeaders,['listingType'=>'contractor','name'=>'HTTP Builder','publicSummary'=>'General building capacity.','disciplinesTrades'=>['general_building'],'serviceAreas'=>['Gauteng'],'capacityStatus'=>'available','supervisionRequired'=>false]));self::assertSame(201,$created->status(),$created->body());$listing=$created->json()['data'];
        $published=$kernel->handle($this->json('POST','/api/v1/marketplace/listings/'.$listing['id'].'/publish',$sellerHeaders,['expectedVersion'=>$listing['version'],'humanConfirmed'=>true,'reason'=>'Reviewed public listing.']));self::assertSame(200,$published->status(),$published->body());self::assertSame('verified',$published->json()['data']['verificationStatus']);
        $directory=$kernel->handle(Request::json('GET','/api/v1/marketplace/listings?trade=general_building&serviceArea=Gauteng'));self::assertSame(200,$directory->status(),$directory->body());self::assertArrayNotHasKey('organisationId',$directory->json()['data'][0]);

        $packageResponse=$kernel->handle($this->json('POST','/api/v1/projects/'.$project.'/procurement-packages',$buyerHeaders,['packageNumber'=>'HTTP-RFQ-1','title'=>'HTTP main contractor RFQ','procurementNeed'=>'Appoint a main contractor','procurementType'=>'main_contractor','procurementMethod'=>'invited_tender','methodReason'=>'Controlled invited tender selected by project team.','scopeSummary'=>'General building scope.','estimatedValueZar'=>1_800_000,'complexity'=>'high','urgency'=>'normal','province'=>'Gauteng','location'=>'Johannesburg','requiredTrades'=>['general_building'],'requiredSpecialists'=>[],'requiredReturnables'=>['priced_scope','programme','tax_clearance','exclusions_schedule'],'evaluationCriteria'=>['price'=>40,'returnables'=>20,'lead_time'=>15,'risk'=>25],'documentRevisionIds'=>[$document['id']],'municipalReadinessScore'=>80,'deadlineAt'=>'2026-09-30T12:00:00Z']));self::assertSame(201,$packageResponse->status(),$packageResponse->body());$package=$packageResponse->json()['data'];
        $issuedResponse=$kernel->handle($this->json('POST','/api/v1/procurement-packages/'.$package['id'].'/issue',$buyerHeaders,['expectedVersion'=>$package['version'],'humanConfirmed'=>true,'reason'=>'Reviewed exact HTTP RFQ package.']));self::assertSame(200,$issuedResponse->status(),$issuedResponse->body());$issued=$issuedResponse->json()['data'];
        $matches=$kernel->handle($this->json('POST','/api/v1/procurement-packages/'.$issued['id'].'/matches',$buyerHeaders,[]));self::assertSame(200,$matches->status(),$matches->body());self::assertCount(1,$matches->json()['data']);
        $invite=$kernel->handle($this->json('POST','/api/v1/procurement-packages/'.$issued['id'].'/invitations',$buyerHeaders,['listingId'=>$listing['id'],'humanConfirmed'=>true]));self::assertSame(201,$invite->status(),$invite->body());$token=$invite->json()['data']['accessToken'];

        $view=$kernel->handle(Request::json('GET','/api/v1/procurement-invitations/'.$token));self::assertSame(200,$view->status(),$view->body());self::assertArrayNotHasKey('organisationId',$view->json()['data']);self::assertFalse($view->json()['data']['emailDeliveryClaimed']);
        $accepted=$kernel->handle($this->json('POST','/api/v1/procurement-invitations/'.$token.'/accept',[],[]));self::assertSame(200,$accepted->status(),$accepted->body());
        $question=$kernel->handle($this->json('POST','/api/v1/procurement-invitations/'.$token.'/clarifications',[],['question'=>'Is drainage included?','material'=>true]));self::assertSame(201,$question->status(),$question->body());self::assertTrue($question->json()['data']['material']);
        $invalid=$kernel->handle(Request::json('GET','/api/v1/procurement-invitations/'.str_repeat('A',43)));self::assertSame(404,$invalid->status(),$invalid->body());self::assertSame('procurement_invitation_not_found',$invalid->json()['error']['code']);
    }

    /** @return array{string,string,string}|array{string,string} */
    private function tenant(string$name,string$prefix,bool$withProject):array{$o=Identifier::generate();$u=Identifier::generate();$p=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u,'email'=>$prefix.'@example.test']);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$o,'name'=>$name,'slug'=>$prefix.'-'.substr($o,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u]);if($withProject){$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),'HTTP-PROC','HTTP Procurement','Client','Johannesburg','Erf 9','Residential','','architect','tender_procurement',UNHEX(:u))")->execute(['p'=>$p,'o'=>$o,'u'=>$u]);return[$o,$u,$p];}return[$o,$u];}
    /** @param array<string,string>$headers @param array<string,mixed>$body */private function json(string$method,string$path,array$headers,array$body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode((object)$body,JSON_THROW_ON_ERROR));}
}

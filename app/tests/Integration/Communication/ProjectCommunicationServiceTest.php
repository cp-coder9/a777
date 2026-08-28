<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Communication;

use Architex\Platform\Communication\CommunicationException;
use Architex\Platform\Communication\ProjectCommunicationService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class ProjectCommunicationServiceTest extends MariaDbTestCase
{
    public function testSharedThreadRequiresHumanSendAndConversionApprovalAndStaysTenantScoped():void
    {
        [$organisationId,$ownerId,$memberId,$projectId]=$this->seedTenant('communication');
        [$otherOrganisationId,$otherOwnerId]=$this->seedTenant('communication-other');
        $service=new ProjectCommunicationService($this->pdo);
        $thread=$service->createThread($organisationId,$ownerId,'owner',[
            'projectId'=>$projectId,'title'=>'Project coordination','phase'=>'design_development','channel'=>'project_group',
            'visibility'=>'project_team','participantUserIds'=>[$memberId],
        ]);
        self::assertSame('Project coordination',$thread['title']);self::assertCount(2,$thread['participants']);
        self::assertCount(1,$service->listThreads($organisationId,$memberId,$projectId));
        $assistant=$service->phaseAssistant($organisationId,$ownerId,$projectId,'design_development');
        self::assertNotEmpty($assistant['suggestedPrompts']);self::assertContains('consultant_query',$assistant['captureTypes']);

        $draft=$service->draftContextualMessage($organisationId,$ownerId,'owner',$thread['id'],[
            'body'=>'Please confirm the structural opening before issue.','phase'=>'design_development','captureType'=>'consultant_query',
            'context'=>['moduleKey'=>'drawings','sourceObjectType'=>'rfi','sourceObjectId'=>'RFI-17','title'=>'Structural opening','status'=>'open','summary'=>'Opening dimensions require confirmation.','persistencePolicy'=>'source_object_record','auditPolicy'=>'record_summary','linkedFileIds'=>[]],
        ]);
        self::assertSame('draft',$draft['status']);self::assertTrue($draft['requiresUserApproval']);
        try{$service->sendDraft($organisationId,$ownerId,'owner',$draft['id'],false);self::fail('A contextual draft must not send itself.');}catch(CommunicationException $e){self::assertSame('communication_send_confirmation_required',$e->reason);}
        $sent=$service->sendDraft($organisationId,$ownerId,'owner',$draft['id'],true);
        self::assertSame('sent',$sent['status']);self::assertSame('rfi',$sent['context']['sourceObjectType']);
        self::assertCount(1,$service->listMessages($organisationId,$memberId,'member',$thread['id'],[]));
        self::assertSame($sent['id'],$service->markRead($organisationId,$memberId,$thread['id'],$sent['id'])['lastReadMessageId']);

        $secondThread=$service->createThread($organisationId,$ownerId,'owner',['projectId'=>$projectId,'title'=>'Separate project topic','phase'=>'design_development','channel'=>'project_group','visibility'=>'project_team','participantUserIds'=>[$memberId]]);
        $base=['body'=>'Invalid contextual relationship.','phase'=>'design_development','context'=>['moduleKey'=>'drawings','sourceObjectType'=>'rfi','sourceObjectId'=>'RFI-18','title'=>'Relationship boundary','status'=>'open','summary'=>'Must remain within its project record boundary.','persistencePolicy'=>'source_object_record','auditPolicy'=>'record_summary','linkedFileIds'=>[]]];
        try{$service->draftContextualMessage($organisationId,$ownerId,'owner',$secondThread['id'],$base+['replyToMessageId'=>$sent['id']]);self::fail('A reply must not cross thread boundaries.');}catch(CommunicationException $e){self::assertSame('communication_reply_not_found',$e->reason);}
        $base['context']['linkedFileIds']=[Identifier::generate()];
        try{$service->draftContextualMessage($organisationId,$ownerId,'owner',$thread['id'],$base);self::fail('A contextual link must resolve to an active file in the same project.');}catch(CommunicationException $e){self::assertSame('communication_context_file_not_found',$e->reason);}

        try{$service->convertMessage($organisationId,$ownerId,'owner',$sent['id'],['conversionType'=>'action','title'=>'Confirm structural opening','assignedUserId'=>$memberId,'dueOn'=>'2030-09-01','humanConfirmed'=>false]);self::fail('Conversion must remain human controlled.');}catch(CommunicationException $e){self::assertSame('communication_conversion_confirmation_required',$e->reason);}
        $conversion=$service->convertMessage($organisationId,$ownerId,'owner',$sent['id'],['conversionType'=>'action','title'=>'Confirm structural opening','assignedUserId'=>$memberId,'dueOn'=>'2030-09-01','humanConfirmed'=>true]);
        self::assertSame('open',$conversion['status']);self::assertSame('action',$conversion['conversionType']);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM communication_actions')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM inbox_items')->fetchColumn());
        self::assertCount(1,$service->listActions($organisationId,$ownerId,$projectId));

        try{$service->getThread($otherOrganisationId,$otherOwnerId,$thread['id']);self::fail('Another tenant must not see the thread.');}catch(CommunicationException $e){self::assertSame('communication_thread_not_found',$e->reason);}
    }

    /** @return array{string,string,string,string} */
    private function seedTenant(string $slug):array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$memberId=Identifier::generate();$projectId=Identifier::generate();
        foreach([[$ownerId,"$slug-owner@example.test"],[$memberId,"$slug-member@example.test"]] as [$id,$email])$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'email'=>$email]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisationId,'name'=>$slug,'slug'=>$slug]);
        foreach([[$ownerId,'owner'],[$memberId,'member']] as [$id,$role])$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:role,'active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$id,'role'=>$role]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),:code,:name,'Client','Tshwane','Erf 31','Commercial','','architect','design_development',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'code'=>strtoupper(substr($slug,0,12)),'name'=>$slug.' project','user_id'=>$ownerId]);
        return[$organisationId,$ownerId,$memberId,$projectId];
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\DedicatedApps;

use Architex\Platform\DedicatedApps\DedicatedAppException;
use Architex\Platform\DedicatedApps\DedicatedAppRegistry;
use Architex\Platform\DedicatedApps\DedicatedAppService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\CreateProject;
use Architex\Platform\Project\ProjectPassportService;
use Architex\Tests\Support\MariaDbTestCase;

final class DedicatedAppServiceTest extends MariaDbTestCase
{
    public function testCredentialWorkflowHumanGateAndOfflineConflictAreTenantScoped():void
    {
        [$organisation,$owner,$project]=$this->tenant('dedicated-a');
        [$otherOrganisation,$otherOwner]=$this->tenant('dedicated-b');
        $service=new DedicatedAppService($this->pdo,new DedicatedAppRegistry());

        $registered=$service->registerInstallation($organisation,$owner,'owner',[
            'appId'=>'field_companion','projectId'=>$project,'name'=>'Site tablet 01','version'=>'1.0.0',
            'buildCommit'=>str_repeat('a',40),'deviceId'=>'site-tablet-01','capabilities'=>['daily logs','snags','RFIs','NCRs','inspection photos','offline sync'],
        ]);
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/',$registered['credentialToken']);
        self::assertSame('registered',$registered['installation']['status']);
        self::assertArrayNotHasKey('credentialToken',$service->installations($organisation,$owner,'owner')[0]);
        self::assertSame($organisation,$service->authenticateClient($registered['credentialToken'])['organisationId']);
        self::assertSame([], $service->installations($otherOrganisation,$otherOwner,'owner'));

        $event=$service->ingestEvent($organisation,$owner,'owner',[
            'eventId'=>'field-approval-0001','installationId'=>$registered['installation']['id'],'projectId'=>$project,
            'type'=>'site_instruction_ready','summary'=>'Site instruction requires accountable approval.',
            'outputs'=>['ProjectRecord','ProjectInboxItem','AuditEvent'],'humanGate'=>'approval','riskLevel'=>'high','fileRefs'=>[],
        ]);
        self::assertSame('requires_human',$event['status']);
        self::assertFalse($event['aiMayExecute']);
        self::assertSame($event['id'],$service->ingestEvent($organisation,$owner,'owner',[
            'eventId'=>'field-approval-0001','installationId'=>$registered['installation']['id'],'projectId'=>$project,
            'type'=>'site_instruction_ready','summary'=>'Site instruction requires accountable approval.',
            'outputs'=>['ProjectRecord','ProjectInboxItem','AuditEvent'],'humanGate'=>'approval','riskLevel'=>'high','fileRefs'=>[],
        ])['id']);
        try{$service->confirmEvent($organisation,$owner,'owner',$event['id'],false,'Not reviewed');self::fail('Sensitive event must fail without human confirmation.');}
        catch(DedicatedAppException$exception){self::assertSame('dedicated_app_human_confirmation_required',$exception->reason);}
        self::assertSame('completed',$service->confirmEvent($organisation,$owner,'owner',$event['id'],true,'Owner reviewed the exact site instruction.')['status']);

        $first=$service->submitSyncBatch($organisation,$owner,'owner',[
            'installationId'=>$registered['installation']['id'],'projectId'=>$project,'clientBatchId'=>'batch-0001','baseVersion'=>0,
            'operations'=>[['clientOperationId'=>'op-1','entityType'=>'daily_log','entityId'=>'log-1','operation'=>'upsert','payload'=>['weather'=>'clear']]],
        ]);
        self::assertSame('applied',$first['status']);self::assertSame(1,$first['serverVersion']);
        $conflict=$service->submitSyncBatch($organisation,$owner,'owner',[
            'installationId'=>$registered['installation']['id'],'projectId'=>$project,'clientBatchId'=>'batch-0002','baseVersion'=>0,
            'operations'=>[['clientOperationId'=>'op-2','entityType'=>'daily_log','entityId'=>'log-1','operation'=>'upsert','payload'=>['weather'=>'rain']]],
        ]);
        self::assertSame('conflict',$conflict['status']);self::assertSame(1,$conflict['serverVersion']);self::assertNotEmpty($conflict['conflicts']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM dedicated_app_workflow_events WHERE external_event_id='field-approval-0001'")->fetchColumn());
    }

    /** @return array{string,string,string} */
    private function tenant(string$slug):array
    {
        $organisation=Identifier::generate();$owner=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$owner,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$slug,'slug'=>$slug]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$owner]);
        $passport=(new ProjectPassportService($this->pdo))->create(new CreateProject($organisation,$owner,strtoupper(substr($slug,0,8)),$slug.' project','Client','Cape Town','Erf 1','Mixed use','','architect'));
        return[$organisation,$owner,$passport->projectId];
    }
}

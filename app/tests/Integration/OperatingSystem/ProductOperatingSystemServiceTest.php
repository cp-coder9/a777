<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\OperatingSystem;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\OperatingSystem\ApiCapabilityService;
use Architex\Platform\OperatingSystem\DeploymentReleaseGateService;
use Architex\Platform\OperatingSystem\ProductOperatingSystemException;
use Architex\Platform\OperatingSystem\ProductOperatingSystemService;
use Architex\Platform\Project\CreateProject;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPassportService;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Records\ProjectRecordService;
use Architex\Tests\Support\MariaDbTestCase;

final class ProductOperatingSystemServiceTest extends MariaDbTestCase
{
    public function testProjectsRealRecordsIntoRoleAwareDecisionsRisksAndClientCards(): void
    {
        [$organisation,$owner,$project]=$this->tenant('unified-a');
        $context=new ProjectContext($organisation,$owner,$project,'owner');
        (new ProjectRecordService($this->pdo))->issue($context,ProjectPhase::Onboarding,'municipal','owner_document','Owner authority document','pending_review',['source'=>'human_upload'],['source-1']);
        $inboxId=(new InboxService($this->pdo))->create($context,'payment_review','Review payment release readiness','A provider payment request needs accountable review.','critical',['sourceModule'=>'finance','humanGate'=>'payment_release','actionTarget'=>'payments_escrow','linkedRecordIds'=>['pay-1']]);

        $service=new ProductOperatingSystemService($this->pdo);
        $snapshot=$service->projectSnapshot($organisation,$owner,'owner',$project);

        self::assertSame($project,$snapshot['context']['projectId']);
        self::assertSame('onboarding',$snapshot['context']['stage']);
        self::assertSame('Architect / lead professional',$snapshot['context']['roleLabel']);
        self::assertSame('critical',$snapshot['operatingState']['status']);
        self::assertSame('payment_release',$snapshot['decisions'][0]['humanGate']);
        self::assertFalse($snapshot['decisions'][0]['aiMayExecute']);
        self::assertSame('budget',$snapshot['risks'][0]['category']);
        self::assertNotEmpty($snapshot['clientCommandCentre']);
        self::assertContains($snapshot['decisions'][0]['status'],['advisory','awaiting_human','blocked','ready','completed']);
        $mobile=$service->mobileInbox($organisation,$owner,'owner');
        self::assertContains('risk_warning',array_column($mobile['items'],'inboxType'));
        self::assertContains('document_request',array_column($mobile['items'],'inboxType'));

        try{$service->transitionInboxItem($organisation,$owner,'owner',$inboxId,'resolved',['humanConfirmed'=>false,'reason'=>'No review']);self::fail('Sensitive inbox transitions require human confirmation.');}
        catch(ProductOperatingSystemException$exception){self::assertSame('operating_human_confirmation_required',$exception->reason);}
        $resolved=$service->transitionInboxItem($organisation,$owner,'owner',$inboxId,'resolved',['humanConfirmed'=>true,'reason'=>'Accountable owner reviewed provider evidence.']);
        self::assertSame('resolved',$resolved['status']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action='operating.inbox_transitioned'")->fetchColumn());
    }

    public function testCapabilityParityAndReleaseGateAreTruthfulAndTenantIsolated(): void
    {
        [$organisation,$owner,$project]=$this->tenant('unified-b');
        [$otherOrganisation,$otherOwner]=$this->tenant('unified-c');
        $capabilities=(new ApiCapabilityService())->report();
        self::assertSame('warn',$capabilities['status']);
        self::assertSame('api_backed',$capabilities['capabilities']['projects']['status']);
        self::assertSame('provider_gated',$capabilities['capabilities']['payments']['status']);
        self::assertFalse($capabilities['capabilities']['payments']['movesMoney']);

        $gateService=new DeploymentReleaseGateService($this->pdo);
        $failed=$gateService->record($organisation,$owner,'owner',[
            'environment'=>'staging','repoBranch'=>'master','repoCommit'=>str_repeat('a',40),'deployedBranch'=>'old','deployedCommit'=>str_repeat('b',40),
            'buildTimestamp'=>'2026-08-30T00:00:00+02:00','apiMode'=>'php-shared-hosting','apiHealthOk'=>true,'databaseReady'=>true,'migrationsReady'=>true,'mailReady'=>false,
            'routeSmoke'=>[['route'=>'/app','status'=>200]],'dependencyVulnerabilityCount'=>0,'humanConfirmed'=>true,
        ]);
        self::assertSame('fail',$failed['status']);
        self::assertStringContainsString('stale',$failed['summary']);
        self::assertCount(1,$gateService->history($organisation,$owner,'owner'));
        self::assertSame([], $gateService->history($otherOrganisation,$otherOwner,'owner'));
        self::assertNotSame($organisation,$otherOrganisation);
        self::assertNotEmpty($project);
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

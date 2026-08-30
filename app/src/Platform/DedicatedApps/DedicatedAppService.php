<?php

declare(strict_types=1);

namespace Architex\Platform\DedicatedApps;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use JsonException;
use PDO;
use Throwable;

final readonly class DedicatedAppService
{
    private const GATES=['none','review','approval','signature','payment_release','municipal_submission','professional_certification','closeout_acceptance'];
    private const SENSITIVE=['approval','signature','payment_release','municipal_submission','professional_certification','closeout_acceptance'];
    private const RISKS=['low','medium','high','critical'];
    public function __construct(private PDO$pdo,private DedicatedAppRegistry$registry){}

    /** @return array<string,mixed> */
    public function catalogue(string$o,string$u,string$r):array
    {
        $this->member($o,$u);$installations=$this->installations($o,$u,$r);$counts=[];
        foreach($installations as$i)$counts[$i['appId']]=($counts[$i['appId']]??0)+1;
        $apps=array_map(fn(array$app)=>$app+['installationCount'=>$counts[$app['id']]??0],$this->registry->all());
        $conflicts=$this->pdo->prepare("SELECT COUNT(*) FROM dedicated_app_sync_batches WHERE organisation_id=UNHEX(:o) AND status='conflict'");$conflicts->execute(['o'=>$o]);
        $events=$this->pdo->prepare("SELECT COUNT(*) FROM dedicated_app_workflow_events WHERE organisation_id=UNHEX(:o) AND status='requires_human'");$events->execute(['o'=>$o]);
        return['apps'=>$apps,'summary'=>['appCount'=>count($apps),'installationCount'=>count($installations),'offlineConflictCount'=>(int)$conflicts->fetchColumn(),'requiresHumanCount'=>(int)$events->fetchColumn(),'aiMayExecute'=>false,'fundsHeldByArchitex'=>false]];
    }

    /** @return list<array<string,mixed>> */
    public function installations(string$o,string$u,string$r):array
    {
        $this->member($o,$u);$s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) project_id,app_id,name,version,build_commit,device_id,status,capabilities,runtime_provenance,audit_writer_ready,file_manager_writer_ready,last_sync_status,last_seen_at,created_at,updated_at FROM dedicated_app_installations WHERE organisation_id=UNHEX(:o) ORDER BY created_at DESC");$s->execute(['o'=>$o]);
        return array_map(fn(array$x)=>$this->installation($x),$s->fetchAll());
    }

    /** @param array<string,mixed>$input @return array{installation:array<string,mixed>,credentialToken:string} */
    public function registerInstallation(string$o,string$u,string$r,array$input):array
    {
        if(!in_array($r,['owner','admin'],true))throw new DedicatedAppException('dedicated_app_registration_forbidden');$this->member($o,$u);
        $appId=$this->key($input['appId']??null,'dedicated_app_id_invalid');$app=$this->registry->get($appId);$project=$this->optionalId($input['projectId']??null,'dedicated_app_project_invalid');if($project!==null)$this->project($o,$project);
        $name=$this->text($input['name']??null,180,'dedicated_app_name_required');$version=$this->text($input['version']??null,80,'dedicated_app_version_required');$commit=$this->commit($input['buildCommit']??null);$device=$this->text($input['deviceId']??null,180,'dedicated_app_device_required');
        $capabilities=$this->strings($input['capabilities']??null,30,120,'dedicated_app_capabilities_invalid');$unknown=array_diff($capabilities,$app['mvpCapabilities']);if($unknown!==[])throw new DedicatedAppException('dedicated_app_capabilities_invalid');
        $token=$this->token();$id=Identifier::generate();$runtime=['kind'=>$app['kind'],'requiresDeviceOrHostAgent'=>$app['requiresDeviceOrHostAgent'],'declaredAt'=>gmdate('c'),'registrationIsOnlineProof'=>false];
        try{$s=$this->pdo->prepare("INSERT INTO dedicated_app_installations(id,organisation_id,project_id,app_id,name,version,build_commit,device_id,credential_hash,status,capabilities,runtime_provenance,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:project),:app,:name,:version,:commit,:device,:hash,'registered',:capabilities,:runtime,UNHEX(:u))");$s->execute(['id'=>$id,'o'=>$o,'project'=>$project,'app'=>$appId,'name'=>$name,'version'=>$version,'commit'=>$commit,'device'=>$device,'hash'=>hash('sha256',$token),'capabilities'=>$this->json($capabilities),'runtime'=>$this->json($runtime),'u'=>$u]);}
        catch(Throwable$e){if((string)$e->getCode()==='23000')throw new DedicatedAppException('dedicated_app_installation_conflict');throw$e;}
        (new AuditService($this->pdo))->append($o,$u,'dedicated_app.installation_registered','dedicated_app_installation',$id,['appId'=>$appId,'version'=>$version,'buildCommit'=>$commit,'deviceId'=>$device,'registrationIsOnlineProof'=>false]);
        return['installation'=>$this->findInstallation($o,$id),'credentialToken'=>$token];
    }

    /** @return array<string,mixed> */
    public function authenticateClient(string$token):array
    {
        if(preg_match('/\A[A-Za-z0-9_-]{43}\z/',$token)!==1)throw new DedicatedAppException('dedicated_app_client_unauthenticated');
        $s=$this->pdo->prepare("SELECT LOWER(HEX(id)) installation_id,LOWER(HEX(organisation_id)) organisation_id,LOWER(HEX(project_id)) project_id,LOWER(HEX(created_by)) actor_user_id,app_id,status FROM dedicated_app_installations WHERE credential_hash=:hash LIMIT 1");$s->execute(['hash'=>hash('sha256',$token)]);$x=$s->fetch();
        if(!is_array($x)||in_array($x['status'],['revoked','disabled'],true))throw new DedicatedAppException('dedicated_app_client_unauthenticated');
        return['installationId'=>$x['installation_id'],'organisationId'=>$x['organisation_id'],'projectId'=>$x['project_id']?:null,'actorUserId'=>$x['actor_user_id'],'appId'=>$x['app_id'],'status'=>$x['status']];
    }

    /** @param array<string,mixed>$input @return array<string,mixed> */
    public function heartbeatClient(string$token,array$input):array
    {
        $scope=$this->authenticateClient($token);$status=$input['status']??'online';if(!in_array($status,['online','degraded'],true))throw new DedicatedAppException('dedicated_app_status_invalid');
        $audit=($input['auditWriterReady']??false)===true;$files=($input['fileManagerWriterReady']??false)===true;$sync=$input['lastSyncStatus']??'never';if(!in_array($sync,['never','synced','conflict','failed'],true))throw new DedicatedAppException('dedicated_app_sync_status_invalid');
        $runtime=['host'=>$this->nullableText($input['host']??null,180),'os'=>$this->nullableText($input['os']??null,180),'reportedVersion'=>$this->nullableText($input['version']??null,80),'reportedBuildCommit'=>isset($input['buildCommit'])?$this->commit($input['buildCommit']):null,'reportedAt'=>gmdate('c'),'selfReported'=>true];
        $s=$this->pdo->prepare("UPDATE dedicated_app_installations SET status=:status,runtime_provenance=:runtime,audit_writer_ready=:audit,file_manager_writer_ready=:files,last_sync_status=:sync,last_seen_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)");$s->execute(['status'=>$status,'runtime'=>$this->json($runtime),'audit'=>(int)$audit,'files'=>(int)$files,'sync'=>$sync,'id'=>$scope['installationId'],'o'=>$scope['organisationId']]);
        return$this->findInstallation($scope['organisationId'],$scope['installationId']);
    }

    /** @param array<string,mixed>$input @return array<string,mixed> */
    public function ingestClientEvent(string$token,array$input):array{$s=$this->authenticateClient($token);$input['installationId']=$s['installationId'];if(!isset($input['projectId'])&&$s['projectId']!==null)$input['projectId']=$s['projectId'];return$this->ingestEvent($s['organisationId'],$s['actorUserId'],'client',$input);}
    /** @param array<string,mixed>$input @return array<string,mixed> */
    public function submitClientSync(string$token,array$input):array{$s=$this->authenticateClient($token);$input['installationId']=$s['installationId'];if(!isset($input['projectId'])&&$s['projectId']!==null)$input['projectId']=$s['projectId'];return$this->submitSyncBatch($s['organisationId'],$s['actorUserId'],'client',$input);}

    /** @param array<string,mixed>$input @return array<string,mixed> */
    public function ingestEvent(string$o,string$u,string$r,array$input):array
    {
        $this->member($o,$u);$installationId=$this->id($input['installationId']??null,'dedicated_app_installation_not_found');$installation=$this->findInstallation($o,$installationId);if(in_array($installation['status'],['revoked','disabled'],true))throw new DedicatedAppException('dedicated_app_installation_disabled');$app=$this->registry->get($installation['appId']);
        $project=$this->optionalId($input['projectId']??null,'dedicated_app_project_invalid');if($project!==null)$this->project($o,$project);$external=$this->text($input['eventId']??null,180,'dedicated_app_event_id_required');$type=$this->key($input['type']??null,'dedicated_app_event_type_invalid');$summary=$this->text($input['summary']??null,1000,'dedicated_app_event_summary_required');
        $outputs=$this->strings($input['outputs']??null,20,80,'dedicated_app_outputs_invalid');if(array_diff($outputs,$app['spineOutputs'])!==[])throw new DedicatedAppException('dedicated_app_outputs_invalid');$gate=$input['humanGate']??null;if(!is_string($gate)||!in_array($gate,self::GATES,true))throw new DedicatedAppException('dedicated_app_human_gate_invalid');$risk=$input['riskLevel']??null;if(!is_string($risk)||!in_array($risk,self::RISKS,true))throw new DedicatedAppException('dedicated_app_risk_invalid');$files=$this->strings($input['fileRefs']??[],50,500,'dedicated_app_file_refs_invalid');
        $projectOutputs=array_intersect(['ProjectRecord','ProjectInboxItem','ProjectRiskSignal','NotificationEvent','FileManagerAsset'],$outputs);if($projectOutputs!==[]&&$project===null)throw new DedicatedAppException('dedicated_app_project_scope_required');if(in_array('FileManagerAsset',$outputs,true))$this->assertFiles($o,(string)$project,$files);
        $blockers=[];$warnings=[];if(!in_array($gate,$app['sensitiveGates'],true))$warnings[]='Human gate is outside the app default; accountable routing review is required.';if($app['id']==='payment_governance_gateway'&&!in_array($gate,['payment_release','approval'],true))$blockers[]='Payment governance requires payment_release or approval.';if($app['id']==='secure_remote_desktop'&&!in_array('FileManagerAsset',$outputs,true))$warnings[]='Remote desktop output should route through FileManager.';
        $status=$blockers!==[]?'blocked':(in_array($gate,self::SENSITIVE,true)?'requires_human':'allowed');$payload=['externalEventId'=>$external,'installationId'=>$installationId,'projectId'=>$project,'type'=>$type,'summary'=>$summary,'outputs'=>$outputs,'humanGate'=>$gate,'riskLevel'=>$risk,'fileRefs'=>$files];$sha=hash('sha256',$this->json($payload));
        $existing=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,payload_sha256 FROM dedicated_app_workflow_events WHERE installation_id=UNHEX(:i) AND external_event_id=:event");$existing->execute(['i'=>$installationId,'event'=>$external]);$old=$existing->fetch();if(is_array($old)){if(!hash_equals($old['payload_sha256'],$sha))throw new DedicatedAppException('dedicated_app_event_idempotency_conflict');return$this->event($o,$old['id']);}
        $id=Identifier::generate();$this->pdo->beginTransaction();try{
            $s=$this->pdo->prepare("INSERT INTO dedicated_app_workflow_events(id,organisation_id,project_id,installation_id,actor_user_id,external_event_id,event_type,summary,outputs,human_gate,risk_level,file_refs,status,blockers,warnings,ai_may_execute,payload_sha256) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:project),UNHEX(:installation),UNHEX(:actor),:external,:type,:summary,:outputs,:gate,:risk,:files,:status,:blockers,:warnings,FALSE,:sha)");
            $s->execute(['id'=>$id,'o'=>$o,'project'=>$project,'installation'=>$installationId,'actor'=>$u,'external'=>$external,'type'=>$type,'summary'=>$summary,'outputs'=>$this->json($outputs),'gate'=>$gate,'risk'=>$risk,'files'=>$this->json($files),'status'=>$status,'blockers'=>$this->json($blockers),'warnings'=>$this->json($warnings),'sha'=>$sha]);
            $recordId=null;$inboxId=null;
            if($project!==null){
                $context=new ProjectContext($o,$u,$project,$r);
                if(in_array('ProjectRecord',$outputs,true)){
                    $recordId=(new ProjectRecordService($this->pdo))->issue($context,$this->projectPhase($o,$project),'dedicated_apps',$type,$summary,$status==='allowed'?'draft':'pending_review',['dedicatedAppEventId'=>$id,'installationId'=>$installationId,'appId'=>$app['id'],'externalEventId'=>$external,'riskLevel'=>$risk,'humanGate'=>$gate,'fileRefs'=>$files,'aiMayExecute'=>false],['required'=>$status==='requires_human','humanApprovalConfirmed'=>false]);
                }
                if(array_intersect(['ProjectInboxItem','ProjectRiskSignal','NotificationEvent'],$outputs)!==[]){
                    $inboxId=(new InboxService($this->pdo))->create($context,'dedicated_app_'.$type,$summary,'Dedicated app output from '.$app['name'].' requires governed review.',$risk,['sourceModule'=>'dedicated_apps','dedicatedAppEventId'=>$id,'appId'=>$app['id'],'humanGate'=>$gate,'status'=>$status,'projectRecordId'=>$recordId,'aiMayExecute'=>false]);
                }
            }
            $link=$this->pdo->prepare("INSERT INTO dedicated_app_event_spine_links(workflow_event_id,organisation_id,project_record_id,inbox_item_id) VALUES(UNHEX(:event),UNHEX(:o),UNHEX(:record),UNHEX(:inbox))");
            $link->execute(['event'=>$id,'o'=>$o,'record'=>$recordId,'inbox'=>$inboxId]);
            if(array_intersect(['NotificationEvent','ProjectInboxItem','ProjectRiskSignal'],$outputs)!==[]){$d=$this->pdo->prepare("INSERT INTO dedicated_app_notification_dispatches(id,organisation_id,workflow_event_id,channel,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:event),'in_app','queued')");$d->execute(['id'=>Identifier::generate(),'o'=>$o,'event'=>$id]);}
            (new AuditService($this->pdo))->append($o,$u,'dedicated_app.workflow_ingested','dedicated_app_workflow_event',$id,['appId'=>$app['id'],'eventType'=>$type,'status'=>$status,'humanGate'=>$gate,'aiMayExecute'=>false,'routing'=>['projectRecordId'=>$recordId,'inboxItemId'=>$inboxId,'fileManagerRefs'=>$files,'notificationRequired'=>array_intersect(['NotificationEvent','ProjectInboxItem','ProjectRiskSignal'],$outputs)!==[]]]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        return$this->event($o,$id);
    }

    /** @return array<string,mixed> */
    public function confirmEvent(string$o,string$u,string$r,string$id,bool$confirmed,string$reason):array
    {
        $this->member($o,$u);if(!$confirmed)throw new DedicatedAppException('dedicated_app_human_confirmation_required');$reason=$this->text($reason,1000,'dedicated_app_confirmation_reason_required');$event=$this->event($o,$id);if($event['status']!=='requires_human')throw new DedicatedAppException('dedicated_app_event_not_awaiting_human');
        $this->pdo->beginTransaction();try{$s=$this->pdo->prepare("UPDATE dedicated_app_workflow_events SET status='completed',confirmed_by=UNHEX(:u),confirmation_reason=:reason,confirmed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o) AND status='requires_human'");$s->execute(['u'=>$u,'reason'=>$reason,'id'=>$id,'o'=>$o]);if($s->rowCount()!==1)throw new DedicatedAppException('dedicated_app_event_state_conflict');$this->pdo->prepare("UPDATE project_records r JOIN dedicated_app_event_spine_links l ON l.project_record_id=r.id SET r.status='approved',r.approvals=:approvals WHERE l.workflow_event_id=UNHEX(:id) AND l.organisation_id=UNHEX(:o) AND r.status='pending_review'")->execute(['approvals'=>$this->json(['required'=>true,'humanApprovalConfirmed'=>true,'approvedBy'=>$u,'reason'=>$reason]),'id'=>$id,'o'=>$o]);(new AuditService($this->pdo))->append($o,$u,'dedicated_app.workflow_confirmed','dedicated_app_workflow_event',$id,['reason'=>$reason,'humanConfirmed'=>true,'projectRecordApproved'=>true]);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}return$this->event($o,$id);
    }

    /** @return array{sent:int,integrationRequired:int,failed:int,remaining:int} */
    public function deliverQueuedNotifications(int$limit=500):array
    {
        $limit=max(1,min(500,$limit));$rows=$this->pdo->query("SELECT LOWER(HEX(d.id)) id,d.channel,LOWER(HEX(l.inbox_item_id)) inbox_id FROM dedicated_app_notification_dispatches d LEFT JOIN dedicated_app_event_spine_links l ON l.workflow_event_id=d.workflow_event_id WHERE d.status='queued' ORDER BY d.created_at ASC LIMIT {$limit}")->fetchAll();$sent=0;$required=0;$failed=0;
        foreach($rows as$row){$status='integration_required';$error='dedicated_app_provider_not_configured';if($row['channel']==='in_app'&&is_string($row['inbox_id'])&&$row['inbox_id']!==''){$status='sent';$error=null;$sent++;}elseif($row['channel']==='in_app'){$failed++;$status='failed';$error='dedicated_app_inbox_evidence_missing';}else{$required++;}$s=$this->pdo->prepare('UPDATE dedicated_app_notification_dispatches SET status=:status,last_error_code=:error WHERE id=UNHEX(:id) AND status=\'queued\'');$s->execute(['status'=>$status,'error'=>$error,'id'=>$row['id']]);}
        $remaining=(int)$this->pdo->query("SELECT COUNT(*) FROM dedicated_app_notification_dispatches WHERE status='queued'")->fetchColumn();return['sent'=>$sent,'integrationRequired'=>$required,'failed'=>$failed,'remaining'=>$remaining];
    }

    /** @param array<string,mixed>$input @return array<string,mixed> */
    public function submitSyncBatch(string$o,string$u,string$r,array$input):array
    {
        $this->member($o,$u);$installationId=$this->id($input['installationId']??null,'dedicated_app_installation_not_found');$installation=$this->findInstallation($o,$installationId);$app=$this->registry->get($installation['appId']);if(!$app['requiresOfflineSupport'])throw new DedicatedAppException('dedicated_app_offline_sync_not_supported');$project=$this->id($input['projectId']??null,'dedicated_app_project_invalid');$this->project($o,$project);$batch=$this->text($input['clientBatchId']??null,180,'dedicated_app_batch_id_required');$base=$input['baseVersion']??null;if(!is_int($base)||$base<0)throw new DedicatedAppException('dedicated_app_base_version_invalid');$operations=$input['operations']??null;if(!is_array($operations)||$operations===[]||count($operations)>100)throw new DedicatedAppException('dedicated_app_operations_invalid');foreach($operations as$op)if(!is_array($op)||!isset($op['clientOperationId'],$op['entityType'],$op['entityId'],$op['operation'],$op['payload']))throw new DedicatedAppException('dedicated_app_operations_invalid');$sha=hash('sha256',$this->json(['baseVersion'=>$base,'operations'=>$operations]));
        $old=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,payload_sha256 FROM dedicated_app_sync_batches WHERE installation_id=UNHEX(:i) AND client_batch_id=:batch");$old->execute(['i'=>$installationId,'batch'=>$batch]);$found=$old->fetch();if(is_array($found)){if(!hash_equals($found['payload_sha256'],$sha))throw new DedicatedAppException('dedicated_app_batch_idempotency_conflict');return$this->syncBatch($o,$found['id']);}
        $id=Identifier::generate();$this->pdo->beginTransaction();try{$this->pdo->prepare("INSERT IGNORE INTO dedicated_app_sync_state(organisation_id,project_id,installation_id,server_version) VALUES(UNHEX(:o),UNHEX(:p),UNHEX(:i),0)")->execute(['o'=>$o,'p'=>$project,'i'=>$installationId]);$state=$this->pdo->prepare("SELECT server_version FROM dedicated_app_sync_state WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND installation_id=UNHEX(:i) FOR UPDATE");$state->execute(['o'=>$o,'p'=>$project,'i'=>$installationId]);$server=(int)$state->fetchColumn();$status=$base===$server?'applied':'conflict';$conflicts=$status==='conflict'?[['code'=>'stale_base_version','clientBaseVersion'=>$base,'serverVersion'=>$server,'resolution'=>'human_review_required']]:[];if($status==='applied'){$server++;$this->pdo->prepare("UPDATE dedicated_app_sync_state SET server_version=:v WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND installation_id=UNHEX(:i)")->execute(['v'=>$server,'o'=>$o,'p'=>$project,'i'=>$installationId]);}$s=$this->pdo->prepare("INSERT INTO dedicated_app_sync_batches(id,organisation_id,project_id,installation_id,actor_user_id,client_batch_id,base_version,server_version,status,operations,conflicts,payload_sha256) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:i),UNHEX(:u),:batch,:base,:server,:status,:operations,:conflicts,:sha)");$s->execute(['id'=>$id,'o'=>$o,'p'=>$project,'i'=>$installationId,'u'=>$u,'batch'=>$batch,'base'=>$base,'server'=>$server,'status'=>$status,'operations'=>$this->json($operations),'conflicts'=>$this->json($conflicts),'sha'=>$sha]);$this->pdo->prepare("UPDATE dedicated_app_installations SET last_sync_status=:sync,last_seen_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id)")->execute(['sync'=>$status==='applied'?'synced':'conflict','id'=>$installationId]);(new AuditService($this->pdo))->append($o,$u,'dedicated_app.sync_'.$status,'dedicated_app_sync_batch',$id,['appId'=>$app['id'],'baseVersion'=>$base,'serverVersion'=>$server,'operationCount'=>count($operations),'silentOverwrite'=>false]);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        return$this->syncBatch($o,$id);
    }

    /** @return list<array<string,mixed>> */public function recentEvents(string$o,string$u,string$r):array{$this->member($o,$u);$s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id FROM dedicated_app_workflow_events WHERE organisation_id=UNHEX(:o) ORDER BY created_at DESC LIMIT 100");$s->execute(['o'=>$o]);return array_map(fn(array$x)=>$this->event($o,$x['id']),$s->fetchAll());}
    /** @return list<array<string,mixed>> */public function syncConflicts(string$o,string$u,string$r):array{$this->member($o,$u);$s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id FROM dedicated_app_sync_batches WHERE organisation_id=UNHEX(:o) AND status='conflict' ORDER BY created_at DESC LIMIT 100");$s->execute(['o'=>$o]);return array_map(fn(array$x)=>$this->syncBatch($o,$x['id']),$s->fetchAll());}

    /** @return array<string,mixed> */private function findInstallation(string$o,string$id):array{$s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) project_id,app_id,name,version,build_commit,device_id,status,capabilities,runtime_provenance,audit_writer_ready,file_manager_writer_ready,last_sync_status,last_seen_at,created_at,updated_at FROM dedicated_app_installations WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)");$s->execute(['id'=>$id,'o'=>$o]);$x=$s->fetch();if(!is_array($x))throw new DedicatedAppException('dedicated_app_installation_not_found');return$this->installation($x);}
    /** @param array<string,mixed>$x @return array<string,mixed> */private function installation(array$x):array{return['id'=>$x['id'],'projectId'=>$x['project_id']?:null,'appId'=>$x['app_id'],'name'=>$x['name'],'version'=>$x['version'],'buildCommit'=>$x['build_commit'],'deviceId'=>$x['device_id'],'status'=>$x['status'],'capabilities'=>$this->decode($x['capabilities']),'runtimeProvenance'=>$this->decode($x['runtime_provenance']),'auditWriterReady'=>(bool)$x['audit_writer_ready'],'fileManagerWriterReady'=>(bool)$x['file_manager_writer_ready'],'lastSyncStatus'=>$x['last_sync_status'],'lastSeenAt'=>$x['last_seen_at'],'createdAt'=>$x['created_at'],'updatedAt'=>$x['updated_at'],'credentialStoredAsHash'=>true];}
    /** @return array<string,mixed> */private function event(string$o,string$id):array{$s=$this->pdo->prepare("SELECT LOWER(HEX(e.id)) id,LOWER(HEX(e.project_id)) project_id,LOWER(HEX(e.installation_id)) installation_id,e.external_event_id,e.event_type,e.summary,e.outputs,e.human_gate,e.risk_level,e.file_refs,e.status,e.blockers,e.warnings,e.ai_may_execute,e.payload_sha256,LOWER(HEX(e.confirmed_by)) confirmed_by,e.confirmation_reason,e.confirmed_at,e.created_at,LOWER(HEX(l.project_record_id)) project_record_id,LOWER(HEX(l.inbox_item_id)) inbox_item_id FROM dedicated_app_workflow_events e LEFT JOIN dedicated_app_event_spine_links l ON l.workflow_event_id=e.id AND l.organisation_id=e.organisation_id WHERE e.id=UNHEX(:id) AND e.organisation_id=UNHEX(:o)");$s->execute(['id'=>$id,'o'=>$o]);$x=$s->fetch();if(!is_array($x))throw new DedicatedAppException('dedicated_app_event_not_found');return['id'=>$x['id'],'projectId'=>$x['project_id']?:null,'installationId'=>$x['installation_id'],'eventId'=>$x['external_event_id'],'type'=>$x['event_type'],'summary'=>$x['summary'],'outputs'=>$this->decode($x['outputs']),'humanGate'=>$x['human_gate'],'riskLevel'=>$x['risk_level'],'fileRefs'=>$this->decode($x['file_refs']),'status'=>$x['status'],'blockers'=>$this->decode($x['blockers']),'warnings'=>$this->decode($x['warnings']),'aiMayExecute'=>(bool)$x['ai_may_execute'],'payloadSha256'=>$x['payload_sha256'],'projectRecordId'=>$x['project_record_id']?:null,'inboxItemId'=>$x['inbox_item_id']?:null,'confirmedBy'=>$x['confirmed_by']?:null,'confirmationReason'=>$x['confirmation_reason'],'confirmedAt'=>$x['confirmed_at'],'createdAt'=>$x['created_at']];}
    /** @return array<string,mixed> */private function syncBatch(string$o,string$id):array{$s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) project_id,LOWER(HEX(installation_id)) installation_id,client_batch_id,base_version,server_version,status,operations,conflicts,payload_sha256,created_at FROM dedicated_app_sync_batches WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)");$s->execute(['id'=>$id,'o'=>$o]);$x=$s->fetch();if(!is_array($x))throw new DedicatedAppException('dedicated_app_sync_batch_not_found');return['id'=>$x['id'],'projectId'=>$x['project_id'],'installationId'=>$x['installation_id'],'clientBatchId'=>$x['client_batch_id'],'baseVersion'=>(int)$x['base_version'],'serverVersion'=>(int)$x['server_version'],'status'=>$x['status'],'operations'=>$this->decode($x['operations']),'conflicts'=>$this->decode($x['conflicts']),'payloadSha256'=>$x['payload_sha256'],'createdAt'=>$x['created_at']];}
    private function member(string$o,string$u):void{$s=$this->pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active'");$s->execute(['o'=>$o,'u'=>$u]);if((int)$s->fetchColumn()!==1)throw new DedicatedAppException('dedicated_app_membership_required');}
    private function project(string$o,string$id):void{$s=$this->pdo->prepare("SELECT COUNT(*) FROM projects WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)");$s->execute(['id'=>$id,'o'=>$o]);if((int)$s->fetchColumn()!==1)throw new DedicatedAppException('dedicated_app_project_not_found');}
    private function projectPhase(string$o,string$id):ProjectPhase{$s=$this->pdo->prepare("SELECT current_phase FROM projects WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)");$s->execute(['id'=>$id,'o'=>$o]);$phase=$s->fetchColumn();if(!is_string($phase))throw new DedicatedAppException('dedicated_app_project_not_found');return ProjectPhase::from($phase);}
    /** @param list<string>$files */private function assertFiles(string$o,string$p,array$files):void{if($files===[])throw new DedicatedAppException('dedicated_app_file_refs_required');foreach($files as$file){$id=$this->id($file,'dedicated_app_file_refs_invalid');$s=$this->pdo->prepare("SELECT 1 FROM stored_files WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND status='active'");$s->execute(['id'=>$id,'o'=>$o,'p'=>$p]);if($s->fetchColumn()===false)throw new DedicatedAppException('dedicated_app_file_ref_not_found');}}
    private function id(mixed$v,string$error):string{if(!is_string($v)||preg_match('/\A[a-f0-9]{32}\z/i',$v)!==1)throw new DedicatedAppException($error);return strtolower($v);}private function optionalId(mixed$v,string$error):?string{return$v===null||$v===''?null:$this->id($v,$error);}private function commit(mixed$v):string{if(!is_string($v)||preg_match('/\A[a-f0-9]{40}\z/i',$v)!==1)throw new DedicatedAppException('dedicated_app_build_commit_invalid');return strtolower($v);}private function key(mixed$v,string$error):string{if(!is_string($v)||preg_match('/\A[a-z][a-z0-9_]{2,99}\z/',$v)!==1)throw new DedicatedAppException($error);return$v;}private function text(mixed$v,int$max,string$error):string{if(!is_string($v)||trim($v)===''||mb_strlen(trim($v))>$max)throw new DedicatedAppException($error);return trim($v);}private function nullableText(mixed$v,int$max):?string{return$v===null||$v===''?null:$this->text($v,$max,'dedicated_app_provenance_invalid');}
    /** @return list<string> */private function strings(mixed$v,int$maxCount,int$maxLength,string$error):array{if(!is_array($v)||count($v)>$maxCount)throw new DedicatedAppException($error);$out=[];foreach($v as$x){if(!is_string($x)||trim($x)===''||mb_strlen(trim($x))>$maxLength)throw new DedicatedAppException($error);$out[]=trim($x);}return array_values(array_unique($out));}private function token():string{return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');}private function json(mixed$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}private function decode(mixed$v):array{try{$x=json_decode((string)$v,true,32,JSON_THROW_ON_ERROR);return is_array($x)?$x:[];}catch(JsonException){return[];}}
}

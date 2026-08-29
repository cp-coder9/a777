<?php

declare(strict_types=1);

namespace Architex\Platform\Agent;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use DateTimeImmutable;
use PDO;
use Throwable;

final readonly class AgentOrchestrationService
{
    private const MODULE = 'agent_orchestration_core';

    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function bootstrap(string $organisationId, string $userId, string $projectId, array $input): array
    {
        $this->assertProject($organisationId, $userId, $projectId);
        $this->confirmed($input);
        $displayName = $this->text($input['displayName'] ?? null, 160, 'agent_display_name_required');
        $purpose = $this->text($input['purpose'] ?? null, 500, 'agent_purpose_required');
        $allowed = $this->strings($input['memoryScope'] ?? null, 30, 'agent_memory_scope_invalid');
        $excluded = array_values(array_unique([...$this->strings($input['memoryExclusions'] ?? null, 30, 'agent_memory_exclusions_invalid'), 'other_users_private_context', 'other_tenants']));
        $retention = $this->date($input['retentionUntil'] ?? null, 'agent_memory_retention_invalid');
        if ($retention < (new DateTimeImmutable('today'))->format('Y-m-d')) throw new AgentOrchestrationException('agent_memory_retention_invalid');

        return $this->transaction(function () use ($organisationId,$userId,$projectId,$displayName,$purpose,$allowed,$excluded,$retention): array {
            $identityId = Identifier::generate();
            $this->pdo->prepare("INSERT INTO agent_identities(id,organisation_id,user_id,display_name,status,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:name,'active',UNHEX(:created_by)) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),status='active'")->execute(['id'=>$identityId,'o'=>$organisationId,'u'=>$userId,'created_by'=>$userId,'name'=>$displayName]);
            $identity = $this->queryOne("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,display_name displayName,status FROM agent_identities WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)", ['o'=>$organisationId,'u'=>$userId], 'agent_identity_not_found');
            $userContextId = Identifier::generate();
            $this->pdo->prepare("INSERT INTO agent_user_contexts(id,organisation_id,project_id,user_id,agent_identity_id,purpose,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),UNHEX(:agent),:purpose,'active') ON DUPLICATE KEY UPDATE agent_identity_id=VALUES(agent_identity_id),purpose=VALUES(purpose),status='active'")->execute(['id'=>$userContextId,'o'=>$organisationId,'p'=>$projectId,'u'=>$userId,'agent'=>$identity['id'],'purpose'=>$purpose]);
            $projectContextId = Identifier::generate();
            $this->pdo->prepare("INSERT INTO agent_project_contexts(id,organisation_id,project_id,purpose,status,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:purpose,'active',UNHEX(:u)) ON DUPLICATE KEY UPDATE purpose=VALUES(purpose),status='active'")->execute(['id'=>$projectContextId,'o'=>$organisationId,'p'=>$projectId,'purpose'=>$purpose,'u'=>$userId]);
            $memoryId = Identifier::generate();
            $this->pdo->prepare("INSERT INTO agent_memory_boundaries(id,organisation_id,project_id,user_id,scope_key,allowed_scopes,excluded_scopes,retention_until,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),'project_user_context',:allowed,:excluded,:retention,'active') ON DUPLICATE KEY UPDATE allowed_scopes=VALUES(allowed_scopes),excluded_scopes=VALUES(excluded_scopes),retention_until=VALUES(retention_until),status='active'")->execute(['id'=>$memoryId,'o'=>$organisationId,'p'=>$projectId,'u'=>$userId,'allowed'=>$this->json($allowed),'excluded'=>$this->json($excluded),'retention'=>$retention]);
            $userContext = $this->queryOne("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,LOWER(HEX(agent_identity_id)) agentIdentityId,purpose,status FROM agent_user_contexts WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND user_id=UNHEX(:u)", ['o'=>$organisationId,'p'=>$projectId,'u'=>$userId], 'agent_user_context_not_found');
            $projectContext = $this->queryOne("SELECT LOWER(HEX(id)) id,purpose,status FROM agent_project_contexts WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p)", ['o'=>$organisationId,'p'=>$projectId], 'agent_project_context_not_found');
            $memory = $this->memory($organisationId,$userId,$projectId);
            $payload=['userContextId'=>$userContext['id'],'projectContextId'=>$projectContext['id'],'memoryBoundaryId'=>$memory['id'],'permissionBypassAllowed'=>false,'externalActionPerformed'=>false];
            $this->record($organisationId,$projectId,$userId,'agent_context','Agent identity and bounded context','approved',$payload,[$identity['id'],$userContext['id'],$projectContext['id'],$memory['id']]);
            $this->audit($organisationId,$userId,'agent.context_bootstrapped','agent_user_context',$userContext['id'],$payload);
            return ['identity'=>$identity,'userContext'=>$userContext,'projectContext'=>$projectContext,'memoryBoundary'=>$memory,'boundaries'=>$this->boundaries()];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createPolicy(string $organisationId, string $userId, string $role, array $input): array
    {
        $this->governor($organisationId,$userId,$role); $this->confirmed($input);
        $key=$this->key($input['policyKey']??null,'agent_policy_key_invalid');
        $title=$this->text($input['title']??null,220,'agent_policy_title_required');
        $rules=$this->object($input['rules']??null,'agent_policy_rules_invalid');
        if (($rules['allowPermissionBypass']??false)!==false || ($rules['allowExternalDispatch']??false)!==false) throw new AgentOrchestrationException('agent_policy_boundary_invalid');
        return $this->transaction(function() use($organisationId,$userId,$key,$title,$rules):array {
            $versionStatement=$this->pdo->prepare('SELECT COALESCE(MAX(version),0)+1 FROM agent_governance_policies WHERE organisation_id=UNHEX(:o) AND policy_key=:key FOR UPDATE');
            $versionStatement->execute(['o'=>$organisationId,'key'=>$key]);$version=(int)$versionStatement->fetchColumn();
            $this->pdo->prepare("UPDATE agent_governance_policies SET status='superseded' WHERE organisation_id=UNHEX(:o) AND policy_key=:key AND status='active'")->execute(['o'=>$organisationId,'key'=>$key]);
            $id=Identifier::generate();
            $this->pdo->prepare("INSERT INTO agent_governance_policies(id,organisation_id,policy_key,title,version,rules,status,created_by) VALUES(UNHEX(:id),UNHEX(:o),:key,:title,:version,:rules,'active',UNHEX(:u))")->execute(['id'=>$id,'o'=>$organisationId,'key'=>$key,'title'=>$title,'version'=>$version,'rules'=>$this->json($rules),'u'=>$userId]);
            $policy=$this->policy($organisationId,$id);$this->audit($organisationId,$userId,'agent.governance_policy_created','agent_governance_policy',$id,['policyKey'=>$key,'version'=>$version,'rules'=>$rules]);return$policy;
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createRoute(string $organisationId,string $userId,string $role,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$this->governor($organisationId,$userId,$role);$this->confirmed($input);
        $eventType=$this->key($input['eventType']??null,'agent_event_type_invalid');$sourceModule=$this->key($input['sourceModule']??null,'agent_source_module_invalid');$targetRole=$this->key($input['targetRole']??null,'agent_target_role_invalid');$policyId=$this->id($input['policyId']??null,'agent_policy_not_found');$this->policy($organisationId,$policyId);
        $createDraft=$this->boolean($input['createDraft']??null,'agent_create_draft_invalid');$approvalRequired=$this->boolean($input['approvalRequired']??null,'agent_approval_rule_invalid');if($createDraft&&!$approvalRequired)throw new AgentOrchestrationException('agent_formal_draft_approval_required');
        $id=Identifier::generate();$this->pdo->prepare("INSERT INTO agent_event_routes(id,organisation_id,project_id,event_type,source_module,target_role,policy_id,create_draft,approval_required,status,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:event,:module,:role,UNHEX(:policy),:draft,:approval,'active',UNHEX(:u))")->execute(['id'=>$id,'o'=>$organisationId,'p'=>$projectId,'event'=>$eventType,'module'=>$sourceModule,'role'=>$targetRole,'policy'=>$policyId,'draft'=>$createDraft?1:0,'approval'=>$approvalRequired?1:0,'u'=>$userId]);
        $route=$this->route($organisationId,$projectId,$id);$this->audit($organisationId,$userId,'agent.event_route_created','agent_event_route',$id,['eventType'=>$eventType,'policyId'=>$policyId,'createDraft'=>$createDraft,'approvalRequired'=>$approvalRequired]);return$route;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function routeEvent(string $organisationId,string $userId,string $projectId,array $input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$this->confirmed($input);
        $eventType=$this->key($input['eventType']??null,'agent_event_type_invalid');$sourceType=$this->key($input['sourceType']??null,'agent_source_type_invalid');$sourceId=$this->id($input['sourceObjectId']??null,'agent_source_object_invalid');$title=$this->text($input['title']??null,220,'agent_event_title_required');$facts=$this->object($input['facts']??null,'agent_event_facts_invalid');$severity=$this->choice($facts['severity']??'medium',['low','medium','high','critical'],'agent_event_severity_invalid');
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) id FROM agent_event_routes WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND event_type=:event AND status='active' ORDER BY created_at,id LIMIT 1");$statement->execute(['o'=>$organisationId,'p'=>$projectId,'event'=>$eventType]);$routeId=$statement->fetchColumn();if(!is_string($routeId))throw new AgentOrchestrationException('agent_event_route_not_found');$route=$this->route($organisationId,$projectId,$routeId);$policy=$this->policy($organisationId,$route['policyId'],false);
        return$this->transaction(function()use($organisationId,$userId,$projectId,$eventType,$sourceType,$sourceId,$title,$facts,$severity,$route,$policy):array {
            $eventId=Identifier::generate();$this->pdo->prepare("INSERT INTO workflow_events(id,organisation_id,project_id,event_type,priority,source_module,payload) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:event,:priority,:module,:payload)")->execute(['id'=>$eventId,'o'=>$organisationId,'p'=>$projectId,'event'=>$eventType,'priority'=>$severity,'module'=>$route['sourceModule'],'payload'=>$this->json(['sourceType'=>$sourceType,'sourceObjectId'=>$sourceId,'facts'=>$facts,'routeId'=>$route['id'],'policyId'=>$policy['id']])]);
            $recommendationId=Identifier::generate();$rationale='Review the recorded '.$eventType.' facts under policy '.$policy['policyKey'].' v'.$policy['version'].'.';
            $this->pdo->prepare("INSERT INTO agent_recommendations(id,organisation_id,project_id,user_id,route_id,policy_id,source_type,source_object_id,title,rationale,severity,requires_approval,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),UNHEX(:route),UNHEX(:policy),:source_type,UNHEX(:source),:title,:rationale,:severity,:approval,'open')")->execute(['id'=>$recommendationId,'o'=>$organisationId,'p'=>$projectId,'u'=>$userId,'route'=>$route['id'],'policy'=>$policy['id'],'source_type'=>$sourceType,'source'=>$sourceId,'title'=>$title,'rationale'=>$rationale,'severity'=>$severity,'approval'=>$route['approvalRequired']?1:0]);
            $recommendation=$this->recommendation($organisationId,$projectId,$recommendationId);$draft=null;$gate=null;
            if($route['createDraft'])[$draft,$gate]=$this->createDraftInternal($organisationId,$userId,$projectId,$route,$policy,$sourceType,$sourceId,$title,'Draft only: '.$title.'. Source facts: '.$this->json($facts));
            $monitoring=$this->monitor($organisationId,$userId,$projectId,'project_agent',$eventType,'routed',['workflowEventId'=>$eventId,'routeId'=>$route['id'],'policyId'=>$policy['id'],'recommendationId'=>$recommendationId,'messageDraftId'=>$draft['id']??null,'approvalGateId'=>$gate['id']??null]);
            $this->record($organisationId,$projectId,$userId,'agent_recommendation',$title,'pending_review',['recommendationId'=>$recommendationId,'routeId'=>$route['id'],'policyId'=>$policy['id'],'sourceType'=>$sourceType,'sourceObjectId'=>$sourceId,'advisoryOnly'=>true],[$sourceId,$recommendationId,$route['id'],$policy['id']]);
            $this->inbox($organisationId,$projectId,$route['targetRole'],'agent.recommendation_created','Agent recommendation requires human review',$title,$severity,['recommendationId'=>$recommendationId,'sourceObjectId'=>$sourceId,'policyId'=>$policy['id']]);
            $this->audit($organisationId,$userId,'agent.recommendation_created','agent_recommendation',$recommendationId,['routeId'=>$route['id'],'policyId'=>$policy['id'],'sourceObjectId'=>$sourceId]);
            $this->audit($organisationId,$userId,'agent.event_routed','workflow_event',$eventId,['routeId'=>$route['id'],'policyId'=>$policy['id'],'recommendationId'=>$recommendationId]);
            return['workflowEventId'=>$eventId,'routeId'=>$route['id'],'policyId'=>$policy['id'],'recommendation'=>$recommendation,'messageDraft'=>$draft,'approvalGate'=>$gate,'monitoring'=>$monitoring,'boundaries'=>$this->boundaries()];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createMessageDraft(string$organisationId,string$userId,string$projectId,array$input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$this->confirmed($input);$policy=$this->policy($organisationId,$this->id($input['policyId']??null,'agent_policy_not_found'));$sourceType=$this->key($input['sourceType']??null,'agent_source_type_invalid');$sourceId=$this->id($input['sourceObjectId']??null,'agent_source_object_invalid');$subject=$this->text($input['subject']??null,220,'agent_draft_subject_required');$body=$this->text($input['body']??null,10000,'agent_draft_body_required');$role=$this->key($input['recipientRole']??null,'agent_target_role_invalid');$route=['id'=>null,'targetRole'=>$role,'approvalRequired'=>true];
        return$this->transaction(function()use($organisationId,$userId,$projectId,$route,$policy,$sourceType,$sourceId,$subject,$body):array{[$draft,$gate]=$this->createDraftInternal($organisationId,$userId,$projectId,$route,$policy,$sourceType,$sourceId,$subject,$body);return['messageDraft'=>$draft,'approvalGate'=>$gate,'boundaries'=>$this->boundaries()];});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function decideApproval(string$organisationId,string$userId,string$role,string$gateId,array$input):array
    {
        $this->governor($organisationId,$userId,$role);$this->confirmed($input);$decision=$this->choice($input['decision']??null,['approved','rejected'],'agent_approval_decision_invalid');$reason=$this->text($input['reason']??null,1000,'agent_approval_reason_required');$gate=$this->gate($organisationId,$gateId);if($gate['status']!=='pending')throw new AgentOrchestrationException('agent_approval_immutable');if($gate['requestedBy']===$userId)throw new AgentOrchestrationException('agent_self_approval_forbidden');if($gate['objectType']!=='agent_message_draft')throw new AgentOrchestrationException('agent_approval_object_invalid');
        return$this->transaction(function()use($organisationId,$userId,$decision,$reason,$gateId,$gate):array{$draftStatus=$decision==='approved'?'approved_for_human_send':'rejected';$this->pdo->prepare('UPDATE agent_approval_gates SET status=:decision,decided_by=UNHEX(:u),decision_reason=:reason,decided_at=UTC_TIMESTAMP(6) WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)')->execute(['decision'=>$decision,'u'=>$userId,'reason'=>$reason,'o'=>$organisationId,'id'=>$gateId]);$this->pdo->prepare('UPDATE agent_message_drafts SET status=:status,external_dispatch_performed=FALSE WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)')->execute(['status'=>$draftStatus,'o'=>$organisationId,'id'=>$gate['objectId']]);$draft=$this->draft($organisationId,$gate['projectId'],$gate['objectId']);$payload=['approvalGateId'=>$gateId,'messageDraftId'=>$draft['id'],'decision'=>$decision,'externalDispatchPerformed'=>false,'humanSendStillRequired'=>true];$this->record($organisationId,$gate['projectId'],$userId,'agent_approval_decision','Agent draft '.$decision,$decision==='approved'?'approved':'rejected',$payload,[$gateId,$draft['id']]);$this->inbox($organisationId,$gate['projectId'],$gate['requiredRole'],'agent.approval_decided','Agent draft decision recorded','The draft remains unsent and requires an authorised human delivery action.','medium',$payload);$this->audit($organisationId,$userId,'agent.approval_decided','agent_approval_gate',$gateId,$payload);return['gate'=>$this->gate($organisationId,$gateId),'messageDraft'=>$draft,'boundaries'=>$this->boundaries()];});
    }

    /** @return array<string,mixed> */
    public function workspace(string$organisationId,string$userId,string$projectId):array
    {
        $this->assertProject($organisationId,$userId,$projectId);$params=['o'=>$organisationId,'p'=>$projectId];
        return[
            'identities'=>$this->queryAll("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,display_name displayName,status FROM agent_identities WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) ORDER BY created_at",['o'=>$organisationId,'u'=>$userId]),
            'userContexts'=>$this->queryAll("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,LOWER(HEX(agent_identity_id)) agentIdentityId,purpose,status FROM agent_user_contexts WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND user_id=UNHEX(:u) ORDER BY created_at",$params+['u'=>$userId]),
            'projectContexts'=>$this->queryAll("SELECT LOWER(HEX(id)) id,purpose,status FROM agent_project_contexts WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) ORDER BY created_at",$params),
            'policies'=>$this->policies($organisationId),
            'routes'=>$this->queryAll("SELECT LOWER(HEX(id)) id,event_type eventType,source_module sourceModule,target_role targetRole,LOWER(HEX(policy_id)) policyId,create_draft createDraft,approval_required approvalRequired,status FROM agent_event_routes WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) ORDER BY created_at,id",$params,true),
            'recommendations'=>$this->queryAll("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,LOWER(HEX(route_id)) routeId,LOWER(HEX(policy_id)) policyId,source_type sourceType,LOWER(HEX(source_object_id)) sourceObjectId,title,rationale,severity,requires_approval requiresApproval,status FROM agent_recommendations WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) ORDER BY created_at,id",$params,true),
            'messageDrafts'=>$this->queryAll("SELECT LOWER(HEX(id)) id,LOWER(HEX(authored_for_user_id)) authoredForUserId,LOWER(HEX(route_id)) routeId,LOWER(HEX(policy_id)) policyId,source_type sourceType,LOWER(HEX(source_object_id)) sourceObjectId,recipient_role recipientRole,subject,body,formal_instruction formalInstruction,status,external_dispatch_performed externalDispatchPerformed FROM agent_message_drafts WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) ORDER BY created_at,id",$params,true),
            'approvalGates'=>$this->queryAll("SELECT LOWER(HEX(id)) id,object_type objectType,LOWER(HEX(object_id)) objectId,required_role requiredRole,status,LOWER(HEX(requested_by)) requestedBy,LOWER(HEX(decided_by)) decidedBy,decision_reason decisionReason FROM agent_approval_gates WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) ORDER BY created_at,id",$params),
            'memoryBoundaries'=>$this->queryAll("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,scope_key scopeKey,allowed_scopes allowedScopes,excluded_scopes excludedScopes,DATE_FORMAT(retention_until,'%Y-%m-%d') retentionUntil,status FROM agent_memory_boundaries WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND user_id=UNHEX(:u) ORDER BY created_at",$params+['u'=>$userId],false,['allowedScopes','excludedScopes']),
            'monitoring'=>$this->queryAll("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,agent_key agentKey,event_type eventType,outcome,details,permission_bypass permissionBypass,external_action_performed externalActionPerformed FROM agent_monitoring_records WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) ORDER BY created_at,id",$params,true,['details']),
            'boundaries'=>$this->boundaries(),
        ];
    }

    /** @param array<string,mixed> $route @param array<string,mixed> $policy @return array{array<string,mixed>,array<string,mixed>} */
    private function createDraftInternal(string$o,string$u,string$p,array$route,array$policy,string$sourceType,string$sourceId,string$subject,string$body):array
    {
        $draftId=Identifier::generate();$routeId=is_string($route['id']??null)?$route['id']:null;
        $this->pdo->prepare("INSERT INTO agent_message_drafts(id,organisation_id,project_id,authored_for_user_id,route_id,policy_id,source_type,source_object_id,recipient_role,subject,body,formal_instruction,status,external_dispatch_performed) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),UNHEX(:route),UNHEX(:policy),:source_type,UNHEX(:source),:role,:subject,:body,TRUE,'pending_approval',FALSE)")->execute(['id'=>$draftId,'o'=>$o,'p'=>$p,'u'=>$u,'route'=>$routeId,'policy'=>$policy['id'],'source_type'=>$sourceType,'source'=>$sourceId,'role'=>$route['targetRole'],'subject'=>$subject,'body'=>$body]);
        $gateId=Identifier::generate();$this->pdo->prepare("INSERT INTO agent_approval_gates(id,organisation_id,project_id,object_type,object_id,required_role,status,requested_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),'agent_message_draft',UNHEX(:object),:role,'pending',UNHEX(:u))")->execute(['id'=>$gateId,'o'=>$o,'p'=>$p,'object'=>$draftId,'role'=>$route['targetRole'],'u'=>$u]);
        $payload=['messageDraftId'=>$draftId,'approvalGateId'=>$gateId,'policyId'=>$policy['id'],'sourceType'=>$sourceType,'sourceObjectId'=>$sourceId,'externalDispatchPerformed'=>false];$this->record($o,$p,$u,'agent_contextual_message_draft',$subject,'pending_review',$payload,[$draftId,$gateId,$policy['id'],$sourceId]);$this->inbox($o,$p,$route['targetRole'],'agent.approval_required','Agent draft requires human approval',$subject,'high',$payload);$this->audit($o,$u,'agent.message_draft_created','agent_message_draft',$draftId,$payload);return[$this->draft($o,$p,$draftId),$this->gate($o,$gateId)];
    }

    /** @return array<string,mixed> */ private function boundaries():array{return['permissionBypassAllowed'=>false,'externalDispatchAvailable'=>false,'externalDispatchPerformed'=>false,'formalInstructionRequiresHumanApproval'=>true,'projectAgentOverridesRolePermissions'=>false,'systemGovernanceSeparateFromRecommendations'=>true,'crossUserMemoryVisible'=>false];}
    /** @return array<string,mixed> */ private function policy(string$o,string$id,bool$activeOnly=true):array{$row=$this->queryOne("SELECT LOWER(HEX(id)) id,policy_key policyKey,title,version,rules,status FROM agent_governance_policies WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)".($activeOnly?" AND status='active'":" AND status<>'withdrawn'"),['o'=>$o,'id'=>$id],'agent_policy_not_found');$row['rules']=$this->decode($row['rules']);return$row;}
    /** @return list<array<string,mixed>> */ private function policies(string$o):array{return$this->queryAll("SELECT LOWER(HEX(id)) id,policy_key policyKey,title,version,rules,status FROM agent_governance_policies WHERE organisation_id=UNHEX(:o) ORDER BY policy_key,version",['o'=>$o],false,['rules']);}
    /** @return array<string,mixed> */ private function route(string$o,string$p,string$id):array{return$this->queryOne("SELECT LOWER(HEX(id)) id,event_type eventType,source_module sourceModule,target_role targetRole,LOWER(HEX(policy_id)) policyId,create_draft createDraft,approval_required approvalRequired,status FROM agent_event_routes WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND id=UNHEX(:id) AND status='active'",['o'=>$o,'p'=>$p,'id'=>$id],'agent_event_route_not_found',true);}
    /** @return array<string,mixed> */ private function recommendation(string$o,string$p,string$id):array{return$this->queryOne("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,LOWER(HEX(route_id)) routeId,LOWER(HEX(policy_id)) policyId,source_type sourceType,LOWER(HEX(source_object_id)) sourceObjectId,title,rationale,severity,requires_approval requiresApproval,status FROM agent_recommendations WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND id=UNHEX(:id)",['o'=>$o,'p'=>$p,'id'=>$id],'agent_recommendation_not_found',true);}
    /** @return array<string,mixed> */ private function draft(string$o,string$p,string$id):array{return$this->queryOne("SELECT LOWER(HEX(id)) id,LOWER(HEX(authored_for_user_id)) authoredForUserId,LOWER(HEX(route_id)) routeId,LOWER(HEX(policy_id)) policyId,source_type sourceType,LOWER(HEX(source_object_id)) sourceObjectId,recipient_role recipientRole,subject,body,formal_instruction formalInstruction,status,external_dispatch_performed externalDispatchPerformed FROM agent_message_drafts WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND id=UNHEX(:id)",['o'=>$o,'p'=>$p,'id'=>$id],'agent_message_draft_not_found',true);}
    /** @return array<string,mixed> */ private function gate(string$o,string$id):array{return$this->queryOne("SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) projectId,object_type objectType,LOWER(HEX(object_id)) objectId,required_role requiredRole,status,LOWER(HEX(requested_by)) requestedBy,LOWER(HEX(decided_by)) decidedBy,decision_reason decisionReason FROM agent_approval_gates WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)",['o'=>$o,'id'=>$id],'agent_approval_gate_not_found');}
    /** @return array<string,mixed> */ private function memory(string$o,string$u,string$p):array{$row=$this->queryOne("SELECT LOWER(HEX(id)) id,LOWER(HEX(user_id)) userId,scope_key scopeKey,allowed_scopes allowedScopes,excluded_scopes excludedScopes,DATE_FORMAT(retention_until,'%Y-%m-%d') retentionUntil,status FROM agent_memory_boundaries WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND user_id=UNHEX(:u)",['o'=>$o,'p'=>$p,'u'=>$u],'agent_memory_boundary_not_found');$row['allowedScopes']=$this->decode($row['allowedScopes']);$row['excludedScopes']=$this->decode($row['excludedScopes']);return$row;}
    /** @param array<string,mixed> $details @return array<string,mixed> */ private function monitor(string$o,string$u,string$p,string$key,string$event,string$outcome,array$details):array{$id=Identifier::generate();$this->pdo->prepare("INSERT INTO agent_monitoring_records(id,organisation_id,project_id,user_id,agent_key,event_type,outcome,details,permission_bypass,external_action_performed) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:u),:key,:event,:outcome,:details,FALSE,FALSE)")->execute(['id'=>$id,'o'=>$o,'p'=>$p,'u'=>$u,'key'=>$key,'event'=>$event,'outcome'=>$outcome,'details'=>$this->json($details)]);return['id'=>$id,'userId'=>$u,'agentKey'=>$key,'eventType'=>$event,'outcome'=>$outcome,'details'=>$details,'permissionBypass'=>false,'externalActionPerformed'=>false];}
    /** @param array<string,mixed> $payload @param list<string> $links */ private function record(string$o,string$p,string$u,string$type,string$title,string$status,array$payload,array$links):void{$phase=$this->projectPhase($o,$p);$this->pdo->prepare("INSERT INTO project_records(id,organisation_id,project_id,phase,module_key,record_type,title,status,payload,approvals,linked_record_ids,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:phase,:module,:type,:title,:status,:payload,:approvals,:links,UNHEX(:u))")->execute(['id'=>Identifier::generate(),'o'=>$o,'p'=>$p,'phase'=>$phase,'module'=>self::MODULE,'type'=>$type,'title'=>$title,'status'=>$status,'payload'=>$this->json($payload),'approvals'=>$this->json(['humanReviewRequired'=>true]),'links'=>$this->json($links),'u'=>$u]);}
    /** @param array<string,mixed> $payload */ private function inbox(string$o,string$p,string$role,string$event,string$title,string$detail,string$priority,array$payload):void{$this->pdo->prepare("INSERT INTO inbox_items(id,organisation_id,project_id,assigned_role,event_type,title,detail,priority,payload) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:role,:event,:title,:detail,:priority,:payload)")->execute(['id'=>Identifier::generate(),'o'=>$o,'p'=>$p,'role'=>$role,'event'=>$event,'title'=>$title,'detail'=>$detail,'priority'=>$priority,'payload'=>$this->json($payload)]);}
    /** @param array<string,mixed> $metadata */ private function audit(string$o,string$u,string$action,string$type,string$id,array$metadata):void{(new AuditService($this->pdo))->append($o,$u,$action,$type,$id,$metadata);}
    private function assertProject(string$o,string$u,string$p):void{$s=$this->pdo->prepare("SELECT 1 FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:u) AND m.status='active' WHERE p.organisation_id=UNHEX(:o) AND p.id=UNHEX(:p)");$s->execute(['o'=>$o,'u'=>$u,'p'=>$p]);if($s->fetchColumn()===false)throw new AgentOrchestrationException('agent_project_not_found');}
    private function governor(string$o,string$u,string$role):void{if(!in_array($role,['owner','admin'],true))throw new AgentOrchestrationException('agent_approval_permission_denied');$s=$this->pdo->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active' AND role IN ('owner','admin')");$s->execute(['o'=>$o,'u'=>$u]);if($s->fetchColumn()===false)throw new AgentOrchestrationException('agent_approval_permission_denied');}
    private function projectPhase(string$o,string$p):string{$s=$this->pdo->prepare('SELECT current_phase FROM projects WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:p)');$s->execute(['o'=>$o,'p'=>$p]);$phase=$s->fetchColumn();return is_string($phase)?$phase:'onboarding';}
    /** @param array<string,mixed> $params @return array<string,mixed> */ private function queryOne(string$sql,array$params,string$error,bool$booleans=false):array{$s=$this->pdo->prepare($sql);$s->execute($params);$row=$s->fetch();if(!is_array($row))throw new AgentOrchestrationException($error);return$booleans?$this->booleans($row):$row;}
    /** @param array<string,mixed> $params @param list<string> $json @return list<array<string,mixed>> */ private function queryAll(string$sql,array$params,bool$booleans=false,array$json=[]):array{$s=$this->pdo->prepare($sql);$s->execute($params);$rows=[];foreach($s->fetchAll()as$row){foreach($json as$key)$row[$key]=$this->decode($row[$key]??null);$rows[]=$booleans?$this->booleans($row):$row;}return$rows;}
    /** @param array<string,mixed> $row @return array<string,mixed> */ private function booleans(array$row):array{foreach(['createDraft','approvalRequired','requiresApproval','formalInstruction','externalDispatchPerformed','permissionBypass','externalActionPerformed']as$key)if(array_key_exists($key,$row))$row[$key]=(bool)$row[$key];return$row;}
    /** @template T @param callable():T $callback @return T */ private function transaction(callable$callback):mixed{$this->pdo->beginTransaction();try{$value=$callback();$this->pdo->commit();return$value;}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
    /** @param array<string,mixed> $input */ private function confirmed(array$input):void{if(($input['humanConfirmed']??false)!==true)throw new AgentOrchestrationException('agent_human_confirmation_required');}
    private function text(mixed$v,int$max,string$error):string{if(!is_string($v)||trim($v)===''||mb_strlen(trim($v))>$max)throw new AgentOrchestrationException($error);return trim($v);}
    private function key(mixed$v,string$error):string{if(!is_string($v)||preg_match('/\A[a-z][a-z0-9_.-]{1,99}\z/',$v)!==1)throw new AgentOrchestrationException($error);return$v;}
    private function id(mixed$v,string$error):string{if(!is_string($v)||preg_match('/\A[a-f0-9]{32}\z/i',$v)!==1)throw new AgentOrchestrationException($error);return strtolower($v);}
    private function boolean(mixed$v,string$error):bool{if(!is_bool($v))throw new AgentOrchestrationException($error);return$v;}
    /** @param list<string> $choices */ private function choice(mixed$v,array$choices,string$error):string{if(!is_string($v)||!in_array($v,$choices,true))throw new AgentOrchestrationException($error);return$v;}
    private function date(mixed$v,string$error):string{if(!is_string($v)||DateTimeImmutable::createFromFormat('!Y-m-d',$v)?->format('Y-m-d')!==$v)throw new AgentOrchestrationException($error);return$v;}
    /** @return list<string> */ private function strings(mixed$v,int$max,string$error):array{if(!is_array($v)||$v===[]||count($v)>$max)throw new AgentOrchestrationException($error);$out=[];foreach($v as$item){if(!is_string($item)||preg_match('/\A[a-z][a-z0-9_.-]{1,99}\z/',$item)!==1)throw new AgentOrchestrationException($error);$out[]=$item;}return array_values(array_unique($out));}
    /** @return array<string,mixed> */ private function object(mixed$v,string$error):array{if(!is_array($v)||array_is_list($v)||count($v)>100)throw new AgentOrchestrationException($error);return$v;}
    private function json(mixed$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
    /** @return array<mixed> */ private function decode(mixed$v):array{if(!is_string($v))return[];$decoded=json_decode($v,true,512,JSON_THROW_ON_ERROR);return is_array($decoded)?$decoded:[];}
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Agent;

use Architex\Platform\Agent\AgentOrchestrationException;
use Architex\Platform\Agent\AgentOrchestrationService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class AgentOrchestrationServiceTest extends MariaDbTestCase
{
    public function testRoutesTraceableAdviceThroughASeparateHumanApprovalGate(): void
    {
        [$organisation, $owner, $project] = $this->tenant('agent-core');
        $member = $this->member($organisation, 'agent-member@example.test', 'member');
        $service = new AgentOrchestrationService($this->pdo);

        $context = $service->bootstrap($organisation, $member, $project, [
            'displayName' => 'Project Wingman',
            'purpose' => 'Coordinate project risks without replacing professional decisions.',
            'memoryScope' => ['project_phase', 'assigned_actions', 'approved_records'],
            'memoryExclusions' => ['other_users_private_context', 'other_tenants'],
            'retentionUntil' => '2031-08-29',
            'humanConfirmed' => true,
        ]);
        self::assertSame($member, $context['userContext']['userId']);

        $policy = $service->createPolicy($organisation, $owner, 'owner', [
            'policyKey' => 'formal_instruction_review',
            'title' => 'Formal instruction review',
            'rules' => ['requireApproval' => true, 'allowPermissionBypass' => false, 'allowExternalDispatch' => false],
            'humanConfirmed' => true,
        ]);
        $route = $service->createRoute($organisation, $owner, 'owner', $project, [
            'eventType' => 'site.blocker_detected',
            'sourceModule' => 'site_execution_field_control',
            'targetRole' => 'owner',
            'policyId' => $policy['id'],
            'createDraft' => true,
            'approvalRequired' => true,
            'humanConfirmed' => true,
        ]);
        $policyV2 = $service->createPolicy($organisation, $owner, 'owner', [
            'policyKey' => 'formal_instruction_review',
            'title' => 'Formal instruction review updated',
            'rules' => ['requireApproval' => true, 'allowPermissionBypass' => false, 'allowExternalDispatch' => false],
            'humanConfirmed' => true,
        ]);
        self::assertSame(2, $policyV2['version']);

        $sourceId = Identifier::generate();
        $result = $service->routeEvent($organisation, $member, $project, [
            'eventType' => 'site.blocker_detected',
            'sourceType' => 'site_payment_blocker',
            'sourceObjectId' => $sourceId,
            'title' => 'Review unresolved inspection blocker',
            'facts' => ['severity' => 'high', 'instructionStatus' => 'not_issued'],
            'humanConfirmed' => true,
        ]);
        self::assertSame($route['id'], $result['routeId']);
        self::assertSame($policy['id'], $result['recommendation']['policyId']);
        self::assertSame($sourceId, $result['recommendation']['sourceObjectId']);
        self::assertSame('pending_approval', $result['messageDraft']['status']);
        self::assertTrue($result['messageDraft']['formalInstruction']);
        self::assertSame('pending', $result['approvalGate']['status']);
        self::assertFalse($result['monitoring']['permissionBypass']);
        self::assertFalse($result['monitoring']['externalActionPerformed']);

        try {
            $service->decideApproval($organisation, $member, 'member', $result['approvalGate']['id'], [
                'decision' => 'approved', 'reason' => 'Self approval attempt.', 'humanConfirmed' => true,
            ]);
            self::fail('The requesting user must not approve their own agent draft.');
        } catch (AgentOrchestrationException $exception) {
            self::assertSame('agent_approval_permission_denied', $exception->reason);
        }

        $decision = $service->decideApproval($organisation, $owner, 'owner', $result['approvalGate']['id'], [
            'decision' => 'approved',
            'reason' => 'Reviewed the source facts; release only as an approved human draft.',
            'humanConfirmed' => true,
        ]);
        self::assertSame('approved', $decision['gate']['status']);
        self::assertSame('approved_for_human_send', $decision['messageDraft']['status']);
        self::assertFalse($decision['messageDraft']['externalDispatchPerformed']);

        try {
            $service->decideApproval($organisation, $owner, 'owner', $result['approvalGate']['id'], [
                'decision' => 'rejected', 'reason' => 'Mutate terminal decision.', 'humanConfirmed' => true,
            ]);
            self::fail('Approval decisions must be immutable.');
        } catch (AgentOrchestrationException $exception) {
            self::assertSame('agent_approval_immutable', $exception->reason);
        }

        $service->bootstrap($organisation, $owner, $project, [
            'displayName' => 'Owner Wingman', 'purpose' => 'Owner-only context.',
            'memoryScope' => ['governance'], 'memoryExclusions' => ['other_users_private_context'],
            'retentionUntil' => '2031-08-29', 'humanConfirmed' => true,
        ]);
        $workspace = $service->workspace($organisation, $member, $project);
        self::assertCount(1, $workspace['userContexts']);
        self::assertSame($member, $workspace['userContexts'][0]['userId']);
        self::assertCount(1, $workspace['memoryBoundaries']);
        self::assertSame($member, $workspace['memoryBoundaries'][0]['userId']);
        self::assertFalse($workspace['boundaries']['permissionBypassAllowed']);
        self::assertFalse($workspace['boundaries']['externalDispatchAvailable']);
        self::assertGreaterThanOrEqual(3, (int) $this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='agent_orchestration_core'")->fetchColumn());
        self::assertGreaterThanOrEqual(2, (int) $this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type LIKE 'agent.%'")->fetchColumn());
        self::assertGreaterThanOrEqual(7, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'agent.%'")->fetchColumn());
    }

    public function testWorkspaceAndRoutesAreTenantScoped(): void
    {
        [$organisation, $owner] = $this->tenant('agent-scope-a');
        [, , $otherProject] = $this->tenant('agent-scope-b');
        try {
            (new AgentOrchestrationService($this->pdo))->workspace($organisation, $owner, $otherProject);
            self::fail('Another tenant project must remain hidden.');
        } catch (AgentOrchestrationException $exception) {
            self::assertSame('agent_project_not_found', $exception->reason);
        }
    }

    /** @return array{string,string,string} */
    private function tenant(string $slug): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$slug,'slug'=>$slug.'-'.substr($organisation,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),'AGENT','Agent Project','Client','City','Erf 1','Office','','architect','design_development',UNHEX(:u))")->execute(['p'=>$project,'o'=>$organisation,'u'=>$user]);
        return [$organisation,$user,$project];
    }

    private function member(string $organisation, string $email, string $role): string
    {
        $user=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$email]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user,'role'=>$role]);
        return $user;
    }
}

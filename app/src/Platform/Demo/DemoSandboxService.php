<?php

declare(strict_types=1);

namespace Architex\Platform\Demo;

use Closure;
use PDO;
use PDOException;
use Throwable;
use Architex\Platform\Identity\Identifier;

final class DemoSandboxService
{
    private readonly DemoSeedCatalog $catalog;
    private readonly DemoRoleLensRegistry $roles;

    public function __construct(
        private readonly PDO $pdo,
        ?DemoSeedCatalog $catalog = null,
        ?DemoRoleLensRegistry $roles = null,
        private readonly ?Closure $seedHook = null,
    ) {
        $this->catalog = $catalog ?? new DemoSeedCatalog();
        $this->roles = $roles ?? new DemoRoleLensRegistry();
    }

    /** @return array<string,mixed> */
    public function ensure(string $organisationId, string $userId, string $membershipRole): array
    {
        $this->assertMembership($organisationId, $userId, $membershipRole);
        $active = $this->activeSandboxForUser($userId);
        if ($active !== null) {
            if ($active['organisationId'] !== $organisationId) {
                throw new DemoException('demo_sandbox_not_found');
            }
            return $this->state($organisationId, $userId);
        }

        try {
            $this->pdo->beginTransaction();
            $generation = $this->nextGeneration($userId);
            $sandboxId = $this->insertSandbox($organisationId, $userId, $generation);
            $this->seed($sandboxId, $organisationId, $userId, $membershipRole);
            $this->event($sandboxId, $organisationId, $userId, 'ensured', ['generation'=>$generation]);
            $this->event($sandboxId, $organisationId, $userId, 'seeded', ['seedVersion'=>$this->catalog->version(),'seedHash'=>$this->catalog->hash()]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $active = $this->activeSandboxForUser($userId);
                if ($active !== null && $active['organisationId'] === $organisationId) {
                    return $this->state($organisationId, $userId);
                }
            }
            throw $exception;
        }

        return $this->state($organisationId, $userId);
    }

    /** @return array<string,mixed> */
    public function state(string $organisationId, string $userId): array
    {
        $this->assertMembership($organisationId, $userId);
        $sandbox = $this->sandbox($organisationId, $userId);
        $counts = [];
        foreach (['personas'=>'demo_personas','items'=>'demo_seed_items'] as $key => $table) {
            $statement = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE sandbox_id=UNHEX(:sandbox) AND organisation_id=UNHEX(:organisation)");
            $statement->execute(['sandbox'=>$sandbox['sandboxId'],'organisation'=>$organisationId]);
            $counts[$key] = (int) $statement->fetchColumn();
        }
        $statement = $this->pdo->prepare('SELECT COUNT(DISTINCT project_id) FROM demo_project_role_assignments WHERE sandbox_id=UNHEX(:sandbox) AND organisation_id=UNHEX(:organisation)');
        $statement->execute(['sandbox'=>$sandbox['sandboxId'],'organisation'=>$organisationId]);
        $counts['projects'] = (int) $statement->fetchColumn();

        return $sandbox + ['demoMode'=>true,'counts'=>$counts,'relianceLabels'=>['illustrative_demo_not_for_reliance','not_accredited_demo_record']];
    }

    /** @return array<string,mixed> */
    public function selectRole(string $organisationId, string $userId, string $role): array
    {
        $this->assertMembership($organisationId, $userId);
        $lens = $this->roles->get($role);
        $sandbox = $this->sandbox($organisationId, $userId);
        $statement = $this->pdo->prepare("UPDATE demo_sandboxes SET selected_role=:role WHERE id=UNHEX(:sandbox) AND organisation_id=UNHEX(:organisation) AND owner_user_id=UNHEX(:owner) AND status='active'");
        $statement->execute(['role'=>$role,'sandbox'=>$sandbox['sandboxId'],'organisation'=>$organisationId,'owner'=>$userId]);
        if ($statement->rowCount() > 0) {
            $this->event($sandbox['sandboxId'], $organisationId, $userId, 'role_changed', ['role'=>$role]);
        }

        return $this->state($organisationId, $userId) + ['roleLens'=>$lens];
    }

    /** @return list<array<string,mixed>> */
    public function projects(string $organisationId, string $userId): array
    {
        $this->assertMembership($organisationId, $userId);
        $sandbox = $this->sandbox($organisationId, $userId);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(p.id)) id,p.project_code projectCode,p.project_name projectName,p.client_name clientName,p.municipality,p.property_reference propertyReference,p.property_use propertyUse,p.current_phase currentPhase,p.version FROM demo_project_role_assignments a JOIN projects p ON p.id=a.project_id AND p.organisation_id=a.organisation_id WHERE a.sandbox_id=UNHEX(:sandbox) AND a.organisation_id=UNHEX(:organisation) AND a.role_key=:role ORDER BY p.project_code");
        $statement->execute(['sandbox'=>$sandbox['sandboxId'],'organisation'=>$organisationId,'role'=>$sandbox['selectedRole']]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed> */
    public function project(string $organisationId, string $userId, string $projectId): array
    {
        $this->assertMembership($organisationId, $userId);
        $sandbox = $this->sandbox($organisationId, $userId);
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(p.id)) id,p.project_code projectCode,p.project_name projectName,p.client_name clientName,p.municipality,p.property_reference propertyReference,p.property_use propertyUse,p.land_use_notes landUseNotes,p.lead_professional_role leadProfessionalRole,p.current_phase currentPhase,p.version,(SELECT COUNT(*) FROM project_records r WHERE r.organisation_id=p.organisation_id AND r.project_id=p.id) recordCount FROM demo_project_role_assignments a JOIN projects p ON p.id=a.project_id AND p.organisation_id=a.organisation_id WHERE a.sandbox_id=UNHEX(:sandbox) AND a.organisation_id=UNHEX(:organisation) AND a.role_key=:role AND p.id=UNHEX(:project)");
        $statement->execute(['sandbox'=>$sandbox['sandboxId'],'organisation'=>$organisationId,'role'=>$sandbox['selectedRole'],'project'=>$projectId]);
        $project = $statement->fetch();
        if (!is_array($project)) {
            throw new DemoException('demo_project_not_visible');
        }
        $project['recordCount'] = (int) $project['recordCount'];
        return $project;
    }

    /** @return array<string,mixed> */
    public function learning(string $organisationId, string $userId): array
    {
        $this->assertMembership($organisationId, $userId);
        $sandbox = $this->sandbox($organisationId, $userId);
        $statement = $this->pdo->prepare("SELECT item_type,COUNT(*) item_count FROM demo_seed_items WHERE sandbox_id=UNHEX(:sandbox) AND organisation_id=UNHEX(:organisation) AND item_type LIKE 'learning_%' GROUP BY item_type");
        $statement->execute(['sandbox'=>$sandbox['sandboxId'],'organisation'=>$organisationId]);
        $raw = array_column($statement->fetchAll(), 'item_count', 'item_type');
        return ['demoMode'=>true,'sandboxId'=>$sandbox['sandboxId'],'generation'=>$sandbox['generation'],'counts'=>['articles'=>(int)($raw['learning_article']??0),'assessments'=>(int)($raw['learning_assessment']??0),'modules'=>(int)($raw['learning_module']??0),'certificates'=>(int)($raw['learning_certificate']??0)],'relianceLabel'=>'not_accredited_demo_record'];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function reset(string $organisationId, string $userId, string $membershipRole, array $input): array
    {
        if (($input['confirmation'] ?? null) !== 'RESET DEMO SANDBOX') {
            throw new DemoException('demo_reset_confirmation_required');
        }
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw new DemoException('demo_reset_reason_required');
        }
        if ($membershipRole !== 'owner' || $this->assertMembership($organisationId, $userId, $membershipRole) !== 'owner') {
            throw new DemoException('demo_reset_owner_required');
        }

        try {
            $this->pdo->beginTransaction();
            $old = $this->lockedSandbox($organisationId, $userId);
            $generation = $old['generation'] + 1;
            $this->pdo->prepare("UPDATE demo_sandboxes SET status='superseded',reset_reason=:reason,superseded_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:sandbox) AND status='active'")->execute(['reason'=>$reason,'sandbox'=>$old['sandboxId']]);
            $newOrganisation = Identifier::generate();
            $slug = 'demo-' . substr($userId, 0, 8) . '-g' . $generation . '-' . substr($newOrganisation, 0, 8);
            $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$newOrganisation,'name'=>'Demo Sandbox Generation '.$generation,'slug'=>$slug]);
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$newOrganisation,'user'=>$userId]);
            $newSandbox = $this->insertSandbox($newOrganisation, $userId, $generation);
            $this->seed($newSandbox, $newOrganisation, $userId, 'owner');
            $this->event($old['sandboxId'], $organisationId, $userId, 'reset_requested', ['reason'=>$reason,'replacementGeneration'=>$generation]);
            $this->event($old['sandboxId'], $organisationId, $userId, 'superseded', ['replacementSandboxId'=>$newSandbox]);
            $this->event($newSandbox, $newOrganisation, $userId, 'reset_completed', ['previousSandboxId'=>$old['sandboxId'],'generation'=>$generation]);
            $this->pdo->prepare("UPDATE organisation_memberships SET status='revoked' WHERE organisation_id=UNHEX(:organisation) AND user_id=UNHEX(:user) AND status='active'")->execute(['organisation'=>$organisationId,'user'=>$userId]);
            $this->pdo->prepare("UPDATE organisations SET status='closed' WHERE id=UNHEX(:organisation) AND status='active'")->execute(['organisation'=>$organisationId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $this->state($newOrganisation, $userId);
    }

    private function seed(string $sandboxId, string $organisationId, string $userId, string $membershipRole): void
    {
        if ($this->seedHook !== null) {
            ($this->seedHook)();
        }
        $personaInsert = $this->pdo->prepare('INSERT INTO demo_personas(id,sandbox_id,organisation_id,role_key,label,group_key,canonical_role,synthetic_email,canonical_payload,payload_sha256) VALUES(UNHEX(:id),UNHEX(:sandbox),UNHEX(:organisation),:role,:label,:group_key,:canonical_role,:email,:payload,:sha)');
        foreach ($this->catalog->personas() as $persona) {
            $payload = $this->json($persona);
            $personaInsert->execute(['id'=>Identifier::generate(),'sandbox'=>$sandboxId,'organisation'=>$organisationId,'role'=>$persona['roleKey'],'label'=>$persona['label'],'group_key'=>$persona['group'],'canonical_role'=>$persona['canonicalRole'],'email'=>$persona['email'],'payload'=>$payload,'sha'=>hash('sha256',$payload)]);
        }

        $projectIds = [];
        $projectInsert = $this->pdo->prepare('INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by,created_at) VALUES(UNHEX(:id),UNHEX(:organisation),:code,:name,:client,:municipality,:reference,:property_use,:notes,:lead_role,:phase,UNHEX(:user),:created_at)');
        foreach ($this->catalog->projects() as $project) {
            $projectId = Identifier::generate();
            $projectIds[$project['sourceKey']] = $projectId;
            $projectInsert->execute(['id'=>$projectId,'organisation'=>$organisationId,'code'=>$project['projectCode'],'name'=>$project['projectName'],'client'=>$project['clientName'],'municipality'=>$project['municipality'],'reference'=>$project['propertyReference'],'property_use'=>$project['propertyUse'],'notes'=>$project['landUseNotes'],'lead_role'=>$project['leadProfessionalRole'],'phase'=>$project['currentPhase'],'user'=>$userId,'created_at'=>str_replace('T',' ',rtrim($project['createdAt'],'Z'))]);
            $payload = $this->json(['sourceKey'=>$project['sourceKey'],'budgetCents'=>$project['budgetCents'],'targetDate'=>$project['targetDate'],'relianceLabel'=>$project['relianceLabel']]);
            $this->pdo->prepare("INSERT INTO project_records(id,organisation_id,project_id,phase,module_key,record_type,title,status,payload,approvals,linked_record_ids,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:phase,'demo','demo_seed_summary',:title,'draft',:payload,JSON_OBJECT('authoritative',false),JSON_ARRAY(),UNHEX(:user))")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'project'=>$projectId,'phase'=>$project['currentPhase'],'title'=>$project['projectName'].' synthetic demo baseline','payload'=>$payload,'user'=>$userId]);
            $this->pdo->prepare("INSERT INTO workflow_events(id,organisation_id,project_id,event_type,priority,source_module,payload,occurred_at) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),'demo.seeded','low','demo',:payload,:occurred_at)")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'project'=>$projectId,'payload'=>$payload,'occurred_at'=>str_replace('T',' ',rtrim($project['createdAt'],'Z'))]);
            $this->pdo->prepare("INSERT INTO inbox_items(id,organisation_id,project_id,assigned_user_id,assigned_role,event_type,title,detail,priority,payload,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:user),:role,'demo.orientation','Synthetic demo project ready','Illustrative data only; no professional or statutory reliance.','low',:payload,'open')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'project'=>$projectId,'user'=>$userId,'role'=>$membershipRole,'payload'=>$payload]);
        }

        $assignmentInsert = $this->pdo->prepare('INSERT INTO demo_project_role_assignments(sandbox_id,organisation_id,project_id,role_key) VALUES(UNHEX(:sandbox),UNHEX(:organisation),UNHEX(:project),:role)');
        foreach ($this->roles->all() as $role) {
            foreach ($role['projectKeys'] as $projectKey) {
                $assignmentInsert->execute(['sandbox'=>$sandboxId,'organisation'=>$organisationId,'project'=>$projectIds[$projectKey],'role'=>$role['key']]);
            }
        }

        $itemInsert = $this->pdo->prepare('INSERT INTO demo_seed_items(id,sandbox_id,organisation_id,project_id,item_type,source_key,reliance_label,canonical_payload,payload_sha256,created_at) VALUES(UNHEX(:id),UNHEX(:sandbox),UNHEX(:organisation),UNHEX(:project),:type,:source_key,:reliance,:payload,:sha,:created_at)');
        foreach ($this->catalog->items() as $item) {
            $payload = $this->json($item['payload']);
            $itemInsert->execute(['id'=>Identifier::generate(),'sandbox'=>$sandboxId,'organisation'=>$organisationId,'project'=>$item['projectKey'] === null ? null : $projectIds[$item['projectKey']],'type'=>$item['itemType'],'source_key'=>$item['sourceKey'],'reliance'=>$item['relianceLabel'],'payload'=>$payload,'sha'=>hash('sha256',$payload),'created_at'=>str_replace('T',' ',rtrim($item['occurredAt'],'Z'))]);
        }
    }

    private function insertSandbox(string $organisationId, string $userId, int $generation): string
    {
        $sandbox = Identifier::generate();
        $this->pdo->prepare("INSERT INTO demo_sandboxes(id,owner_user_id,organisation_id,generation,selected_role,seed_version,seed_hash,status) VALUES(UNHEX(:id),UNHEX(:owner),UNHEX(:organisation),:generation,'architect',:version,:hash,'active')")->execute(['id'=>$sandbox,'owner'=>$userId,'organisation'=>$organisationId,'generation'=>$generation,'version'=>$this->catalog->version(),'hash'=>$this->catalog->hash()]);
        return $sandbox;
    }

    private function nextGeneration(string $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(generation),0)+1 FROM demo_sandboxes WHERE owner_user_id=UNHEX(:owner)');
        $statement->execute(['owner'=>$userId]);
        return (int) $statement->fetchColumn();
    }

    /** @return array{sandboxId:string,organisationId:string,generation:int,selectedRole:string,seedVersion:string,seedHash:string}|null */
    private function activeSandboxForUser(string $userId): ?array
    {
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) sandboxId,LOWER(HEX(organisation_id)) organisationId,generation,selected_role selectedRole,seed_version seedVersion,seed_hash seedHash FROM demo_sandboxes WHERE owner_user_id=UNHEX(:owner) AND status='active'");
        $statement->execute(['owner'=>$userId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        $row['generation'] = (int) $row['generation'];
        return $row;
    }

    /** @return array{sandboxId:string,organisationId:string,generation:int,selectedRole:string,seedVersion:string,seedHash:string} */
    private function sandbox(string $organisationId, string $userId): array
    {
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) sandboxId,LOWER(HEX(organisation_id)) organisationId,generation,selected_role selectedRole,seed_version seedVersion,seed_hash seedHash FROM demo_sandboxes WHERE organisation_id=UNHEX(:organisation) AND owner_user_id=UNHEX(:owner) AND status='active'");
        $statement->execute(['organisation'=>$organisationId,'owner'=>$userId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new DemoException('demo_sandbox_not_found');
        }
        $row['generation'] = (int) $row['generation'];
        return $row;
    }

    /** @return array{sandboxId:string,generation:int} */
    private function lockedSandbox(string $organisationId, string $userId): array
    {
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) sandboxId,generation FROM demo_sandboxes WHERE organisation_id=UNHEX(:organisation) AND owner_user_id=UNHEX(:owner) AND status='active' FOR UPDATE");
        $statement->execute(['organisation'=>$organisationId,'owner'=>$userId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new DemoException('demo_sandbox_not_found');
        }
        return ['sandboxId'=>$row['sandboxId'],'generation'=>(int)$row['generation']];
    }

    private function assertMembership(string $organisationId, string $userId, ?string $expectedRole = null): string
    {
        $statement = $this->pdo->prepare("SELECT m.role FROM organisation_memberships m JOIN organisations o ON o.id=m.organisation_id AND o.status='active' JOIN users u ON u.id=m.user_id AND u.status='active' WHERE m.organisation_id=UNHEX(:organisation) AND m.user_id=UNHEX(:user) AND m.status='active'");
        $statement->execute(['organisation'=>$organisationId,'user'=>$userId]);
        $role = $statement->fetchColumn();
        if (!is_string($role) || ($expectedRole !== null && $role !== $expectedRole)) {
            throw new DemoException('demo_membership_required');
        }
        return $role;
    }

    /** @param array<string,mixed> $payload */
    private function event(string $sandboxId, string $organisationId, string $userId, string $type, array $payload): void
    {
        $json = $this->json($payload);
        $this->pdo->prepare('INSERT INTO demo_sandbox_events(id,sandbox_id,organisation_id,actor_user_id,event_type,canonical_payload,payload_sha256) VALUES(UNHEX(:id),UNHEX(:sandbox),UNHEX(:organisation),UNHEX(:actor),:type,:payload,:sha)')->execute(['id'=>Identifier::generate(),'sandbox'=>$sandboxId,'organisation'=>$organisationId,'actor'=>$userId,'type'=>$type,'payload'=>$json,'sha'=>hash('sha256',$json)]);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

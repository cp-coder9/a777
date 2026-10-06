<?php
declare(strict_types=1);
namespace Architex\Platform\Project;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use PDOException;
use Throwable;

final readonly class ProjectPassportService
{
    public function __construct(private PDO $pdo) {}

    public function update(ProjectContext $context, UpdateProject $command): ProjectPassport
    {
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE projects SET project_code=:code,project_name=:name,client_name=:client,municipality=:municipality,property_reference=:reference,property_use=:property_use,land_use_notes=:notes,lead_professional_role=:lead_role,version=version+1 WHERE id=UNHEX(:project_id) AND organisation_id=UNHEX(:organisation_id) AND version=:expected_version');
            $update->execute(['code'=>$command->projectCode,'name'=>$command->projectName,'client'=>$command->clientName,'municipality'=>$command->municipality,'reference'=>$command->propertyReference,'property_use'=>$command->propertyUse,'notes'=>$command->landUseNotes,'lead_role'=>$command->leadProfessionalRole,'project_id'=>$context->projectId,'organisation_id'=>$context->organisationId,'expected_version'=>$command->expectedVersion]);
            if ($update->rowCount() !== 1) {
                // Distinguish a missing project from a stale optimistic version before raising.
                $exists = $this->pdo->prepare('SELECT 1 FROM projects WHERE id=UNHEX(:project_id) AND organisation_id=UNHEX(:organisation_id)');
                $exists->execute(['project_id'=>$context->projectId,'organisation_id'=>$context->organisationId]);
                throw new ProjectWorkflowException($exists->fetchColumn() === false ? 'project_not_found' : 'stale_project_version');
            }
            $project = $this->find($context) ?? throw new ProjectWorkflowException('project_not_found');
            (new AuditService($this->pdo))->append($context->organisationId, $context->userId, 'project.details_updated', 'project', $context->projectId, ['projectCode'=>$project->projectCode,'version'=>$project->version]);
            $this->pdo->commit();
            return $project;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            // A duplicate project_code within the organisation violates the unique key;
            // translate it like create() so the controller's 409 mapping is reachable.
            if ($exception instanceof \PDOException && ($exception->errorInfo[1] ?? null) === 1062) throw new ProjectWorkflowException('project_code_conflict');
            throw $exception;
        }
    }

    public function create(CreateProject $command): ProjectPassport
    {
        $this->pdo->beginTransaction();
        try {
            $membership = $this->pdo->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='active'");
            $membership->execute(['organisation_id'=>$command->organisationId,'user_id'=>$command->userId]);
            if ($membership->fetchColumn() === false) throw new ProjectWorkflowException('tenant_access_denied');
            $projectId = Identifier::generate();
            $insert = $this->pdo->prepare('INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),:project_code,:project_name,:client_name,:municipality,:property_reference,:property_use,:land_use_notes,:lead_role,UNHEX(:user_id))');
            $insert->execute(['id'=>$projectId,'organisation_id'=>$command->organisationId,'project_code'=>$command->projectCode,'project_name'=>$command->projectName,'client_name'=>$command->clientName,'municipality'=>$command->municipality,'property_reference'=>$command->propertyReference,'property_use'=>$command->propertyUse,'land_use_notes'=>$command->landUseNotes,'lead_role'=>$command->leadProfessionalRole,'user_id'=>$command->userId]);
            (new AuditService($this->pdo))->append($command->organisationId, $command->userId, 'project.created', 'project', $projectId, ['projectCode'=>$command->projectCode]);
            $this->pdo->commit();
            return $this->find(new ProjectContext($command->organisationId, $command->userId, $projectId, 'owner')) ?? throw new ProjectWorkflowException('project_not_found');
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) throw new ProjectWorkflowException('project_code_conflict');
            throw $exception;
        }
    }

    public function find(ProjectContext $context): ?ProjectPassport
    {
        $statement = $this->pdo->prepare("SELECT HEX(p.id) project_id,HEX(p.organisation_id) organisation_id,p.project_code,p.project_name,p.client_name,p.municipality,p.property_reference,p.property_use,p.land_use_notes,p.lead_professional_role,p.current_phase,p.version FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:user_id) AND m.status='active' WHERE p.id=UNHEX(:project_id) AND p.organisation_id=UNHEX(:organisation_id)");
        $statement->execute(['user_id'=>$context->userId,'project_id'=>$context->projectId,'organisation_id'=>$context->organisationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        $phase = ProjectPhase::from((string) $row['current_phase']);
        $usable = (new \Architex\Platform\Records\ProjectRecordService($this->pdo))->usableTypes($context);
        $missing = [];
        foreach (array_diff($phase->requiredRecords(), $usable) as $type) {
            $priority = $type === 'municipal_approval_letter' ? 'critical' : (in_array($type, ['professional_appointment','construction_programme','snag_register'], true) ? 'high' : 'medium');
            $missing[] = ['recordType'=>$type,'priority'=>$priority,'reason'=>'Required for ' . $phase->value . ': ' . $type];
        }
        return $this->hydrate($row, $phase, $missing);
    }

    /** @return list<ProjectPassport> */
    public function list(string $organisationId, string $userId): array
    {
        $statement = $this->pdo->prepare("SELECT HEX(p.id) project_id FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:user_id) AND m.status='active' WHERE p.organisation_id=UNHEX(:organisation_id) ORDER BY p.updated_at DESC,p.project_name");
        $statement->execute(['user_id'=>$userId,'organisation_id'=>$organisationId]);
        $projects = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $projectId) {
            $found = $this->find(new ProjectContext($organisationId, $userId, strtolower((string)$projectId), 'member'));
            if ($found !== null) $projects[] = $found;
        }
        return $projects;
    }

    public function effectiveRole(string $organisationId, string $userId, string $projectId): string
    {
        $statement = $this->pdo->prepare("SELECT COALESCE((SELECT t.discipline FROM project_team_members t WHERE t.organisation_id=p.organisation_id AND t.project_id=p.id AND t.user_id=UNHEX(:team_user_id) AND t.status='active' ORDER BY t.accepted_at DESC,t.id DESC LIMIT 1),CASE WHEN p.created_by=UNHEX(:creator_user_id) THEN p.lead_professional_role ELSE (SELECT pp.profession FROM professional_profiles pp WHERE pp.organisation_id=p.organisation_id AND pp.user_id=UNHEX(:profile_user_id) ORDER BY pp.updated_at DESC,pp.id DESC LIMIT 1) END) effective_role FROM projects p WHERE p.organisation_id=UNHEX(:organisation_id) AND p.id=UNHEX(:project_id)");
        $statement->execute(['team_user_id'=>$userId,'creator_user_id'=>$userId,'profile_user_id'=>$userId,'organisation_id'=>$organisationId,'project_id'=>$projectId]);
        $role = $statement->fetchColumn();
        return is_string($role) && $role !== '' ? $role : 'client';
    }

    /** @param array<string,mixed> $row @param list<array{recordType:string,priority:string,reason:string}> $missing */
    private function hydrate(array $row, ProjectPhase $phase, array $missing): ProjectPassport
    {
        return new ProjectPassport(
            strtolower((string)$row['project_id']), strtolower((string)$row['organisation_id']), (string)$row['project_code'], (string)$row['project_name'],
            (string)$row['client_name'], (string)$row['municipality'], (string)$row['property_reference'], (string)$row['property_use'],
            (string)$row['land_use_notes'], (string)$row['lead_professional_role'], $phase, (int)$row['version'], $missing, $missing === [],
        );
    }
}

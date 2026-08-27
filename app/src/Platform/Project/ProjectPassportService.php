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
        $statement = $this->pdo->prepare("SELECT HEX(p.id) project_id,HEX(p.organisation_id) organisation_id,p.project_code,p.project_name,p.current_phase,p.version FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:user_id) AND m.status='active' WHERE p.id=UNHEX(:project_id) AND p.organisation_id=UNHEX(:organisation_id)");
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
        return new ProjectPassport(strtolower((string)$row['project_id']), strtolower((string)$row['organisation_id']), (string)$row['project_code'], (string)$row['project_name'], $phase, (int)$row['version'], $missing, $missing === []);
    }
}

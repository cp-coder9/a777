<?php
declare(strict_types=1);
namespace Architex\Platform\Project;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Records\ProjectRecordService;
use Architex\Platform\Workflow\EventBus;
use PDO;
use Throwable;

final readonly class LifecycleService
{
    public function __construct(private PDO $pdo) {}

    public function transition(ProjectContext $context, ProjectPhase $target, int $expectedVersion): ProjectPassport
    {
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare('SELECT current_phase,version FROM projects WHERE id=UNHEX(:project_id) AND organisation_id=UNHEX(:organisation_id) FOR UPDATE');
            $lock->execute(['project_id'=>$context->projectId,'organisation_id'=>$context->organisationId]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) throw new ProjectWorkflowException('project_not_found');
            $current = ProjectPhase::from((string)$row['current_phase']);
            if ((int)$row['version'] !== $expectedVersion) throw new ProjectWorkflowException('stale_project_version');
            if ($current->next() !== $target) throw new ProjectWorkflowException('invalid_phase_transition');
            $records = new ProjectRecordService($this->pdo);
            $missing = array_values(array_diff($current->requiredRecords(), $records->usableTypes($context)));
            if ($missing !== []) throw new ProjectWorkflowException('phase_requirements_missing', 'phase_requirements_missing');
            $update = $this->pdo->prepare('UPDATE projects SET current_phase=:target,version=version+1 WHERE id=UNHEX(:project_id) AND organisation_id=UNHEX(:organisation_id) AND version=:expected');
            $update->execute(['target'=>$target->value,'project_id'=>$context->projectId,'organisation_id'=>$context->organisationId,'expected'=>$expectedVersion]);
            if ($update->rowCount() !== 1) throw new ProjectWorkflowException('stale_project_version');
            $payload = ['from'=>$current->value,'to'=>$target->value,'version'=>$expectedVersion + 1];
            $records->issue($context, $target, 'project', 'project_phase_transition', 'Phase changed to ' . $target->value, 'issued', $payload);
            (new EventBus($this->pdo))->append($context, 'project_phase_changed', 'medium', 'projects', $payload);
            (new InboxService($this->pdo))->create($context, 'project_phase_changed', 'Project phase changed', $current->value . ' to ' . $target->value, 'medium', $payload);
            (new AuditService($this->pdo))->append($context->organisationId, $context->userId, 'project.phase_changed', 'project', $context->projectId, $payload);
            $this->pdo->commit();
            return (new ProjectPassportService($this->pdo))->find($context) ?? throw new ProjectWorkflowException('project_not_found');
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}

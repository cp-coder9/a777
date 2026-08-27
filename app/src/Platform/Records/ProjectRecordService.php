<?php
declare(strict_types=1);
namespace Architex\Platform\Records;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use InvalidArgumentException;
use PDO;

final readonly class ProjectRecordService
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $payload @param array<string,mixed> $approvals @param list<string> $linkedRecordIds */
    public function issue(ProjectContext $context, ProjectPhase $phase, string $moduleKey, string $recordType, string $title, string $status, array $payload, array $approvals = ['required' => false], array $linkedRecordIds = []): string
    {
        if (!in_array($status, ['draft','pending_review','approved','issued','superseded','rejected','missing'], true)) throw new InvalidArgumentException('Invalid record status.');
        $id = Identifier::generate();
        $statement = $this->pdo->prepare('INSERT INTO project_records(id,organisation_id,project_id,phase,module_key,record_type,title,status,payload,approvals,linked_record_ids,created_by) SELECT UNHEX(:id),p.organisation_id,p.id,:phase,:module_key,:record_type,:title,:status,:payload,:approvals,:links,UNHEX(:created_by) FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:member_user_id) AND m.status=\'active\' WHERE p.id=UNHEX(:project_id) AND p.organisation_id=UNHEX(:organisation_id)');
        $statement->execute([
            'id' => $id, 'phase' => $phase->value, 'module_key' => $moduleKey, 'record_type' => $recordType,
            'title' => $title, 'status' => $status, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'approvals' => json_encode($approvals, JSON_THROW_ON_ERROR), 'links' => json_encode($linkedRecordIds, JSON_THROW_ON_ERROR),
            'created_by' => $context->userId, 'member_user_id' => $context->userId,
            'project_id' => $context->projectId, 'organisation_id' => $context->organisationId,
        ]);
        if ($statement->rowCount() !== 1) throw new InvalidArgumentException('Project context is invalid.');
        return $id;
    }

    /** @return list<string> */
    public function usableTypes(ProjectContext $context): array
    {
        $statement = $this->pdo->prepare("SELECT DISTINCT record_type FROM project_records WHERE organisation_id=UNHEX(:organisation_id) AND project_id=UNHEX(:project_id) AND status IN ('approved','issued')");
        $statement->execute(['organisation_id' => $context->organisationId, 'project_id' => $context->projectId]);
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}

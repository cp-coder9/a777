<?php
declare(strict_types=1);
namespace Architex\Platform\Workflow;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\ProjectContext;
use PDO;

final readonly class EventBus
{
    public function __construct(private PDO $pdo) {}
    /** @param array<string,mixed> $payload */
    public function append(ProjectContext $context, string $type, string $priority, string $sourceModule, array $payload): string
    {
        $id = Identifier::generate();
        $statement = $this->pdo->prepare('INSERT INTO workflow_events(id,organisation_id,project_id,event_type,priority,source_module,payload) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),:event_type,:priority,:source_module,:payload)');
        $statement->execute(['id'=>$id,'organisation_id'=>$context->organisationId,'project_id'=>$context->projectId,'event_type'=>$type,'priority'=>$priority,'source_module'=>$sourceModule,'payload'=>json_encode($payload, JSON_THROW_ON_ERROR)]);
        return $id;
    }
}

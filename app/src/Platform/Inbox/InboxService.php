<?php
declare(strict_types=1);
namespace Architex\Platform\Inbox;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\ProjectContext;
use PDO;

final readonly class InboxService
{
    public function __construct(private PDO $pdo) {}
    /** @param array<string,mixed> $payload */
    public function create(ProjectContext $context, string $type, string $title, string $detail, string $priority, array $payload = []): string
    {
        $id = Identifier::generate();
        $statement = $this->pdo->prepare('INSERT INTO inbox_items(id,organisation_id,project_id,assigned_user_id,assigned_role,event_type,title,detail,priority,payload) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:project_id),UNHEX(:user_id),:role,:event_type,:title,:detail,:priority,:payload)');
        $statement->execute(['id'=>$id,'organisation_id'=>$context->organisationId,'project_id'=>$context->projectId,'user_id'=>$context->userId,'role'=>$context->role,'event_type'=>$type,'title'=>$title,'detail'=>$detail,'priority'=>$priority,'payload'=>json_encode($payload, JSON_THROW_ON_ERROR)]);
        return $id;
    }
}

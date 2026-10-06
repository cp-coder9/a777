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
        $recipients = [$context->userId => $context->role];
        foreach ((new WorkflowRecipients($this->pdo))->forEvent($context->organisationId,$context->projectId,$type) as $recipient) {
            $recipients[$recipient['id']] = $recipient['role'];
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            foreach ($recipients as $user => $role) {
                $statement->execute(['id'=>$user===$context->userId?$id:Identifier::generate(),'organisation_id'=>$context->organisationId,'project_id'=>$context->projectId,'user_id'=>$user,'role'=>$role,'event_type'=>$type,'title'=>$title,'detail'=>$detail,'priority'=>$priority,'payload'=>json_encode($payload, JSON_THROW_ON_ERROR)]);
            }
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId, string $userId, string $role, int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $audience = InboxRecipients::predicate();
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(i.id)) id,LOWER(HEX(i.project_id)) projectId,i.event_type eventType,i.title,i.detail,i.priority,i.status,i.payload,DATE_FORMAT(i.created_at,'%Y-%m-%dT%H:%i:%s.%fZ') createdAt FROM inbox_items i JOIN organisation_memberships m ON {$audience} WHERE i.organisation_id=UNHEX(:organisation_id) AND m.user_id=UNHEX(:user_id) ORDER BY i.created_at DESC,i.id DESC LIMIT {$limit}");
        $statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['payload'] = json_decode((string)$row['payload'], true, flags: JSON_THROW_ON_ERROR);
        return $rows;
    }
}

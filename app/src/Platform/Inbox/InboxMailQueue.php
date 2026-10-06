<?php
declare(strict_types=1);
namespace Architex\Platform\Inbox;

use Architex\Platform\Jobs\JobQueue;
use PDO;

/** Reconciles committed inbox writes, including producers using direct SQL. */
final readonly class InboxMailQueue
{
    public function __construct(private PDO $pdo, private JobQueue $queue) {}

    public function enqueue(int $limit = 100): int
    {
        $limit = max(1, min(100, $limit));
        $audience = InboxRecipients::predicate();
        $rows = $this->pdo->query("SELECT LOWER(HEX(i.id)) inboxId,LOWER(HEX(m.user_id)) userId
            FROM inbox_items i JOIN organisation_memberships m ON {$audience}
            JOIN users u ON u.id=m.user_id AND u.status='active' AND u.email_verified_at IS NOT NULL
            JOIN organisations o ON o.id=i.organisation_id AND o.status='active'
            WHERE i.status='open' AND i.event_type NOT LIKE 'demo.%'
            AND NOT EXISTS (SELECT 1 FROM scheduled_job_deduplication_keys d
                WHERE d.deduplication_key=SHA2(CONCAT('inbox:',LOWER(HEX(i.id)),':',LOWER(HEX(m.user_id))),256))
            ORDER BY i.created_at,i.id,m.user_id LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $this->queue->enqueue('send_inbox_email', $row, 'inbox:' . $row['inboxId'] . ':' . $row['userId']);
        }
        return count($rows);
    }

    /** Resolve current authorisation and address again immediately before delivery. */
    public function recipient(string $inboxId, string $userId): ?string
    {
        $audience = InboxRecipients::predicate();
        $statement = $this->pdo->prepare("SELECT u.email FROM inbox_items i
            JOIN organisation_memberships m ON {$audience}
            JOIN users u ON u.id=m.user_id AND u.status='active' AND u.email_verified_at IS NOT NULL
            JOIN organisations o ON o.id=i.organisation_id AND o.status='active'
            WHERE i.id=UNHEX(:inbox) AND m.user_id=UNHEX(:user) AND i.status='open' AND i.event_type NOT LIKE 'demo.%'");
        $statement->execute(['inbox'=>$inboxId,'user'=>$userId]);
        $email = $statement->fetchColumn();
        return is_string($email) ? $email : null;
    }
}

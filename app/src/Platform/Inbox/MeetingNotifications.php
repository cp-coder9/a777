<?php
declare(strict_types=1);
namespace Architex\Platform\Inbox;

use Architex\Platform\Project\ProjectContext;
use PDO;

final readonly class MeetingNotifications
{
    public function __construct(private PDO $pdo) {}

    /** @param list<array<string,mixed>> $attendees @param list<array<string,mixed>> $outcomes */
    public function published(string $organisation, string $project, string $meeting, string $minutes, array $attendees, array $outcomes): void
    {
        $identities = array_column($attendees, 'identity');
        foreach ($outcomes as $outcome) {
            if (($outcome['state'] ?? null) === 'accepted') $identities[] = $outcome['owner'] ?? '';
        }
        $resolve = $this->pdo->prepare("SELECT LOWER(HEX(u.id)) id,m.role FROM users u
            JOIN organisation_memberships m ON m.user_id=u.id AND m.organisation_id=UNHEX(:organisation) AND m.status='active'
            WHERE u.status='active' AND (LOWER(HEX(u.id))=:identity_id OR u.email=:identity_email)");
        $seen = [];
        foreach (array_unique($identities) as $identity) {
            if (!is_string($identity) || trim($identity) === '') continue;
            $resolve->execute(['organisation'=>$organisation,'identity_id'=>strtolower(trim($identity)),'identity_email'=>trim($identity)]);
            $rows = $resolve->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== 1 || isset($seen[$rows[0]['id']])) continue;
            $recipient = $rows[0];
            $seen[$recipient['id']] = true;
            (new InboxService($this->pdo))->create(new ProjectContext($organisation,$recipient['id'],$project,$recipient['role']),
                'meeting_minutes_published','Meeting minutes published','Reviewed meeting minutes and accepted outcomes are available.','medium',
                ['meetingId'=>$meeting,'minutesId'=>$minutes]);
        }
    }
}

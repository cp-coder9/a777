<?php
declare(strict_types=1);
namespace Architex\Tests\Unit\Inbox;

use Architex\Platform\Inbox\InboxRecipients;
use PHPUnit\Framework\TestCase;

final class NotificationRoutingTest extends TestCase
{
    public function testDirectAssignmentTakesPrecedenceOverRole(): void
    {
        $sql = InboxRecipients::predicate();
        self::assertStringContainsString('i.assigned_user_id=m.user_id', $sql);
        self::assertStringContainsString('i.assigned_user_id IS NULL AND i.assigned_role=m.role', $sql);
        self::assertStringNotContainsString('OR i.assigned_role=m.role', $sql);
    }

    public function testRoleAudienceRequiresActiveProjectMembershipOrManagement(): void
    {
        $sql = InboxRecipients::predicate();
        self::assertStringContainsString("m.status='active'", $sql);
        self::assertStringContainsString('m.organisation_id=i.organisation_id', $sql);
        self::assertStringContainsString('t.project_id=i.project_id', $sql);
        self::assertStringContainsString("t.status='active'", $sql);
    }
}

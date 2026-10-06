<?php
declare(strict_types=1);
namespace Architex\Platform\Inbox;

/** Shared by inbox reads and mail dispatch. Labels never grant permissions. */
final class InboxRecipients
{
    public static function predicate(): string
    {
        return "m.organisation_id=i.organisation_id AND m.status='active'
            AND (i.project_id IS NULL OR EXISTS (SELECT 1 FROM projects p WHERE p.id=i.project_id AND p.organisation_id=i.organisation_id))
            AND (i.assigned_user_id=m.user_id OR
                (i.assigned_user_id IS NULL AND i.assigned_role=m.role AND
                    (i.project_id IS NULL OR m.role IN ('owner','admin') OR
                     EXISTS (SELECT 1 FROM projects p WHERE p.id=i.project_id AND p.organisation_id=i.organisation_id AND p.created_by=m.user_id) OR
                     EXISTS (SELECT 1 FROM project_team_members t WHERE t.project_id=i.project_id AND t.organisation_id=i.organisation_id AND t.user_id=m.user_id AND t.status='active'))))";
    }
}

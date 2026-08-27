<?php
declare(strict_types=1);

namespace Architex\Platform\Authorization;

final class PermissionService
{
    /** @var array<string,list<string>> */
    private const ROLE_PERMISSIONS = [
        'owner' => ['organisation.manage','members.manage','projects.read','projects.write','tools.use'],
        'admin' => ['members.manage','projects.read','projects.write','tools.use'],
        'member' => ['projects.read','projects.write','tools.use'],
        'viewer' => ['projects.read'],
    ];

    public function allows(string $role, string $permission): bool
    {
        return in_array($permission, self::ROLE_PERMISSIONS[$role] ?? [], true);
    }
}

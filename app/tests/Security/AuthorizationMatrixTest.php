<?php
declare(strict_types=1);

namespace Architex\Tests\Security;

use Architex\Platform\Authorization\PermissionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthorizationMatrixTest extends TestCase
{
    #[DataProvider('decisions')]
    public function testRolePermissionMatrixFailsClosed(string $role, string $permission, bool $expected): void
    {
        self::assertSame($expected, (new PermissionService())->allows($role, $permission));
    }

    /** @return iterable<string,array{string,string,bool}> */
    public static function decisions(): iterable
    {
        yield 'owner manages organisation' => ['owner','organisation.manage',true];
        yield 'admin cannot manage organisation' => ['admin','organisation.manage',false];
        yield 'member writes projects' => ['member','projects.write',true];
        yield 'viewer cannot write' => ['viewer','projects.write',false];
        yield 'unknown role' => ['root','projects.read',false];
        yield 'unknown permission' => ['owner','system.override',false];
    }
}

<?php
declare(strict_types=1);

namespace Architex\Tests\Security;

use Architex\Http\Middleware\OrganisationContextMiddleware;
use Architex\Http\Middleware\PermissionMiddleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
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

    public function testTenantAndPermissionMiddlewareReturnDistinctFailClosedResponses(): void
    {
        $permissions = new PermissionMiddleware(new PermissionService(), 'projects.write');
        $next = static fn (Request $request): Response => Response::success(['called' => true]);
        self::assertSame(401, $permissions->process(Request::json('GET', '/'), $next)->status());

        $identity = new AuthenticatedIdentity(str_repeat('a', 32), 'viewer@example.com', [['organisationId' => str_repeat('b', 32), 'role' => 'viewer']]);
        $withoutTenant = Request::json('GET', '/')->withAttribute('identity', $identity);
        self::assertSame(404, $permissions->process($withoutTenant, $next)->status());

        $wrongTenant = Request::json('GET', '/', ['X-Organisation-Id' => str_repeat('c', 32)])->withAttribute('identity', $identity);
        self::assertSame(404, (new OrganisationContextMiddleware())->process($wrongTenant, $next)->status());

        $scoped = Request::json('GET', '/', ['X-Organisation-Id' => str_repeat('b', 32)])->withAttribute('identity', $identity);
        $contextual = (new OrganisationContextMiddleware())->process($scoped, static fn (Request $request): Response => $permissions->process($request, $next));
        self::assertSame(403, $contextual->status());
        self::assertSame('forbidden', $contextual->json()['error']['code']);
    }
}

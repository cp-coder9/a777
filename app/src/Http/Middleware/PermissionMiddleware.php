<?php
declare(strict_types=1);

namespace Architex\Http\Middleware;

use Architex\Http\Middleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Authorization\PermissionService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Closure;

final readonly class PermissionMiddleware implements Middleware
{
    public function __construct(private PermissionService $permissions, private string $permission) {}

    public function process(Request $request, Closure $next): Response
    {
        if (!$request->attribute('identity') instanceof AuthenticatedIdentity) {
            return Response::error(401, 'unauthenticated', 'Authentication is required.');
        }
        $role = $request->attribute('role');
        if (!is_string($role)) {
            return Response::error(404, 'not_found', 'Organisation not found.');
        }
        if (!$this->permissions->allows($role, $this->permission)) {
            return Response::error(403, 'forbidden', 'Permission denied.');
        }
        return $next($request);
    }
}

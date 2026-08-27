<?php
declare(strict_types=1);

namespace Architex\Http\Middleware;

use Architex\Http\Middleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Closure;

final class OrganisationContextMiddleware implements Middleware
{
    public function process(Request $request, Closure $next): Response
    {
        $organisationId = $request->header('X-Organisation-Id');
        if ($organisationId === null) {
            return $next($request);
        }
        $identity = $request->attribute('identity');
        if (!$identity instanceof AuthenticatedIdentity) {
            return Response::error(401, 'unauthenticated', 'Authentication is required.');
        }
        foreach ($identity->memberships as $membership) {
            if (hash_equals($membership['organisationId'], strtolower($organisationId))) {
                return $next($request->withAttribute('organisationId', $membership['organisationId'])->withAttribute('role', $membership['role']));
            }
        }
        return Response::error(404, 'not_found', 'Organisation not found.');
    }
}

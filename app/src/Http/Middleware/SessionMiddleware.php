<?php
declare(strict_types=1);

namespace Architex\Http\Middleware;

use Architex\Http\Middleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\IdentityException;
use Architex\Platform\Identity\SessionGateway;
use Closure;

final readonly class SessionMiddleware implements Middleware
{
    public function __construct(private SessionGateway $sessions) {}

    public function process(Request $request, Closure $next): Response
    {
        $token = $this->token($request);
        if ($token === null) {
            return $next($request);
        }
        try {
            $session = $this->sessions->resolve($token);
        } catch (IdentityException) {
            return Response::error(401, 'unauthenticated', 'Authentication is required.')->withHeaders(['Set-Cookie' => 'architex_session=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax']);
        }
        return $next($request->withAttribute('session', $session)->withAttribute('identity', $session->identity));
    }

    private function token(Request $request): ?string
    {
        $authorization = $request->header('Authorization');
        if (is_string($authorization) && preg_match('/^Bearer ([A-Za-z0-9_-]{40,200})$/D', $authorization, $matches) === 1) {
            return $matches[1];
        }
        $cookie = $request->header('Cookie');
        if (is_string($cookie) && preg_match('/(?:^|;\s*)architex_session=([A-Za-z0-9_-]{40,200})(?:;|$)/D', $cookie, $matches) === 1) {
            return $matches[1];
        }
        return null;
    }
}

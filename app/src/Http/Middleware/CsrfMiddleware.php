<?php
declare(strict_types=1);

namespace Architex\Http\Middleware;

use Architex\Http\Middleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedSession;
use Architex\Platform\Identity\SessionGateway;
use Closure;

final readonly class CsrfMiddleware implements Middleware
{
    public function __construct(private SessionGateway $sessions) {}

    public function process(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST','PUT','PATCH','DELETE'], true)) {
            return $next($request);
        }
        $session = $request->attribute('session');
        if (!$session instanceof AuthenticatedSession) {
            return $next($request);
        }
        $token = $request->header('X-CSRF-Token');
        if (!is_string($token) || !$this->sessions->verifyCsrf($session->sessionId, $token)) {
            return Response::error(419, 'csrf_invalid', 'CSRF validation failed.');
        }
        return $next($request);
    }
}

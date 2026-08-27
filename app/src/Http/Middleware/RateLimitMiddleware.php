<?php

declare(strict_types=1);

namespace Architex\Http\Middleware;

use Architex\Http\Middleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Security\RateLimiter;
use Closure;

final readonly class RateLimitMiddleware implements Middleware
{
    /** @var Closure(Request):string */
    private Closure $keyResolver;

    /** @param Closure(Request):string|null $keyResolver */
    public function __construct(
        private RateLimiter $limiter,
        private string $scope,
        private int $limit,
        private int $windowSeconds,
        ?Closure $keyResolver = null,
    ) {
        $this->keyResolver = $keyResolver ?? static fn (Request $request): string => is_string($request->attribute('remoteIp')) ? $request->attribute('remoteIp') : 'unknown';
    }

    public function process(Request $request, Closure $next): Response
    {
        $decision = $this->limiter->consume($this->scope, ($this->keyResolver)($request), $this->limit, $this->windowSeconds);
        if (!$decision->allowed) {
            return Response::error(429, 'rate_limited', 'Too many requests.', headers: [
                'Retry-After' => (string) $decision->retryAfterSeconds,
                'X-RateLimit-Remaining' => '0',
            ]);
        }
        return $next($request)->withHeaders(['X-RateLimit-Remaining' => (string) $decision->remaining]);
    }
}

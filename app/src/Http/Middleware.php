<?php
declare(strict_types=1);

namespace Architex\Http;

use Closure;

interface Middleware
{
    /** @param Closure(Request):Response $next */
    public function process(Request $request, Closure $next): Response;
}

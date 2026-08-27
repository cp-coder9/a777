<?php
declare(strict_types=1);

namespace Architex\Http\Middleware;

use Architex\Http\Middleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Closure;

final readonly class JsonRequestMiddleware implements Middleware
{
    public function __construct(private int $maximumBytes = 1_048_576) {}

    public function process(Request $request, Closure $next): Response
    {
        if (strlen($request->body()) > $this->maximumBytes) {
            return Response::error(413, 'request_too_large', 'Request body is too large.');
        }
        if (in_array($request->method(), ['POST','PUT','PATCH'], true) && $request->body() !== '') {
            $contentType = strtolower(trim(explode(';', $request->header('Content-Type') ?? '')[0]));
            if ($contentType !== 'application/json') {
                return Response::error(415, 'unsupported_media_type', 'Content-Type must be application/json.');
            }
            try {
                $request->jsonBody();
            } catch (\JsonException) {
                return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
            }
        }
        return $next($request);
    }
}

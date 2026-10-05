<?php
declare(strict_types=1);
namespace Architex\Http\Controller;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Closure;
final readonly class V1ClonePageController
{
    public function __construct(private Closure $render, private bool $allowVisualFixtures = false) {}
    public function __invoke(Request $request): Response
    {
        $headers = [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
        ];
        if (!$request->attribute('identity') instanceof AuthenticatedIdentity) {
            return Response::binary('<!doctype html><html lang="en"><meta charset="utf-8"><title>Sign in required</title><body><h1>Sign in required</h1><p>The V1 candidate requires your Architex session.</p><a href="/app">Open sign in</a></body></html>', 'text/html', $headers, 401);
        }
        $html = ($this->render)();
        if ($this->allowVisualFixtures) $html = str_replace('</head>', '<meta name="v1-clone-visual-fixtures" content="allowed"></head>', $html);
        return Response::binary($html, 'text/html', $headers);
    }
}

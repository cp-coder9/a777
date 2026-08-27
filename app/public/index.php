<?php

declare(strict_types=1);

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Controller\HealthController;
use Architex\Http\Kernel;
use Architex\Http\Middleware\SecurityHeadersMiddleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Http\Router;
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Mail\UnavailableIdentityNotifier;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$request = Request::fromGlobals();
if ($request->path() === '/api/v1/health') {
    $router = new Router();
    $router->get('/api/v1/health', new HealthController());
    (new Kernel($router, middleware: [new SecurityHeadersMiddleware()]))->handle($request)->emit();
    return;
}

try {
    $environment = getenv();
    $config = AppConfig::fromEnvironment(is_array($environment) ? $environment : []);
    $notifier = new UnavailableIdentityNotifier();
    $kernel = (new AuthenticationKernelFactory(ConnectionFactory::create($config), $notifier, $notifier))->create();
} catch (Throwable) {
    $router = new Router();
    $router->add($request->method(), $request->path(), static fn (Request $request): Response => Response::error(503, 'service_unavailable', 'The service is temporarily unavailable.'));
    $kernel = new Kernel($router, middleware: [new SecurityHeadersMiddleware()]);
}

$kernel->handle($request)->emit();

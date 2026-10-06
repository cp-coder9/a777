<?php

declare(strict_types=1);

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Controller\HealthController;
use Architex\Http\Controller\ReadinessController;
use Architex\Http\Kernel;
use Architex\Http\Middleware\SecurityHeadersMiddleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Http\Router;
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Database\MigrationRunner;
use Architex\Infrastructure\Mail\MailRuntime;

require dirname(__DIR__) . '/bootstrap.php';

// PHP's built-in server invokes this file as a router. Let it serve real public
// assets directly; production Apache/LiteSpeed reaches this file only for app
// routes through .htaccess.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_string($requestPath) && $requestPath !== '/' && is_file(__DIR__ . $requestPath)) {
        return false;
    }
}

$request = Request::fromGlobals();
$legalRoutes = ['/terms','/privacy','/disclaimer','/ai-disclaimer','/payment-escrow-terms','/cpd-terms','/resource-sharing-terms','/dispute-resolution-rules'];
if ($request->method() === 'GET' && in_array($request->path(), $legalRoutes, true)) {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'none'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    require __DIR__ . '/legal.php';
    return;
}
$isAppRoute = $request->method() === 'GET' && (
    $request->path() === '/' || $request->path() === '/app' ||
    $request->path() === '/v1' || $request->path() === '/legacy' ||
    ($request->path() === '/demo' && getenv('APP_MODE')==='demo') ||
    preg_match('#\A/(?:messages/community|community-app|admin/community-(?:moderation|channels)|admin/platform-completeness|admin/qc-controls/nuanced-gap-test|qc/runs/[a-f0-9]{32}|qc/projects/[a-f0-9]{32}|supplier/rfqs/[A-Za-z0-9_-]{43}|projects/[a-f0-9]{32}/procurement/suppliers|toolbox/standalone/(?:sa-council-drawing-compliance-navigator|freelancer-job-ticket-marketplace))(?:/.*)?\z#', $request->path()) === 1
);
if ($isAppRoute) {
    // V1 pixel-clone default (promoted): serve the v1.html clone at / and /v1.
    // The prior application remains reachable at /app and /legacy for reversal.
    // Flip getenv('V1_CLONE_DEFAULT') to '0' (or remove it) to revert to the old UI at /.
    $v1CloneDefault = getenv('V1_CLONE_DEFAULT') !== '0';
    if ($v1CloneDefault && ($request->path() === '/' || $request->path() === '/v1')) {
        $environment = getenv();
        $pdo = ConnectionFactory::create(AppConfig::fromEnvironment(is_array($environment) ? $environment : []));
        $allowFixtures = getenv('APP_ENV') === 'test' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        $controller = new \Architex\Http\Controller\V1ClonePageController(static function (): string {
            $html = file_get_contents(dirname(__DIR__) . '/views/v1-clone.php');
            if (!is_string($html)) throw new RuntimeException('Candidate assets have not been built.');
            return $html;
        }, $allowFixtures);
        (new \Architex\Http\Middleware\SessionMiddleware(new \Architex\Platform\Identity\SessionService($pdo)))
            ->process($request, static fn (Request $authenticated): Response => $controller($authenticated))->emit();
        return;
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    require __DIR__ . '/app.php';
    return;
}
if ($request->path() === '/api/v1/health') {
    $router = new Router();
    $router->get('/api/v1/health', new HealthController());
    (new Kernel($router, middleware: [new SecurityHeadersMiddleware()]))->handle($request)->emit();
    return;
}

try {
    $environment = getenv();
    $config = AppConfig::fromEnvironment(is_array($environment) ? $environment : []);
    $pdo = ConnectionFactory::create($config);
    $runtime = MailRuntime::fromEnvironment($pdo, is_array($environment) ? $environment : []);
    $migrations = dirname(__DIR__) . '/database/migrations';
    $readiness = new ReadinessController(
        static fn (): bool => (int)$pdo->query('SELECT 1')->fetchColumn() === 1,
        static fn (): bool => (new MigrationRunner($pdo, $migrations))->status()->isCurrent(),
        static fn (): bool => (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('scheduled_jobs','scheduled_job_deduplication_keys')")->fetchColumn() === 2,
        static fn (): bool => $runtime->isReady(),
    );
    $fileStorageRoot = is_string($environment['FILE_STORAGE_PATH'] ?? null) && trim($environment['FILE_STORAGE_PATH']) !== '' ? trim($environment['FILE_STORAGE_PATH']) : null;
    $kernel = (new AuthenticationKernelFactory($pdo, $runtime->registrationNotifier, $runtime->passwordResetNotifier, $readiness, $fileStorageRoot, $config->appMode, appUrl: $runtime->appUrl))->create();
} catch (Throwable) {
    $router = new Router();
    if ($request->path() === '/api/v1/readiness') {
        $unavailable = static fn (): bool => false;
        $router->get('/api/v1/readiness', new ReadinessController($unavailable, $unavailable, $unavailable, $unavailable));
    } else {
        $router->add($request->method(), $request->path(), static fn (Request $request): Response => Response::error(503, 'service_unavailable', 'The service is temporarily unavailable.'));
    }
    $kernel = new Kernel($router, middleware: [new SecurityHeadersMiddleware()]);
}

$kernel->handle($request)->emit();

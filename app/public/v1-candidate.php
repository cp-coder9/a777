<?php
declare(strict_types=1);
use Architex\Http\Controller\V1ClonePageController;
use Architex\Http\Middleware\SessionMiddleware;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Platform\Identity\SessionService;
require dirname(__DIR__) . '/bootstrap.php';
$request = Request::fromGlobals();
if ($request->method() !== 'GET') {
    Response::error(405, 'method_not_allowed', 'Use GET for this page.', headers: ['Allow' => 'GET', 'Cache-Control' => 'no-store'])->emit();
    return;
}
try {
    $environment = getenv();
    $pdo = ConnectionFactory::create(AppConfig::fromEnvironment(is_array($environment) ? $environment : []));
    $allowFixtures = getenv('APP_ENV') === 'test' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $controller = new V1ClonePageController(static function (): string {
        $html = file_get_contents(dirname(__DIR__) . '/views/v1-clone.php');
        if (!is_string($html)) throw new RuntimeException('Candidate assets have not been built.');
        return $html;
    }, $allowFixtures);
    (new SessionMiddleware(new SessionService($pdo)))->process($request, static fn (Request $authenticated): Response => $controller($authenticated))->emit();
} catch (Throwable) {
    Response::error(503, 'candidate_unavailable', 'The candidate is temporarily unavailable.', headers: ['Cache-Control' => 'no-store'])->emit();
}

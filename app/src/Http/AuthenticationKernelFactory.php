<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\AuthController;
use Architex\Http\Controller\HealthController;
use Architex\Http\Controller\RegistrationController;
use Architex\Http\Controller\ProjectController;
use Architex\Http\Controller\InboxController;
use Architex\Http\Controller\ToolRegistryController;
use Architex\Http\Controller\ReadinessController;
use Architex\Http\Controller\FileController;
use Architex\Http\Middleware\CsrfMiddleware;
use Architex\Http\Middleware\JsonRequestMiddleware;
use Architex\Http\Middleware\OrganisationContextMiddleware;
use Architex\Http\Middleware\RateLimitMiddleware;
use Architex\Http\Middleware\PermissionMiddleware;
use Architex\Http\Middleware\SecurityHeadersMiddleware;
use Architex\Http\Middleware\SessionMiddleware;
use Architex\Platform\Identity\AuthenticationService;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\PasswordResetService;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\RegistrationService;
use Architex\Platform\Identity\SessionService;
use Architex\Platform\Security\RateLimiter;
use Architex\Platform\Authorization\PermissionService;
use Architex\Platform\Project\LifecycleService;
use Architex\Platform\Project\ProjectPassportService;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Files\FileService;
use Architex\Platform\Files\LocalFileStorage;
use Architex\Infrastructure\Logging\JsonLogger;
use PDO;

final readonly class AuthenticationKernelFactory
{
    public function __construct(
        private PDO $pdo,
        private RegistrationNotifier $registrationNotifier,
        private PasswordResetNotifier $passwordResetNotifier,
        private ?ReadinessController $readinessController = null,
    ) {}

    public function create(): Kernel
    {
        $sessions = new SessionService($this->pdo);
        $limiter = new RateLimiter($this->pdo);
        $registration = new RegistrationController(new RegistrationService($this->pdo, $this->registrationNotifier));
        $authentication = new AuthController(
            new AuthenticationService($this->pdo),
            $sessions,
            new PasswordResetService($this->pdo, $this->passwordResetNotifier),
        );
        $remoteKey = static fn (Request $request): string => is_string($request->attribute('remoteIp')) ? $request->attribute('remoteIp') : 'unknown';
        $loginKey = static function (Request $request) use ($remoteKey): string {
            try {
                $body = $request->jsonBody();
                $email = is_string($body['email'] ?? null) ? strtolower(trim($body['email'])) : 'invalid';
            } catch (\JsonException) {
                $email = 'invalid';
            }
            return $email . '|' . $remoteKey($request);
        };

        $router = new Router();
        $router->get('/api/v1/health', new HealthController());
        if ($this->readinessController !== null) {
            $router->get('/api/v1/readiness', $this->readinessController);
        }
        (new AuthenticationRoutes(
            $registration,
            $authentication,
            new RateLimitMiddleware($limiter, 'registration', 5, 3_600, $remoteKey),
            new RateLimitMiddleware($limiter, 'login', 10, 900, $loginKey),
            new RateLimitMiddleware($limiter, 'password_reset', 5, 3_600, $remoteKey),
        ))->register($router);
        (new ProjectRoutes(
            new ProjectController(new ProjectPassportService($this->pdo), new LifecycleService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new ShellDataRoutes(
            new InboxController(new InboxService($this->pdo)),
            new ToolRegistryController(dirname(__DIR__, 2) . '/public/assets/tool-registry.json'),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
        ))->register($router);
        (new FileRoutes(
            new FileController(new FileService($this->pdo, new LocalFileStorage(dirname(__DIR__, 2) . '/storage/files'))),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);

        return new Kernel($router, middleware: [
            new SecurityHeadersMiddleware(),
            new JsonRequestMiddleware(),
            new SessionMiddleware($sessions),
            new OrganisationContextMiddleware(),
            new CsrfMiddleware($sessions),
        ], logger: JsonLogger::toFile(dirname(__DIR__, 2) . '/storage/logs/application.jsonl'));
    }
}

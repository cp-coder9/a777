<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\AuthController;
use Architex\Http\Controller\RegistrationController;
use Architex\Http\Controller\LegalNoticeController;
use Closure;

final readonly class AuthenticationRoutes
{
    public function __construct(
        private RegistrationController $registration,
        private AuthController $authentication,
        private Middleware $registrationLimit,
        private Middleware $loginLimit,
        private Middleware $passwordResetLimit,
        private ?LegalNoticeController $legalNotices = null,
    ) {}

    public function register(Router $router): void
    {
        if ($this->legalNotices !== null) {
            $router->get('/api/v1/legal-notices/signup', $this->legalNotices->signup(...));
        }
        $router->post('/api/v1/registrations', $this->through($this->registrationLimit, $this->registration->create(...)));
        $router->post('/api/v1/registrations/verify-email', $this->registration->verifyEmail(...));
        $router->post('/api/v1/sessions', $this->through($this->loginLimit, $this->authentication->login(...)));
        $router->delete('/api/v1/session', $this->authentication->logout(...));
        $router->get('/api/v1/session', $this->authentication->current(...));
        $router->post('/api/v1/password-resets', $this->through($this->passwordResetLimit, $this->authentication->requestPasswordReset(...)));
        $router->post('/api/v1/password-resets/complete', $this->through($this->passwordResetLimit, $this->authentication->completePasswordReset(...)));
    }

    /** @param Closure(Request):Response $handler @return Closure(Request):Response */
    private function through(Middleware $middleware, Closure $handler): Closure
    {
        return static fn (Request $request): Response => $middleware->process($request, $handler);
    }
}

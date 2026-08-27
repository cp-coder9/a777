<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Identity\AuthenticatedSession;
use Architex\Platform\Identity\AuthenticationGateway;
use Architex\Platform\Identity\IdentityException;
use Architex\Platform\Identity\SessionGateway;
use Architex\Platform\Identity\PasswordResetGateway;

final readonly class AuthController
{
    public function __construct(private AuthenticationGateway $authentication, private SessionGateway $sessions, private ?PasswordResetGateway $passwordResets = null) {}

    public function login(Request $request): Response
    {
        try {
            $body = $request->jsonBody();
            if (!is_string($body['email'] ?? null) || !is_string($body['password'] ?? null)) {
                return Response::error(422, 'validation_failed', 'Email and password are required.');
            }
            $identity = $this->authentication->authenticate($body['email'], $body['password']);
            $session = $this->sessions->rotate(
                $identity->userId,
                null,
                is_string($request->attribute('remoteIp')) ? $request->attribute('remoteIp') : null,
                $request->header('User-Agent'),
            );
            return Response::success([
                'identity' => $this->identityData($identity),
                'csrfToken' => $session->csrfToken,
                'expiresAt' => $session->expiresAt,
            ], headers: ['Set-Cookie' => $this->sessionCookie($session->token)]);
        } catch (IdentityException) {
            return Response::error(401, 'invalid_credentials', 'Email or password is invalid.');
        } catch (\JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        }
    }

    public function current(Request $request): Response
    {
        $identity = $request->attribute('identity');
        $session = $request->attribute('session');
        if (!$identity instanceof AuthenticatedIdentity || !$session instanceof AuthenticatedSession) {
            return Response::error(401, 'unauthenticated', 'Authentication is required.');
        }
        return Response::success($this->identityData($identity) + ['csrfToken' => $this->sessions->issueCsrf($session->sessionId)]);
    }

    public function logout(Request $request): Response
    {
        $session = $request->attribute('session');
        if (!$session instanceof AuthenticatedSession) {
            return Response::error(401, 'unauthenticated', 'Authentication is required.');
        }
        $this->sessions->revoke($session->sessionId);
        return Response::success(['status' => 'signed_out'], headers: ['Set-Cookie' => $this->sessionCookie('', 0)]);
    }

    public function requestPasswordReset(Request $request): Response
    {
        if ($this->passwordResets === null) {
            return Response::error(503, 'password_reset_unavailable', 'Password reset is temporarily unavailable.');
        }
        try {
            $body = $request->jsonBody();
            if (!is_string($body['email'] ?? null)) {
                return Response::error(422, 'validation_failed', 'Email is required.');
            }
            $this->passwordResets->request($body['email']);
            return Response::success(['status' => 'accepted'], status: 202);
        } catch (IdentityException $exception) {
            if ($exception->reason === 'password_reset_unavailable') {
                return Response::error(503, $exception->reason, 'Password reset is temporarily unavailable.');
            }
            return Response::success(['status' => 'accepted'], status: 202);
        } catch (\JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        }
    }

    public function completePasswordReset(Request $request): Response
    {
        if ($this->passwordResets === null) {
            return Response::error(503, 'password_reset_unavailable', 'Password reset is temporarily unavailable.');
        }
        try {
            $body = $request->jsonBody();
            if (!is_string($body['token'] ?? null) || !is_string($body['password'] ?? null)) {
                return Response::error(422, 'validation_failed', 'Token and password are required.');
            }
            $this->passwordResets->complete($body['token'], $body['password']);
            return Response::success(['status' => 'password_updated']);
        } catch (IdentityException $exception) {
            return Response::error(400, $exception->reason, 'The password reset token is invalid or expired.');
        } catch (\InvalidArgumentException $exception) {
            return Response::error(422, 'validation_failed', $exception->getMessage());
        } catch (\JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        }
    }

    /** @return array{userId:string,email:string,memberships:list<array{organisationId:string,role:string}>} */
    private function identityData(AuthenticatedIdentity $identity): array
    {
        return ['userId' => $identity->userId, 'email' => $identity->email, 'memberships' => $identity->memberships];
    }

    private function sessionCookie(string $token, int $maxAge = 43_200): string
    {
        return 'architex_session=' . $token . '; Path=/; Max-Age=' . $maxAge . '; HttpOnly; Secure; SameSite=Lax';
    }
}

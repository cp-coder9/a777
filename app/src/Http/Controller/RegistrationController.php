<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\IdentityException;
use Architex\Platform\Identity\RegisterOrganisation;
use Architex\Platform\Identity\RegistrationGateway;
use InvalidArgumentException;

final readonly class RegistrationController
{
    public function __construct(private RegistrationGateway $registrations) {}

    public function create(Request $request): Response
    {
        try {
            $body = $request->jsonBody();
            $body['ipAddress'] = is_string($request->attribute('remoteIp')) ? $request->attribute('remoteIp') : null;
            $body['deviceMetadata'] = ['userAgent' => $request->header('User-Agent')];
            $pending = $this->registrations->register(RegisterOrganisation::fromArray($body));
            return Response::success([
                'userId' => $pending->userId,
                'organisationId' => $pending->organisationId,
                'status' => $pending->status,
            ], status: 201);
        } catch (InvalidArgumentException $exception) {
            return Response::error(422, 'validation_failed', $exception->getMessage());
        } catch (IdentityException $exception) {
            if ($exception->reason === 'registration_email_unavailable') {
                return Response::error(503, $exception->reason, 'Registration is temporarily unavailable.');
            }
            $status = $exception->reason === 'registration_conflict' ? 409 : 422;
            return Response::error($status, $exception->reason, $exception->getMessage());
        } catch (\JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        }
    }

    public function verifyEmail(Request $request): Response
    {
        try {
            $body = $request->jsonBody();
            if (!is_string($body['token'] ?? null)) {
                return Response::error(422, 'validation_failed', 'Verification token is required.');
            }
            $identity = $this->registrations->verifyEmail($body['token']);
            return Response::success(['userId' => $identity->userId, 'status' => 'active']);
        } catch (IdentityException) {
            return Response::error(400, 'verification_invalid', 'The email verification token is invalid or expired.');
        } catch (\JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        }
    }
}

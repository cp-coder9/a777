<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Trust\TrustException;
use Architex\Platform\Trust\VerificationService;
use JsonException;

final readonly class TrustController
{
    public function __construct(private VerificationService $service) {}

    public function profile(Request $request): Response
    {
        $scope = $this->scope($request);
        if ($scope instanceof Response) return $scope;
        $profile = $this->service->profileForUser($scope[0], $scope[1]);
        return $profile === null ? Response::error(404, 'profile_not_found', 'Professional profile not found.') : Response::success($profile);
    }

    public function saveProfile(Request $request): Response
    {
        return $this->write($request, fn(array $body, string $organisationId, string $userId): Response => Response::success($this->service->saveProfile($organisationId, $userId, $body), status: 200));
    }

    public function verifications(Request $request): Response
    {
        $scope = $this->scope($request);
        return $scope instanceof Response ? $scope : Response::success($this->service->listForUser($scope[0], $scope[1]));
    }

    public function reviewQueue(Request$request):Response
    {
        $scope=$this->scope($request);return$scope instanceof Response?$scope:Response::success($this->service->reviewQueue($scope[0],$scope[1]));
    }

    public function submit(Request $request): Response
    {
        return $this->write($request, fn(array $body, string $organisationId, string $userId): Response => Response::success($this->service->submit($organisationId, $userId, $body), status: 201));
    }

    public function review(Request $request): Response
    {
        $verificationId = $request->attribute('verificationId');
        if (!is_string($verificationId) || preg_match('/\A[a-f0-9]{32}\z/i', $verificationId) !== 1) return Response::error(404, 'verification_not_found', 'Verification not found.');
        return $this->write($request, fn(array $body, string $organisationId, string $userId): Response => Response::success($this->service->review($organisationId, $userId, strtolower($verificationId), $body)));
    }

    public function directory(Request $request): Response
    {
        return Response::success($this->service->searchDirectory($request->query('profession'), $request->query('serviceArea')));
    }

    /** @param callable(array<string,mixed>,string,string):Response $operation */
    private function write(Request $request, callable $operation): Response
    {
        try {
            $scope = $this->scope($request);
            if ($scope instanceof Response) return $scope;
            return $operation($request->jsonBody(), $scope[0], $scope[1]);
        } catch (JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        } catch (TrustException $exception) {
            $status = match ($exception->reason) {
                'verification_not_found','evidence_file_not_found','membership_not_found' => 404,
                'review_permission_denied' => 403,
                default => 422,
            };
            return Response::error($status, $exception->reason, 'The trust workflow request could not be completed.');
        }
    }

    /** @return array{string,string}|Response */
    private function scope(Request $request): array|Response
    {
        $organisationId = $request->attribute('organisationId');
        $identity = $request->attribute('identity');
        return is_string($organisationId) && $identity instanceof AuthenticatedIdentity
            ? [$organisationId, $identity->userId]
            : Response::error(401, 'authentication_required', 'Authentication is required.');
    }
}

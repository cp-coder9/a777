<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Demo\DemoException;
use Architex\Platform\Demo\DemoSandboxService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class DemoSandboxController
{
    public function __construct(private DemoSandboxService $service) {}

    public function ensure(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->service->ensure(...$scope)); }
    public function state(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->service->state($scope[0], $scope[1])); }
    public function projects(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->service->projects($scope[0], $scope[1])); }
    public function project(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->service->project($scope[0], $scope[1], $this->projectId($request))); }
    public function learning(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->service->learning($scope[0], $scope[1])); }
    public function role(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->selectRole($scope[0], $scope[1], is_string($body['role'] ?? null) ? $body['role'] : '')); }
    public function reset(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->reset($scope[0], $scope[1], $scope[2], $body)); }

    /** @param callable(array{string,string,string}):array<mixed> $operation */
    private function read(Request $request, callable $operation): Response
    {
        $scope = $this->scope($request);
        if ($scope instanceof Response) return $scope;
        try { return Response::success($operation($scope)); }
        catch (DemoException $exception) { return $this->error($exception); }
    }

    /** @param callable(array<string,mixed>,array{string,string,string}):array<mixed> $operation */
    private function write(Request $request, callable $operation): Response
    {
        $scope = $this->scope($request);
        if ($scope instanceof Response) return $scope;
        try { return Response::success($operation($request->jsonBody(), $scope)); }
        catch (JsonException) { return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.'); }
        catch (DemoException $exception) { return $this->error($exception); }
    }

    /** @return array{string,string,string}|Response */
    private function scope(Request $request): array|Response
    {
        $organisation = $request->attribute('organisationId');
        $identity = $request->attribute('identity');
        $role = $request->attribute('role');
        return is_string($organisation) && $identity instanceof AuthenticatedIdentity && is_string($role)
            ? [$organisation, $identity->userId, $role]
            : Response::error(401, 'authentication_required', 'Authentication is required.');
    }

    private function projectId(Request $request): string
    {
        $id = $request->attribute('projectId');
        if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/i', $id) !== 1) throw new DemoException('demo_project_not_visible');
        return strtolower($id);
    }

    private function error(DemoException $exception): Response
    {
        $reason = $exception->getMessage();
        $status = match ($reason) {
            'demo_sandbox_not_found', 'demo_project_not_visible' => 404,
            'demo_membership_required', 'demo_reset_owner_required' => 403,
            'demo_role_invalid', 'demo_reset_confirmation_required', 'demo_reset_reason_required' => 422,
            default => 422,
        };
        return Response::error($status, $reason, match ($reason) {
            'demo_sandbox_not_found' => 'Demo sandbox not found.',
            'demo_project_not_visible' => 'Project is not visible in the active demo role.',
            'demo_membership_required' => 'An active demo organisation membership is required.',
            'demo_reset_owner_required' => 'Only the demo sandbox owner may reset it.',
            default => 'The demo sandbox request could not be completed.',
        });
    }
}

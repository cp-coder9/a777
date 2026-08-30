<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\NuancedQc\NuancedQcException;
use Architex\Platform\NuancedQc\NuancedQcService;
use JsonException;

final readonly class NuancedQcController
{
    public function __construct(private NuancedQcService $service) {}
    public function workspace(Request $request): Response { return $this->read($request, fn (array $scope): array => $this->service->workspace($scope[0], $scope[1], $scope[2], $this->queryId($request, 'projectId'))); }
    public function createRun(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->createRun($scope[0], $scope[1], $scope[2], $body), 201); }
    public function recordSummary(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->recordTechnicalSummary($scope[0], $scope[1], $scope[2], $this->pathId($request, 'runId'), $body)); }
    public function recordCategory(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->recordCategoryResult($scope[0], $scope[1], $scope[2], $this->pathId($request, 'runId'), $body), 201); }
    public function addFinding(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->addFinding($scope[0], $scope[1], $scope[2], $this->pathId($request, 'runId'), $body), 201); }
    public function linkEvidence(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->linkEvidence($scope[0], $scope[1], $scope[2], $this->pathId($request, 'runId'), $body), 201); }
    public function createRetest(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->createRetest($scope[0], $scope[1], $scope[2], $this->pathId($request, 'findingId'), $body), 201); }
    public function completeRetest(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->completeRetest($scope[0], $scope[1], $scope[2], $this->pathId($request, 'retestId'), $body)); }
    public function acceptRisk(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->acceptRisk($scope[0], $scope[1], $scope[2], $this->pathId($request, 'riskId'), $body)); }
    public function signOff(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->signOff($scope[0], $scope[1], $scope[2], $this->pathId($request, 'runId'), $body), 201); }
    public function assignRole(Request $request): Response { return $this->write($request, fn (array $body, array $scope): array => $this->service->assignRole($scope[0], $scope[1], $scope[2], $body)); }

    private function read(Request $request, callable $operation): Response { try { $scope = $this->scope($request); return $scope instanceof Response ? $scope : Response::success($operation($scope)); } catch (NuancedQcException $error) { return $this->error($error); } }
    private function write(Request $request, callable $operation, int $status = 200): Response { try { $scope = $this->scope($request); if ($scope instanceof Response) return $scope; return Response::success($operation($request->jsonBody(), $scope), status: $status); } catch (JsonException) { return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.'); } catch (NuancedQcException $error) { return $this->error($error); } }
    /** @return array{string,string,string}|Response */ private function scope(Request $request): array|Response { $organisation = $request->attribute('organisationId'); $identity = $request->attribute('identity'); $role = $request->attribute('role'); return is_string($organisation) && $identity instanceof AuthenticatedIdentity && is_string($role) ? [$organisation, $identity->userId, $role] : Response::error(401, 'authentication_required', 'Authentication is required.'); }
    private function pathId(Request $request, string $key): string { $id = $request->attribute($key); if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/i', $id) !== 1) throw new NuancedQcException('nuanced_qc_not_found'); return strtolower($id); }
    private function queryId(Request $request, string $key): ?string { $id = $request->query($key); if ($id === null || $id === '') return null; if (preg_match('/\A[a-f0-9]{32}\z/i', $id) !== 1) throw new NuancedQcException('nuanced_qc_project_not_found'); return strtolower($id); }
    private function error(NuancedQcException $error): Response { $status = match (true) { str_ends_with($error->reason, '_not_found') => 404, str_ends_with($error->reason, '_forbidden') || str_ends_with($error->reason, '_membership_required') => 403, str_contains($error->reason, '_conflict') || str_contains($error->reason, '_open_blocker') || str_contains($error->reason, '_unreviewed_gap') || str_ends_with($error->reason, '_confirmation_required') => 409, default => 422 }; return Response::error($status, $error->reason, 'The nuanced QC request could not be completed.'); }
}

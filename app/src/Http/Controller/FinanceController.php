<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Finance\CommercialControlException;
use Architex\Platform\Finance\CommercialControlService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;
use Architex\Infrastructure\Integration\IntegrationException;

final readonly class FinanceController
{
    public function __construct(private CommercialControlService $service) {}

    public function workspace(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->service->workspace($scope[0], $scope[1], $this->id($request, 'projectId', 'commercial_project_not_found'))); }
    public function createBaseline(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->createBaseline($scope[0], $scope[1], $scope[2], $this->id($request, 'projectId', 'commercial_project_not_found'), $body), 201); }
    public function createSchedule(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->createSchedule($scope[0], $scope[1], $scope[2], $this->id($request, 'baselineId', 'commercial_baseline_not_found'), $body), 201); }
    public function createVariation(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->createVariation($scope[0], $scope[1], $scope[2], $this->id($request, 'baselineId', 'commercial_baseline_not_found'), $body), 201); }
    public function submitVariation(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->submitVariation($scope[0], $scope[1], $scope[2], $this->id($request, 'variationId', 'commercial_variation_not_found'), $this->version($body), ($body['humanConfirmed'] ?? false) === true, is_string($body['reason'] ?? null) ? $body['reason'] : '')); }
    public function decideVariation(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->decideVariation($scope[0], $scope[1], $scope[2], $this->id($request, 'variationId', 'commercial_variation_not_found'), $body), 201); }
    public function submitClaim(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->submitClaim($scope[0], $scope[1], $scope[2], $this->id($request, 'baselineId', 'commercial_baseline_not_found'), $body), 201); }
    public function certifyClaim(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->certifyClaim($scope[0], $scope[1], $scope[2], $this->id($request, 'claimId', 'commercial_claim_not_found'), $body), 201); }
    public function retention(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->service->retentionBalance($scope[0], $scope[1], $this->id($request, 'baselineId', 'commercial_baseline_not_found'))); }
    public function createCashflow(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->createCashflowSnapshot($scope[0], $scope[1], $scope[2], $this->id($request, 'baselineId', 'commercial_baseline_not_found'), ($body['humanConfirmed'] ?? false) === true, is_string($body['reason'] ?? null) ? $body['reason'] : ''), 201); }
    public function createRetentionRelease(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->createRetentionReleaseRequest($scope[0], $scope[1], $scope[2], $this->id($request, 'baselineId', 'commercial_baseline_not_found'), $body), 201); }
    public function approveRetentionRelease(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->approveRetentionRelease($scope[0], $scope[1], $scope[2], $this->id($request, 'retentionReleaseId', 'retention_release_not_found'), $body), 201); }
    public function registerProvider(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->registerProvider($scope[0], $scope[1], $scope[2], $body), 201); }
    public function createReleaseRequest(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->createReleaseRequest($scope[0], $scope[1], $scope[2], $this->id($request, 'certificateRevisionId', 'commercial_certificate_revision_not_found'), $body), 201); }
    public function approveReleaseRequest(Request $request): Response { return $this->write($request, fn(array $body, array $scope): array => $this->service->approveReleaseRequest($scope[0], $scope[1], $scope[2], $this->id($request, 'releaseRequestId', 'provider_release_request_not_found'), $body), 201); }
    public function providerCapabilities(Request $request): Response { return $this->read($request, fn(array $scope): array => $this->service->providerCapabilityDiscovery($scope[0],$scope[1],$this->id($request,'providerId','financial_provider_not_found'))); }
    public function submitRelease(Request $request): Response { return $this->write($request, fn(array $body,array $scope): array => $this->service->submitReleaseRequest($scope[0],$scope[1],$scope[2],$this->id($request,'releaseRequestId','provider_release_request_not_found'),$body)); }
    public function providerWebhook(Request $request): Response
    {
        try { return Response::success($this->service->recordProviderWebhook($this->id($request, 'providerId', 'financial_provider_not_found'), $request->body(), (string)($request->header('X-Architex-Provider-Timestamp') ?? ''), (string)($request->header('X-Architex-Provider-Signature') ?? '')), status: 202); }
        catch (CommercialControlException $exception) { return $this->error($exception); }
        catch (IntegrationException $exception) { return $this->integrationError($exception); }
    }

    /** @param callable(array{string,string,string}):array<string,mixed> $operation */
    private function read(Request $request, callable $operation): Response
    {
        try { $scope = $this->scope($request); return $scope instanceof Response ? $scope : Response::success($operation($scope)); }
        catch (CommercialControlException $exception) { return $this->error($exception); }
        catch (IntegrationException $exception) { return $this->integrationError($exception); }
    }

    /** @param callable(array<string,mixed>,array{string,string,string}):array<string,mixed> $operation */
    private function write(Request $request, callable $operation, int $status = 200): Response
    {
        try { $scope = $this->scope($request); if ($scope instanceof Response) return $scope; return Response::success($operation($request->jsonBody(), $scope), status: $status); }
        catch (JsonException) { return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.'); }
        catch (CommercialControlException $exception) { return $this->error($exception); }
        catch (IntegrationException $exception) { return $this->integrationError($exception); }
    }

    /** @return array{string,string,string}|Response */
    private function scope(Request $request): array|Response
    {
        $organisationId = $request->attribute('organisationId'); $identity = $request->attribute('identity'); $role = $request->attribute('role');
        return is_string($organisationId) && $identity instanceof AuthenticatedIdentity && is_string($role) ? [$organisationId, $identity->userId, $role] : Response::error(401, 'authentication_required', 'Authentication is required.');
    }

    private function id(Request $request, string $name, string $reason): string { $id = $request->attribute($name); if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/i', $id) !== 1) throw new CommercialControlException($reason); return strtolower($id); }
    private function integrationError(IntegrationException $exception): Response { return Response::error($exception->httpStatus,$exception->reason,'The configured provider integration could not be verified.'); }
    /** @param array<string,mixed> $body */ private function version(array $body): int { $version = $body['expectedVersion'] ?? null; if (!is_int($version) || $version < 1) throw new CommercialControlException('commercial_variation_version_required'); return $version; }
    private function error(CommercialControlException $exception): Response { $status = match ($exception->reason) { 'commercial_project_not_found','commercial_baseline_not_found','commercial_variation_not_found','commercial_claim_not_found','commercial_certificate_revision_not_found','financial_provider_not_found','provider_release_request_not_found','retention_release_not_found' => 404, 'financial_provider_webhook_signature_invalid' => 401, 'financial_provider_webhook_unavailable' => 503, 'commercial_permission_denied','commercial_variation_approval_permission_denied','provider_release_approval_permission_denied' => 403, 'commercial_variation_version_conflict','commercial_variation_approval_conflict','provider_release_approval_conflict','retention_release_approval_conflict','provider_release_version_conflict','financial_provider_webhook_event_conflict','financial_provider_webhook_reference_pending' => 409, default => 422 }; return Response::error($status, $exception->reason, 'The finance and commercial-control request could not be completed.'); }
}

<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Documents\DocumentException;
use Architex\Platform\Documents\DocumentTransmittalService;
use Architex\Platform\Documents\DrawingIntelligenceService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use JsonException;

final readonly class DocumentEvidenceController
{
    public function __construct(private DocumentTransmittalService $transmittals, private DrawingIntelligenceService $candidates) {}

    public function transmittalIndex(Request $request): Response
    {
        $scope = $this->scope($request); if ($scope instanceof Response) return $scope;
        try { return Response::success($this->transmittals->list($scope[0], $scope[1], $this->queryId($request, 'projectId', 'transmittal_project_required'))); }
        catch (DocumentException $exception) { return $this->error($exception); }
    }

    public function transmittalShow(Request $request): Response
    {
        $scope = $this->scope($request); if ($scope instanceof Response) return $scope;
        try { return Response::success($this->transmittals->get($scope[0], $scope[1], $this->routeId($request, 'transmittalId', 'transmittal_not_found'))); }
        catch (DocumentException $exception) { return $this->error($exception); }
    }

    public function transmittalCreate(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): Response => Response::success($this->transmittals->create($scope[0], $scope[1], $scope[2], $body), status: 201));
    }

    public function transmittalIssue(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): Response => Response::success($this->transmittals->issue(
            $scope[0], $scope[1], $scope[2], $this->routeId($request, 'transmittalId', 'transmittal_not_found'),
            ($body['humanConfirmed'] ?? false) === true, is_string($body['reason'] ?? null) ? $body['reason'] : '',
        )));
    }

    public function candidateIndex(Request $request): Response
    {
        $scope = $this->scope($request); if ($scope instanceof Response) return $scope;
        try { return Response::success($this->candidates->list($scope[0], $scope[1], $this->queryId($request, 'projectId', 'drawing_intelligence_project_not_found'))); }
        catch (DocumentException $exception) { return $this->error($exception); }
    }

    public function candidateShow(Request $request): Response
    {
        $scope = $this->scope($request); if ($scope instanceof Response) return $scope;
        try { return Response::success($this->candidates->get($scope[0], $scope[1], $this->routeId($request, 'candidateId', 'drawing_intelligence_not_found'))); }
        catch (DocumentException $exception) { return $this->error($exception); }
    }

    public function candidateCreate(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): Response => Response::success($this->candidates->create($scope[0], $scope[1], $scope[2], $body), status: 201));
    }

    public function candidateReview(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): Response => Response::success($this->candidates->review(
            $scope[0], $scope[1], $scope[2], $this->routeId($request, 'candidateId', 'drawing_intelligence_not_found'),
            is_string($body['decision'] ?? null) ? $body['decision'] : '', ($body['humanConfirmed'] ?? false) === true,
            is_string($body['reason'] ?? null) ? $body['reason'] : '',
        )));
    }

    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */
    private function write(Request $request, callable $operation): Response
    {
        try {
            $scope = $this->scope($request); if ($scope instanceof Response) return $scope;
            return $operation($request->jsonBody(), $scope);
        } catch (JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        } catch (DocumentException $exception) {
            return $this->error($exception);
        }
    }

    /** @return array{string,string,string}|Response */
    private function scope(Request $request): array|Response
    {
        $organisationId = $request->attribute('organisationId');
        $identity = $request->attribute('identity');
        $role = $request->attribute('role');
        return is_string($organisationId) && $identity instanceof AuthenticatedIdentity && is_string($role)
            ? [$organisationId, $identity->userId, $role]
            : Response::error(401, 'authentication_required', 'Authentication is required.');
    }

    private function routeId(Request $request, string $name, string $reason): string { return $this->id($request->attribute($name), $reason); }
    private function queryId(Request $request, string $name, string $reason): string { return $this->id($request->query($name), $reason); }
    private function id(mixed $id, string $reason): string
    {
        if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/i', $id) !== 1) throw new DocumentException($reason);
        return strtolower($id);
    }

    private function error(DocumentException $exception): Response
    {
        if (in_array($exception->reason, ['transmittal_issue_confirmation_required', 'drawing_intelligence_review_confirmation_required'], true)) {
            return Response::error(422, 'human_confirmation_required', 'Confirm that you personally checked this exact evidence.');
        }
        $status = match ($exception->reason) {
            'transmittal_not_found', 'transmittal_project_not_found', 'drawing_intelligence_not_found',
            'drawing_intelligence_project_not_found', 'drawing_intelligence_revision_not_found' => 404,
            'transmittal_permission_denied', 'drawing_intelligence_permission_denied' => 403,
            'transmittal_not_draft', 'transmittal_code_exists', 'drawing_intelligence_not_reviewable' => 409,
            default => 422,
        };
        return Response::error($status, $exception->reason, 'The document evidence request could not be completed.');
    }
}

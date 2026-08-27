<?php
declare(strict_types=1);
namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Project\CreateProject;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Project\LifecycleService;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Project\ProjectPassportService;
use Architex\Platform\Project\ProjectWorkflowException;
use InvalidArgumentException;

final readonly class ProjectController
{
    public function __construct(private ProjectPassportService $projects, private LifecycleService $lifecycle) {}
    public function create(Request $request): Response
    {
        try {
            $body = $request->jsonBody();
            $organisationId = $request->attribute('organisationId');
            $identity = $request->attribute('identity');
            if (!is_string($organisationId) || !$identity instanceof AuthenticatedIdentity) return Response::error(401, 'authentication_required', 'Authentication is required.');
            $userId = $identity->userId;
            $project = $this->projects->create(new CreateProject($organisationId, $userId, (string)($body['projectCode'] ?? ''), (string)($body['projectName'] ?? ''), (string)($body['clientName'] ?? ''), (string)($body['municipality'] ?? ''), (string)($body['propertyReference'] ?? ''), (string)($body['propertyUse'] ?? ''), (string)($body['landUseNotes'] ?? ''), (string)($body['leadProfessionalRole'] ?? '')));
            return Response::success(['projectId'=>$project->projectId,'phase'=>$project->currentPhase->value,'version'=>$project->version], status: 201);
        } catch (InvalidArgumentException $exception) {
            return Response::error(422, 'validation_failed', $exception->getMessage());
        } catch (ProjectWorkflowException $exception) {
            return Response::error($exception->reason === 'project_code_conflict' ? 409 : 422, $exception->reason, $exception->getMessage());
        } catch (\JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        }
    }

    public function transition(Request $request): Response
    {
        try {
            $body = $request->jsonBody();
            $organisationId = $request->attribute('organisationId');
            $role = $request->attribute('role');
            $identity = $request->attribute('identity');
            if (!is_string($organisationId) || !is_string($role) || !$identity instanceof AuthenticatedIdentity) return Response::error(401, 'authentication_required', 'Authentication is required.');
            if (!is_string($body['projectId'] ?? null) || !is_string($body['targetPhase'] ?? null) || !is_int($body['expectedVersion'] ?? null)) return Response::error(422, 'validation_failed', 'projectId, targetPhase and expectedVersion are required.');
            $phase = ProjectPhase::tryFrom($body['targetPhase']);
            if ($phase === null) return Response::error(422, 'validation_failed', 'Unknown target phase.');
            $project = $this->lifecycle->transition(new ProjectContext($organisationId, $identity->userId, $body['projectId'], $role), $phase, $body['expectedVersion']);
            return Response::success(['projectId'=>$project->projectId,'phase'=>$project->currentPhase->value,'version'=>$project->version]);
        } catch (ProjectWorkflowException $exception) {
            $status = match ($exception->reason) { 'project_not_found' => 404, 'stale_project_version' => 409, default => 422 };
            return Response::error($status, $exception->reason, $exception->getMessage());
        } catch (\JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        }
    }
}

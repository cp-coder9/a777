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

    public function index(Request $request): Response
    {
        $scope = $this->scope($request);
        if ($scope instanceof Response) return $scope;
        return Response::success(array_map($this->serialize(...), $this->projects->list($scope[0], $scope[1]->userId)));
    }

    public function show(Request $request): Response
    {
        $scope = $this->scope($request);
        if ($scope instanceof Response) return $scope;
        $projectId = $request->attribute('projectId');
        if (!is_string($projectId)) return Response::error(404, 'not_found', 'Project not found.');
        $project = $this->projects->find(new ProjectContext($scope[0], $scope[1]->userId, strtolower($projectId), (string)$request->attribute('role')));
        return $project === null ? Response::error(404, 'not_found', 'Project not found.') : Response::success($this->serialize($project));
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

    /** @return array{string,AuthenticatedIdentity}|Response */
    private function scope(Request $request): array|Response
    {
        $organisationId = $request->attribute('organisationId');
        $identity = $request->attribute('identity');
        return is_string($organisationId) && $identity instanceof AuthenticatedIdentity
            ? [$organisationId, $identity]
            : Response::error(401, 'authentication_required', 'Authentication is required.');
    }

    /** @return array<string,mixed> */
    private function serialize(\Architex\Platform\Project\ProjectPassport $project): array
    {
        return ['id'=>$project->projectId,'organisationId'=>$project->organisationId,'projectCode'=>$project->projectCode,'name'=>$project->projectName,'clientName'=>$project->clientName,'municipality'=>$project->municipality,'propertyReference'=>$project->propertyReference,'propertyUse'=>$project->propertyUse,'landUseNotes'=>$project->landUseNotes,'leadProfessionalRole'=>$project->leadProfessionalRole,'phase'=>$project->currentPhase->value,'version'=>$project->version,'missingRecords'=>$project->missingRecords,'mayAdvance'=>$project->mayAdvance];
    }
}

<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Wingman\WingmanException;
use Architex\Platform\Wingman\WingmanService;
use JsonException;

final readonly class WingmanController
{
    public function __construct(private WingmanService $service) {}

    public function bootstrap(Request $request): Response
    {
        return $this->read($request, function (array $scope) use ($request): array {
            $route = $request->query('route');
            if (!is_string($route)) throw new WingmanException('invalid_route');
            return $this->service->bootstrap($scope[0], $scope[1], $scope[2], $route, $this->optionalId($request->query('projectId'), 'invalid_project'));
        });
    }

    public function message(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): array => $this->service->message($scope[0], $scope[1], $scope[2], $body));
    }

    public function conversation(Request $request): Response
    {
        return $this->read($request, fn (array $scope): array => $this->service->conversation($scope[0], $scope[1], $this->id($request, 'conversationId', 'wingman_conversation_not_found')));
    }

    public function feedback(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): array => $this->service->feedback($scope[0], $scope[1], $this->id($request, 'suggestionId', 'wingman_suggestion_not_found'), $body));
    }

    private function read(Request $request, callable $callback): Response
    {
        try {
            $scope = $this->scope($request);
            return $scope instanceof Response ? $scope : Response::success($callback($scope));
        } catch (WingmanException $exception) {
            return $this->error($exception);
        }
    }

    private function write(Request $request, callable $callback): Response
    {
        try {
            $scope = $this->scope($request);
            if ($scope instanceof Response) return $scope;
            return Response::success($callback($request->jsonBody(), $scope));
        } catch (JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain a JSON object.');
        } catch (WingmanException $exception) {
            return $this->error($exception);
        }
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

    private function id(Request $request, string $attribute, string $reason): string
    {
        $value = $request->attribute($attribute);
        if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/i', $value) !== 1) throw new WingmanException($reason);
        return strtolower($value);
    }

    private function optionalId(?string $value, string $reason): ?string
    {
        if ($value === null || $value === '') return null;
        if (preg_match('/\A[a-f0-9]{32}\z/i', $value) !== 1) throw new WingmanException($reason);
        return strtolower($value);
    }

    private function error(WingmanException $exception): Response
    {
        $status = match (true) {
            in_array($exception->reason, ['invalid_prompt','invalid_route','invalid_project','invalid_record','invalid_conversation','invalid_feedback','invalid_tool_arguments'], true) => 400,
            $exception->reason === 'context_denied' => 403,
            str_ends_with($exception->reason, '_not_found') => 404,
            str_ends_with($exception->reason, '_conflict') => 409,
            $exception->reason === 'rate_limited' => 429,
            in_array($exception->reason, ['configuration_required','provider_unavailable','provider_timeout','provider_response_invalid'], true) => 503,
            default => 422,
        };
        return Response::error($status, $exception->reason, 'The guided Wingman request could not be completed.');
    }
}

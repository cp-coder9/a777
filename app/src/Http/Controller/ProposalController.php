<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Proposal\ProposalException;
use Architex\Platform\Proposal\ProposalService;
use JsonException;

final readonly class ProposalController
{
    public function __construct(private ProposalService $proposals) {}

    public function list(Request $request): Response
    {
        $scope = $this->scope($request);
        return $scope instanceof Response ? $scope : Response::success($this->proposals->list($scope[0], $scope[1]));
    }

    public function get(Request $request): Response
    {
        $scope = $this->scope($request);
        if ($scope instanceof Response) return $scope;
        try {
            return Response::success($this->proposals->get($scope[0], $scope[1], $this->id($request)));
        } catch (ProposalException $exception) {
            return $this->error($exception);
        }
    }

    public function create(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): Response => Response::success($this->proposals->createDraft($scope[0], $scope[1], $scope[2], $body), status: 201));
    }

    public function issue(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): Response => Response::success($this->proposals->issue($scope[0], $scope[1], $scope[2], $this->id($request), $body)));
    }

    public function revise(Request $request): Response
    {
        return $this->write($request, fn (array $body, array $scope): Response => Response::success($this->proposals->revise($scope[0], $scope[1], $scope[2], $this->id($request), $body), status: 201));
    }

    public function publicView(Request $request): Response
    {
        try {
            return Response::success($this->proposals->publicView($this->token($request)));
        } catch (ProposalException $exception) {
            return $this->error($exception);
        }
    }

    public function accept(Request $request): Response
    {
        try {
            $body = $request->jsonBody();
            return Response::success($this->proposals->accept(
                $this->token($request),
                is_string($body['acceptedBy'] ?? null) ? $body['acceptedBy'] : '',
                ($body['responsibilityConfirmed'] ?? false) === true,
                is_string($request->attribute('remoteIp')) ? $request->attribute('remoteIp') : 'unknown',
                is_string($request->attribute('userAgent')) ? $request->attribute('userAgent') : ($request->header('User-Agent') ?? 'unknown'),
            ));
        } catch (JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        } catch (ProposalException $exception) {
            return $this->error($exception);
        }
    }

    /** @param callable(array<string,mixed>,array{string,string,string}):Response $operation */
    private function write(Request $request, callable $operation): Response
    {
        try {
            $scope = $this->scope($request);
            if ($scope instanceof Response) return $scope;
            return $operation($request->jsonBody(), $scope);
        } catch (JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain valid JSON.');
        } catch (ProposalException $exception) {
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

    private function id(Request $request): string
    {
        $id = $request->attribute('proposalId');
        if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/i', $id) !== 1) throw new ProposalException('proposal_not_found');
        return strtolower($id);
    }

    private function token(Request $request): string
    {
        $token = $request->attribute('token');
        if (!is_string($token)) throw new ProposalException('proposal_acceptance_invalid');
        return $token;
    }

    private function error(ProposalException $exception): Response
    {
        $status = match ($exception->reason) {
            'proposal_not_found', 'proposal_acceptance_invalid' => 404,
            'proposal_role_denied' => 403,
            'proposal_acceptance_expired' => 410,
            default => 422,
        };
        return Response::error($status, $exception->reason, 'The proposal request could not be completed.');
    }
}

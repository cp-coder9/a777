<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\ModuleRecords\ModuleRecordException;
use Architex\Platform\ModuleRecords\ModuleRecordInput;
use Architex\Platform\ModuleRecords\ModuleRecordService;
use JsonException;

/**
 * REST surface for module_records — the shared store behind the modules whose original
 * had no domain form. Reads are the same service call the scaffold projection used, so
 * a module can move from "read-only projection" to a real form without a second source
 * of truth.
 */
final readonly class ModuleRecordController
{
    public function __construct(private ModuleRecordService $service)
    {
    }

    public function catalogue(Request $request): Response
    {
        return $this->handle($request, 'catalogue');
    }

    public function index(Request $request): Response
    {
        return $this->handle($request, 'list');
    }

    public function show(Request $request): Response
    {
        return $this->handle($request, 'find');
    }

    public function create(Request $request): Response
    {
        return $this->handle($request, 'create');
    }

    public function update(Request $request): Response
    {
        return $this->handle($request, 'update');
    }

    public function transition(Request $request): Response
    {
        return $this->handle($request, 'transition');
    }

    public function events(Request $request): Response
    {
        return $this->handle($request, 'events');
    }

    public function destroy(Request $request): Response
    {
        return $this->handle($request, 'delete');
    }

    private function handle(Request $request, string $action): Response
    {
        $identity = $request->attribute('identity');
        $org = $request->attribute('organisationId');
        if (!$identity instanceof AuthenticatedIdentity || !is_string($org)) {
            return Response::error(401, 'authentication_required', 'Authentication and an organisation context are required.');
        }
        $id = $request->attribute('id');
        $id = is_string($id) ? $id : '';
        try {
            $needsBody = in_array($action, ['create', 'update', 'transition'], true);
            $body = [];
            if ($needsBody) {
                $body = $request->jsonBody();
            } elseif ($action === 'delete') {
                // A delete carries its concurrency precondition in an optional body; an empty
                // body is still a bad request, just not a JSON syntax error.
                try {
                    $body = $request->jsonBody();
                } catch (JsonException) {
                    $body = [];
                }
            }
            $data = match ($action) {
                'catalogue' => ['modules' => $this->service->catalogue(), 'baseFields' => \Architex\Platform\ModuleRecords\ModuleRecordSchema::BASE_FIELDS],
                'list' => $this->service->list(
                    $org,
                    $identity->userId,
                    self::query($request, 'moduleKey'),
                    self::query($request, 'projectId'),
                    self::query($request, 'status'),
                    self::intQuery($request, 'limit', 100),
                ),
                'find' => $this->service->find($org, $identity->userId, $id),
                'create' => $this->service->create($org, $identity->userId, ModuleRecordInput::fromArray($body)),
                'update' => $this->service->update($org, $identity->userId, $id, ModuleRecordInput::fromArray($body)),
                'transition' => $this->service->transition(
                    $org,
                    $identity->userId,
                    $id,
                    self::string($body, 'toStatus'),
                    self::string($body, 'reason'),
                    self::int($body, 'expectedRevision'),
                    self::bool($body, 'humanConfirmed'),
                ),
                'events' => $this->service->events($org, $identity->userId, $id),
                'delete' => $this->delete($org, $identity->userId, $id, $body),
            };
            return match ($action) {
                'create' => Response::success($data, status: 201),
                'delete' => Response::success(['deleted' => true]),
                default => Response::success($data),
            };
        } catch (JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain a JSON object.');
        } catch (ModuleRecordException $exception) {
            return Response::error($exception->status, $exception->reason, self::message($exception->reason));
        }
    }

    private function delete(string $org, string $userId, string $id, array $body): array
    {
        $this->service->remove($org, $userId, $id, self::int($body, 'expectedRevision'));
        return ['deleted' => true];
    }

    private static function message(string $reason): string
    {
        return match ($reason) {
            'unknown_module' => 'That module is not registered for recorded workflows.',
            'record_not_found', 'project_not_found' => 'The record was not found in this organisation.',
            'revision_conflict' => 'Another change happened first. Reload the record before retrying.',
            'confirmation_required' => 'This decision requires an explicit human confirmation.',
            'reason_required' => 'A reason is required for this status change.',
            'unknown_evidence' => 'The evidence files must be uploaded to this project first.',
            default => 'The record could not be saved as submitted.',
        };
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);
        if (is_string($value) && $value !== '') {
            return $value;
        }
        return null;
    }

    private static function intQuery(Request $request, string $key, int $default): int
    {
        $value = $request->query($key);
        return is_string($value) && preg_match('/\A\d{1,4}\z/', $value) === 1 ? (int) $value : $default;
    }

    /** @param array<string,mixed> $body */
    private static function string(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value)) {
            throw new ModuleRecordException($key === 'toStatus' ? 'invalid_status' : 'reason_required');
        }
        return trim($value);
    }

    /** @param array<string,mixed> $body */
    private static function int(array $body, string $key): int
    {
        $value = $body[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new ModuleRecordException('expected_revision_required');
        }
        return $value;
    }

    /** @param array<string,mixed> $body */
    private static function bool(array $body, string $key): bool
    {
        $value = $body[$key] ?? false;
        return $value === true || $value === 1 || $value === '1';
    }
}

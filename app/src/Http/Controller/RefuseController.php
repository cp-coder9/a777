<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Refuse\RefuseException;
use Architex\Platform\Refuse\RefuseInput;
use Architex\Platform\Refuse\RefuseRulePackInput;
use Architex\Platform\Refuse\RefuseService;
use JsonException;

/**
 * REST surface for the municipal refuse area calculator.
 *
 * `catalogue` exists so the screen never hard-codes a vocabulary: the criterion keys, the known
 * municipalities and the active rule pack — including whether it is verified — come from the
 * server that will do the judging.
 */
final readonly class RefuseController
{
    public function __construct(private RefuseService $service)
    {
    }

    public function catalogue(Request $request): Response
    {
        return $this->handle($request, 'catalogue');
    }

    public function rulePacks(Request $request): Response
    {
        return $this->handle($request, 'packs');
    }

    public function saveRulePack(Request $request): Response
    {
        return $this->handle($request, 'savePack');
    }

    public function reviewRulePack(Request $request): Response
    {
        return $this->handle($request, 'reviewPack');
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

    public function declare(Request $request): Response
    {
        return $this->handle($request, 'declare');
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
        $projectId = $request->attribute('projectId');
        $projectId = is_string($projectId) ? $projectId : '';
        try {
            $needsBody = in_array($action, ['savePack', 'reviewPack', 'create', 'update', 'declare'], true);
            $body = [];
            if ($needsBody) {
                $body = $request->jsonBody();
            } elseif ($action === 'delete') {
                try {
                    $body = $request->jsonBody();
                } catch (JsonException) {
                    $body = [];
                }
            }
            $data = match ($action) {
                'catalogue' => $this->catalogueData($org, $identity->userId),
                'packs' => $this->service->rulePacks($org, $identity->userId, self::query($request, 'municipalityKey')),
                'savePack' => $this->service->saveRulePack($org, $identity->userId, RefuseRulePackInput::fromArray($body)),
                'reviewPack' => $this->service->reviewRulePack($org, $identity->userId, $id, self::string($body, 'decision'), self::string($body, 'reason')),
                'list' => $this->service->list($org, $identity->userId, self::query($request, 'projectId'), self::query($request, 'status')),
                'find' => $this->service->find($org, $identity->userId, $id),
                'create' => $this->service->create($org, $identity->userId, $projectId, RefuseInput::fromArray($body)),
                'update' => $this->service->update($org, $identity->userId, $id, RefuseInput::fromArray($body)),
                'declare' => $this->service->declare($org, $identity->userId, $id, self::string($body, 'declarationNote'), self::int($body, 'expectedRevision')),
                'delete' => $this->delete($org, $identity->userId, $id, $body),
            };
            return match ($action) {
                'savePack', 'create' => Response::success($data, status: 201),
                'delete' => Response::success(['deleted' => true]),
                default => Response::success($data),
            };
        } catch (JsonException) {
            return Response::error(400, 'invalid_json', 'Request body must contain a JSON object.');
        } catch (RefuseException $exception) {
            return Response::error($exception->status, $exception->reason, self::message($exception->reason));
        }
    }

    /** @return array<string,mixed> */
    private function catalogueData(string $org, string $userId): array
    {
        return [
            'municipalities' => $this->service->rulePacks($org, $userId),
            'criteriaKeys' => [
                'max_collection_interval_days' => 'Maximum collection interval (days)',
                'min_vehicle_access_width_m' => 'Minimum collection vehicle access width (m)',
                'ventilation_strategies' => 'Accepted ventilation strategies',
                'drainage_required' => 'Wash-down drainage required',
            ],
            'ventilationStrategies' => ['natural', 'mechanical', 'none'],
            'defaults' => ['binCapacityLitres' => 240, 'generationRateLitresPerPersonDay' => 28.0, 'collectionIntervalDays' => 1],
        ];
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    private function delete(string $org, string $userId, string $id, array $body): array
    {
        $this->service->remove($org, $userId, $id, self::int($body, 'expectedRevision'));
        return ['deleted' => true];
    }

    private static function message(string $reason): string
    {
        return match ($reason) {
            'calculation_not_found', 'project_not_found', 'rule_pack_not_found' => 'Not found in this organisation.',
            'rule_pack_version_exists' => 'That rule pack version already exists for this municipality.',
            'revision_conflict' => 'Another change happened first. Reload before retrying.',
            'declared_calculation_is_immutable' => 'A declared calculation cannot be changed or deleted.',
            'source_reference_required' => 'A rule pack needs the source it came from before it can be stored.',
            'unknown_criterion' => 'That criterion is not one this calculator understands.',
            'reason_required' => 'A reason is required for this decision.',
            'declaration_note_required' => 'State the basis of the declaration before declaring.',
            'nothing_to_declare' => 'Nothing in this calculation could be judged, so there is nothing to declare. Load and verify a rule pack, or record the figures being compared.',
            default => 'The calculation could not be saved as submitted.',
        };
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string,mixed> $body */
    private static function string(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value)) {
            throw new RefuseException($key === 'decision' ? 'invalid_decision' : ($key === 'declarationNote' ? 'declaration_note_required' : 'reason_required'));
        }
        return trim($value);
    }

    /** @param array<string,mixed> $body */
    private static function int(array $body, string $key): int
    {
        $value = $body[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new RefuseException('expected_revision_required');
        }
        return $value;
    }
}

<?php

declare(strict_types=1);

namespace Architex\Platform\Refuse;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use PDOException;
use Throwable;

/**
 * Persistence for the municipal refuse area calculator.
 *
 * The service never computes anything itself: it resolves the rule pack that applies to the
 * municipality, hands the inputs and that pack to RefuseCalculator, and stores what came back —
 * including the rule pack version and status, so a saved calculation can always be explained
 * against the exact criteria that produced it.
 */
final readonly class RefuseService
{
    private const SELECT = 'SELECT c.id,LOWER(HEX(c.id)) id_hex,LOWER(HEX(c.project_id)) projectId,LOWER(HEX(c.rule_pack_id)) rulePackId,'
        .'c.rule_pack_version rulePackVersion,c.rule_pack_status rulePackStatus,c.title,c.municipality,c.building_type buildingType,'
        .'c.occupancy,c.gross_floor_area_m2 grossFloorAreaM2,c.generation_rate_litres_per_person_day generationRate,'
        .'c.collection_interval_days collectionIntervalDays,c.bin_capacity_litres binCapacityLitres,c.bins_provided binsProvided,'
        .'c.provided_storage_area_m2 providedStorageAreaM2,c.access_width_m accessWidthM,c.ventilation_strategy ventilationType,'
        .'c.drainage_provided drainageProvided,c.inputs,c.results,c.assessments,c.advisory,c.status,c.declaration_note declarationNote,'
        .'LOWER(HEX(c.declared_by)) declaredBy,c.declared_at declaredAt,c.revision,c.created_at createdAt,c.updated_at updatedAt '
        .'FROM refuse_calculations c';

    private const PACK_SELECT = 'SELECT p.id,LOWER(HEX(p.id)) id_hex,p.municipality_key municipalityKey,p.municipality_name municipalityName,'
        .'p.version,p.status,p.source_reference sourceReference,p.criteria,p.bin_footprint_m2 binFootprintM2,'
        .'p.default_bin_capacity_litres defaultBinCapacityLitres,p.storage_height_mm storageHeightMm,'
        .'p.circulation_allowance_percent circulationAllowancePercent,LOWER(HEX(p.reviewed_by)) reviewedBy,p.reviewed_at reviewedAt,'
        .'p.review_reason reviewReason,p.created_at createdAt,p.updated_at updatedAt FROM refuse_rule_packs p';

    public function __construct(private PDO $pdo)
    {
    }

    // ---- rule packs ---------------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public function rulePacks(string $organisationId, string $userId, ?string $municipalityKey = null): array
    {
        $this->assertMember($organisationId, $userId);
        $sql = self::PACK_SELECT.' WHERE p.organisation_id=UNHEX(:org)';
        $params = ['org' => $organisationId];
        if ($municipalityKey !== null) {
            $sql .= ' AND p.municipality_key=:key';
            $params['key'] = $municipalityKey;
        }
        $sql .= ' ORDER BY p.municipality_key ASC, p.created_at DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_map($this->hydratePack(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed> */
    public function saveRulePack(string $organisationId, string $userId, RefuseRulePackInput $input): array
    {
        $this->assertMember($organisationId, $userId);
        $id = Identifier::generate();
        $insert = $this->pdo->prepare('INSERT INTO refuse_rule_packs(id,organisation_id,municipality_key,municipality_name,version,status,source_reference,criteria,bin_footprint_m2,default_bin_capacity_litres,storage_height_mm,circulation_allowance_percent,created_by)'
            ." VALUES(UNHEX(:id),UNHEX(:org),:key,:name,:version,'review_required',:source,:criteria,:footprint,:capacity,:height,:circulation,UNHEX(:created_by))");
        try {
            $insert->execute([
                'id' => $id, 'org' => $organisationId, 'key' => $input->municipalityKey, 'name' => $input->municipalityName,
                'version' => $input->version, 'source' => $input->sourceReference,
                'criteria' => json_encode($input->criteria, JSON_THROW_ON_ERROR),
                'footprint' => $input->binFootprintM2, 'capacity' => $input->defaultBinCapacityLitres,
                'height' => $input->storageHeightMm, 'circulation' => $input->circulationAllowancePercent, 'created_by' => $userId,
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new RefuseException('rule_pack_version_exists', 409);
            }
            throw $exception;
        }
        (new AuditService($this->pdo))->append($organisationId, $userId, 'refuse.rule_pack.created', 'refuse_rule_pack', $id, [
            'municipalityKey' => $input->municipalityKey, 'version' => $input->version, 'criteria' => array_keys($input->criteria),
        ]);
        return $this->loadedPack($organisationId, $id);
    }

    /** A rule pack is only applied once a named professional has verified it, and says why. */
    public function reviewRulePack(string $organisationId, string $userId, string $packId, string $decision, string $reason): array
    {
        $this->assertMember($organisationId, $userId);
        $pack = $this->loadedPack($organisationId, $packId);
        $status = match ($decision) {
            'verify' => 'verified',
            'withdraw' => 'withdrawn',
            default => throw new RefuseException('invalid_decision'),
        };
        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw new RefuseException('reason_required');
        }
        $statement = $this->pdo->prepare('UPDATE refuse_rule_packs SET status=:status,reviewed_by=UNHEX(:user),reviewed_at=UTC_TIMESTAMP(6),review_reason=:reason WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:org)');
        $statement->execute(['status' => $status, 'user' => $userId, 'reason' => trim($reason), 'id' => $packId, 'org' => $organisationId]);
        if ($statement->rowCount() !== 1) {
            throw new RefuseException('rule_pack_not_found', 404);
        }
        (new AuditService($this->pdo))->append($organisationId, $userId, 'refuse.rule_pack.'.$status, 'refuse_rule_pack', $packId, [
            'municipalityKey' => $pack['municipalityKey'], 'version' => $pack['version'], 'reason' => trim($reason),
        ]);
        return $this->loadedPack($organisationId, $packId);
    }

    /**
     * The pack that governs a municipality: the newest verified version if one exists, otherwise
     * the newest draft — which the calculator will refuse to judge against.
     *
     * @return array<string,mixed>|null
     */
    public function activeRulePack(string $organisationId, string $municipalityName): ?array
    {
        $key = self::municipalityKey($municipalityName);
        $statement = $this->pdo->prepare(self::PACK_SELECT." WHERE p.organisation_id=UNHEX(:org) AND p.municipality_key=:key AND p.status IN ('verified','review_required') ORDER BY FIELD(p.status,'verified','review_required'), p.created_at DESC LIMIT 1");
        $statement->execute(['org' => $organisationId, 'key' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return $this->hydratePack($row);
    }

    // ---- calculations -------------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId, string $userId, ?string $projectId = null, ?string $status = null): array
    {
        $this->assertMember($organisationId, $userId);
        $sql = self::SELECT.' WHERE c.organisation_id=UNHEX(:org)';
        $params = ['org' => $organisationId];
        if ($projectId !== null) {
            if (preg_match('/\A[a-f0-9]{32}\z/i', $projectId) !== 1) {
                throw new RefuseException('invalid_project_id');
            }
            $sql .= ' AND c.project_id=UNHEX(:project)';
            $params['project'] = $projectId;
        }
        if ($status !== null) {
            if (!in_array($status, ['draft', 'calculated', 'declared'], true)) {
                throw new RefuseException('invalid_status');
            }
            $sql .= ' AND c.status=:status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY c.updated_at DESC, c.id DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_map($this->hydrateCalculation(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed> */
    public function find(string $organisationId, string $userId, string $id): array
    {
        $this->assertMember($organisationId, $userId);
        return $this->load($organisationId, $id);
    }

    /** @return array<string,mixed> */
    public function create(string $organisationId, string $userId, string $projectId, RefuseInput $input): array
    {
        $this->assertMember($organisationId, $userId);
        $this->assertProject($organisationId, $projectId);
        if ($input->clientRequestId !== null) {
            $existing = $this->byRequestId($organisationId, $input->clientRequestId);
            if ($existing !== null) {
                return $existing;
            }
        }
        $computed = $this->compute($organisationId, $input);
        $id = Identifier::generate();
        $insert = $this->pdo->prepare('INSERT INTO refuse_calculations(id,organisation_id,project_id,rule_pack_id,rule_pack_version,rule_pack_status,title,municipality,building_type,occupancy,gross_floor_area_m2,generation_rate_litres_per_person_day,collection_interval_days,bin_capacity_litres,bins_provided,provided_storage_area_m2,access_width_m,ventilation_strategy,drainage_provided,inputs,results,assessments,advisory,status,revision,client_request_id,created_by,updated_by)'
            ." VALUES(UNHEX(:id),UNHEX(:org),UNHEX(:project),UNHEX(:pack),:pack_version,:pack_status,:title,:municipality,:building_type,:occupancy,:floor_area,:rate,:interval,:capacity,:bins,:storage,:access,:ventilation,:drainage,:inputs,:results,:assessments,:advisory,:status,1,:request,UNHEX(:created_by),UNHEX(:updated_by))");
        $this->pdo->beginTransaction();
        try {
            $insert->execute($this->calculationParams($id, $organisationId, $projectId, $userId, $input, $computed)
                + ['pack' => $computed['packId'], 'pack_version' => $computed['packVersion'], 'pack_status' => $computed['packStatus'], 'status' => 'calculated']);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'refuse.calculation.created', 'refuse_calculation', $id, [
                'projectId' => $projectId, 'municipality' => $input->municipality,
                'rulePackVersion' => $computed['packVersion'], 'binsRequired' => $computed['results']['binsRequired'],
            ]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (($exception->errorInfo[1] ?? null) === 1062 && $input->clientRequestId !== null) {
                $existing = $this->byRequestId($organisationId, $input->clientRequestId);
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        return $this->load($organisationId, $id);
    }

    /** @return array<string,mixed> */
    public function update(string $organisationId, string $userId, string $id, RefuseInput $input): array
    {
        $this->assertMember($organisationId, $userId);
        $current = $this->load($organisationId, $id);
        if ($current['status'] === 'declared') {
            throw new RefuseException('declared_calculation_is_immutable', 409);
        }
        if ($input->expectedRevision === null) {
            throw new RefuseException('expected_revision_required');
        }
        $computed = $this->compute($organisationId, $input);
        $statement = $this->pdo->prepare('UPDATE refuse_calculations SET rule_pack_id=UNHEX(:pack),rule_pack_version=:pack_version,rule_pack_status=:pack_status,'
            .'title=:title,municipality=:municipality,building_type=:building_type,occupancy=:occupancy,gross_floor_area_m2=:floor_area,'
            .'generation_rate_litres_per_person_day=:rate,collection_interval_days=:interval,bin_capacity_litres=:capacity,bins_provided=:bins,'
            .'provided_storage_area_m2=:storage,access_width_m=:access,ventilation_strategy=:ventilation,drainage_provided=:drainage,'
            .'inputs=:inputs,results=:results,assessments=:assessments,advisory=:advisory,status=:status,revision=revision+1,updated_by=UNHEX(:updated_by)'
            .' WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:org) AND revision=:expected');
        $this->pdo->beginTransaction();
        try {
            // Bound explicitly rather than reusing calculationParams(): that helper carries the
            // INSERT's extra columns (created_by, client_request_id, project_id) which this UPDATE
            // does not reference, and PDO with emulation off rejects any mismatch between the
            // statement's placeholders and the bound array.
            $statement->execute([
                'pack' => $computed['packId'], 'pack_version' => $computed['packVersion'], 'pack_status' => $computed['packStatus'],
                'title' => $input->title, 'municipality' => $input->municipality, 'building_type' => $input->buildingType,
                'occupancy' => $input->occupancy, 'floor_area' => $input->grossFloorAreaM2, 'rate' => $input->generationRate,
                'interval' => $input->collectionIntervalDays, 'capacity' => $input->binCapacityLitres, 'bins' => $input->binsProvided,
                'storage' => $input->providedStorageAreaM2, 'access' => $input->accessWidthM, 'ventilation' => $input->ventilationType,
                'drainage' => $input->drainageProvided ? 1 : 0,
                'inputs' => json_encode($input->snapshot(), JSON_THROW_ON_ERROR),
                'results' => json_encode($computed['results'], JSON_THROW_ON_ERROR),
                'assessments' => json_encode($computed['assessments'], JSON_THROW_ON_ERROR),
                'advisory' => $computed['advisory'], 'status' => 'calculated',
                'id' => $id, 'org' => $organisationId,
                'expected' => $input->expectedRevision, 'updated_by' => $userId,
            ]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                throw new RefuseException('revision_conflict', 409);
            }
            (new AuditService($this->pdo))->append($organisationId, $userId, 'refuse.calculation.recalculated', 'refuse_calculation', $id, [
                'revision' => $input->expectedRevision + 1, 'rulePackVersion' => $computed['packVersion'],
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        return $this->load($organisationId, $id);
    }

    /**
     * Declaring is the moment the professional takes responsibility for the figures. It requires a
     * stated basis, and it is refused when nothing in the calculation could be judged — declaring a
     * page of "not assessed" rows is not a declaration of anything.
     */
    public function declare(string $organisationId, string $userId, string $id, string $note, int $expectedRevision): array
    {
        $this->assertMember($organisationId, $userId);
        $current = $this->load($organisationId, $id);
        if (trim($note) === '' || mb_strlen($note) > 500) {
            throw new RefuseException('declaration_note_required');
        }
        $judged = array_filter($current['assessments'], static fn (array $row): bool => $row['verdict'] !== 'not_assessed');
        if ($judged === []) {
            throw new RefuseException('nothing_to_declare');
        }
        $failing = array_filter($judged, static fn (array $row): bool => $row['verdict'] === 'fail');
        // declared_by and updated_by need distinct placeholders: prepared statements run with
        // emulation off, where a repeated named parameter is an invalid statement.
        $statement = $this->pdo->prepare("UPDATE refuse_calculations SET status='declared',declaration_note=:note,declared_by=UNHEX(:declared_by),declared_at=UTC_TIMESTAMP(6),revision=revision+1,updated_by=UNHEX(:updated_by) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:org) AND revision=:expected");
        $statement->execute(['note' => trim($note), 'declared_by' => $userId, 'updated_by' => $userId, 'id' => $id, 'org' => $organisationId, 'expected' => $expectedRevision]);
        if ($statement->rowCount() !== 1) {
            throw new RefuseException('revision_conflict', 409);
        }
        (new AuditService($this->pdo))->append($organisationId, $userId, 'refuse.calculation.declared', 'refuse_calculation', $id, [
            'rulePackVersion' => $current['rulePackVersion'], 'failingAssessments' => count($failing),
            'judgedAssessments' => count($judged), 'note' => trim($note),
        ]);
        return $this->load($organisationId, $id);
    }

    public function remove(string $organisationId, string $userId, string $id, int $expectedRevision): void
    {
        $this->assertMember($organisationId, $userId);
        $current = $this->load($organisationId, $id);
        if ($current['status'] === 'declared') {
            throw new RefuseException('declared_calculation_is_immutable', 409);
        }
        $statement = $this->pdo->prepare('DELETE FROM refuse_calculations WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:org) AND revision=:expected');
        $statement->execute(['id' => $id, 'org' => $organisationId, 'expected' => $expectedRevision]);
        if ($statement->rowCount() !== 1) {
            throw new RefuseException('revision_conflict', 409);
        }
        (new AuditService($this->pdo))->append($organisationId, $userId, 'refuse.calculation.deleted', 'refuse_calculation', $id, [
            'title' => $current['title'], 'status' => $current['status'],
        ]);
    }

    // ---- internals ----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function compute(string $organisationId, RefuseInput $input): array
    {
        $pack = $this->activeRulePack($organisationId, $input->municipality);
        $verified = $pack !== null && $pack['status'] === 'verified';
        $rule = $pack === null ? [] : [
            'binFootprintM2' => $pack['binFootprintM2'],
            'circulationAllowancePercent' => $pack['circulationAllowancePercent'],
            'criteria' => $pack['criteria'],
        ];
        $computed = RefuseCalculator::compute(
            $input->calculatorInputs(),
            $rule,
            $pack['version'] ?? null,
            $verified,
            $input->municipality,
        );
        return $computed + [
            'packId' => $pack['id'] ?? null,
            'packVersion' => $pack['version'] ?? null,
            'packStatus' => $pack['status'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $computed
     * @return array<string,mixed>
     */
    private function calculationParams(string $id, string $organisationId, string $projectId, string $userId, RefuseInput $input, array $computed): array
    {
        return [
            'id' => $id, 'org' => $organisationId, 'project' => $projectId,
            'title' => $input->title, 'municipality' => $input->municipality, 'building_type' => $input->buildingType,
            'occupancy' => $input->occupancy, 'floor_area' => $input->grossFloorAreaM2, 'rate' => $input->generationRate,
            'interval' => $input->collectionIntervalDays, 'capacity' => $input->binCapacityLitres, 'bins' => $input->binsProvided,
            'storage' => $input->providedStorageAreaM2, 'access' => $input->accessWidthM, 'ventilation' => $input->ventilationType,
            'drainage' => $input->drainageProvided ? 1 : 0,
            'inputs' => json_encode($input->snapshot(), JSON_THROW_ON_ERROR),
            'results' => json_encode($computed['results'], JSON_THROW_ON_ERROR),
            'assessments' => json_encode($computed['assessments'], JSON_THROW_ON_ERROR),
            'advisory' => $computed['advisory'],
            'request' => $input->clientRequestId,
            'created_by' => $userId, 'updated_by' => $userId,
        ];
    }

    /** @return array<string,mixed> */
    private function load(string $organisationId, string $id): array
    {
        if (preg_match('/\A[a-f0-9]{32}\z/i', $id) !== 1) {
            throw new RefuseException('calculation_not_found', 404);
        }
        $statement = $this->pdo->prepare(self::SELECT.' WHERE c.organisation_id=UNHEX(:org) AND c.id=UNHEX(:id)');
        $statement->execute(['org' => $organisationId, 'id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RefuseException('calculation_not_found', 404);
        }
        return $this->hydrateCalculation($row);
    }

    /** @return array<string,mixed> */
    private function loadedPack(string $organisationId, string $packId): array
    {
        if (preg_match('/\A[a-f0-9]{32}\z/i', $packId) !== 1) {
            throw new RefuseException('rule_pack_not_found', 404);
        }
        $statement = $this->pdo->prepare(self::PACK_SELECT.' WHERE p.organisation_id=UNHEX(:org) AND p.id=UNHEX(:id)');
        $statement->execute(['org' => $organisationId, 'id' => $packId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RefuseException('rule_pack_not_found', 404);
        }
        return $this->hydratePack($row);
    }

    /** @return array<string,mixed>|null */
    private function byRequestId(string $organisationId, string $clientRequestId): ?array
    {
        $statement = $this->pdo->prepare(self::SELECT.' WHERE c.organisation_id=UNHEX(:org) AND c.client_request_id=:request');
        $statement->execute(['org' => $organisationId, 'request' => $clientRequestId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrateCalculation($row) : null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrateCalculation(array $row): array
    {
        $row['id'] = (string) $row['id_hex'];
        unset($row['id_hex']);
        $row['revision'] = (int) $row['revision'];
        $row['occupancy'] = (int) $row['occupancy'];
        $row['collectionIntervalDays'] = (int) $row['collectionIntervalDays'];
        $row['binCapacityLitres'] = (int) $row['binCapacityLitres'];
        $row['binsProvided'] = $row['binsProvided'] === null ? null : (int) $row['binsProvided'];
        $row['drainageProvided'] = (bool) $row['drainageProvided'];
        $row['generationRate'] = (float) $row['generationRate'];
        foreach (['grossFloorAreaM2', 'providedStorageAreaM2', 'accessWidthM'] as $decimal) {
            $row[$decimal] = $row[$decimal] === null ? null : (float) $row[$decimal];
        }
        foreach (['inputs', 'results', 'assessments'] as $json) {
            $decoded = json_decode((string) ($row[$json] ?? ''), true);
            $row[$json] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydratePack(array $row): array
    {
        $row['id'] = (string) $row['id_hex'];
        unset($row['id_hex']);
        $row['binFootprintM2'] = (float) $row['binFootprintM2'];
        $row['defaultBinCapacityLitres'] = (int) $row['defaultBinCapacityLitres'];
        $row['storageHeightMm'] = (int) $row['storageHeightMm'];
        $row['circulationAllowancePercent'] = (int) $row['circulationAllowancePercent'];
        $decoded = json_decode((string) ($row['criteria'] ?? ''), true);
        $row['criteria'] = is_array($decoded) ? $decoded : [];
        $row['isVerified'] = $row['status'] === 'verified';
        return $row;
    }

    /** A stable key for a municipality name, so the same council always resolves to one pack set. */
    public static function municipalityKey(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? '';
        return trim($key, '-');
    }

    private function assertMember(string $organisationId, string $userId): void
    {
        if (preg_match('/\A[a-f0-9]{32}\z/i', $organisationId) !== 1 || preg_match('/\A[a-f0-9]{32}\z/i', $userId) !== 1) {
            throw new RefuseException('calculation_not_found', 404);
        }
        $statement = $this->pdo->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:org) AND user_id=UNHEX(:user) AND status='active'");
        $statement->execute(['org' => $organisationId, 'user' => $userId]);
        if ($statement->fetchColumn() === false) {
            throw new RefuseException('calculation_not_found', 404);
        }
    }

    private function assertProject(string $organisationId, string $projectId): void
    {
        if (preg_match('/\A[a-f0-9]{32}\z/i', $projectId) !== 1) {
            throw new RefuseException('invalid_project_id');
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM projects WHERE id=UNHEX(:project) AND organisation_id=UNHEX(:org)');
        $statement->execute(['project' => $projectId, 'org' => $organisationId]);
        if ($statement->fetchColumn() === false) {
            throw new RefuseException('project_not_found', 404);
        }
    }
}

<?php

declare(strict_types=1);

namespace Architex\Platform\ModuleRecords;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use PDOException;
use Throwable;

/**
 * Persistence for the modules whose v1.html original had no domain form.
 *
 * Scope rule: a record always belongs to the organisation; when it names a project that
 * project must belong to the same organisation and the actor must be an active member.
 * Evidence ids must name real rows in stored_files — a record cannot claim evidence that
 * was never uploaded.
 *
 * Concurrency: update/transition/delete require expectedRevision, matching the optimistic
 * concurrency contract the project editor already uses, so a stale screen cannot silently
 * overwrite someone else's decision.
 */
final readonly class ModuleRecordService
{
    private const SELECT = "SELECT r.id,LOWER(HEX(r.id)) id_hex,LOWER(HEX(r.project_id)) projectId,r.module_key moduleKey,r.record_type recordType,"
        .'r.reference,r.title,r.detail,r.status,r.severity,r.owner_role ownerRole,r.due_on dueOn,r.payload,r.evidence_file_ids evidenceFileIds,'
        .'r.linked_record_ids linkedRecordIds,r.human_confirmed humanConfirmed,r.confirmation_note confirmationNote,r.revision,'
        .'LOWER(HEX(r.created_by)) createdBy,r.created_at createdAt,r.updated_at updatedAt FROM module_records r';

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function catalogue(): array
    {
        return ModuleRecordSchema::catalogue();
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId, string $userId, ?string $moduleKey, ?string $projectId, ?string $status, int $limit = 100): array
    {
        $this->assertMember($organisationId, $userId);
        if ($moduleKey !== null && !ModuleRecordSchema::supports($moduleKey)) {
            throw new ModuleRecordException('unknown_module', 404);
        }
        if ($status !== null && $moduleKey !== null) {
            ModuleRecordSchema::assertStatus($moduleKey, $status);
        }
        $sql = self::SELECT.' WHERE r.organisation_id=UNHEX(:org)';
        $params = ['org' => $organisationId];
        if ($moduleKey !== null) {
            $sql .= ' AND r.module_key=:module';
            $params['module'] = $moduleKey;
        }
        if ($projectId !== null) {
            if (preg_match('/\A[a-f0-9]{32}\z/i', $projectId) !== 1) {
                throw new ModuleRecordException('invalid_project_id');
            }
            $sql .= ' AND r.project_id=UNHEX(:project)';
            $params['project'] = $projectId;
        }
        if ($status !== null) {
            $sql .= ' AND r.status=:status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY r.updated_at DESC, r.id DESC LIMIT '.max(1, min(200, $limit));
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed> */
    public function find(string $organisationId, string $userId, string $id): array
    {
        $this->assertMember($organisationId, $userId);
        return $this->load($organisationId, $id);
    }

    /** @return array<string,mixed> */
    public function create(string $organisationId, string $userId, ModuleRecordInput $input): array
    {
        $this->assertMember($organisationId, $userId);
        $input->assertComplete();
        $this->assertProject($organisationId, $input->projectId);
        $this->assertEvidence($organisationId, $input->projectId, $input->evidenceFileIds);
        if ($input->clientRequestId !== null) {
            $existing = $this->byRequestId($organisationId, $input->clientRequestId);
            if ($existing !== null) {
                return $existing;
            }
        }
        $id = Identifier::generate();
        $insert = $this->pdo->prepare('INSERT INTO module_records(id,organisation_id,project_id,module_key,record_type,reference,title,detail,status,severity,owner_role,due_on,payload,evidence_file_ids,linked_record_ids,human_confirmed,confirmation_note,revision,client_request_id,created_by,updated_by)'
            .' VALUES(UNHEX(:id),UNHEX(:org),UNHEX(:project),:module,:type,:reference,:title,:detail,:status,:severity,:owner,:due_on,:payload,:evidence,:links,:confirmed,:note,1,:request,UNHEX(:created_by),UNHEX(:updated_by))');
        $this->pdo->beginTransaction();
        try {
            $insert->execute([
                'id' => $id, 'org' => $organisationId, 'project' => $input->projectId, 'module' => $input->moduleKey,
                'type' => $input->recordType, 'reference' => $input->reference, 'title' => $input->title, 'detail' => $input->detail,
                'status' => $input->status, 'severity' => $input->severity, 'owner' => $input->ownerRole, 'due_on' => $input->dueOn,
                'payload' => json_encode($input->payload, JSON_THROW_ON_ERROR), 'evidence' => json_encode($input->evidenceFileIds, JSON_THROW_ON_ERROR),
                'links' => json_encode($input->linkedRecordIds, JSON_THROW_ON_ERROR), 'confirmed' => $input->humanConfirmed ? 1 : 0,
                'note' => $input->confirmationNote, 'request' => $input->clientRequestId,
                // created_by and updated_by need distinct placeholders: prepared statements run
                // with emulation off, where a repeated named parameter is invalid.
                'created_by' => $userId, 'updated_by' => $userId,
            ]);
            $this->appendEvent($organisationId, $id, $input->moduleKey, null, $input->status, 'Record created', $userId);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'module_record.created', 'module_record', $id, [
                'moduleKey' => $input->moduleKey, 'recordType' => $input->recordType, 'status' => $input->status, 'projectId' => $input->projectId,
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
    public function update(string $organisationId, string $userId, string $id, ModuleRecordInput $input): array
    {
        $this->assertMember($organisationId, $userId);
        $current = $this->load($organisationId, $id);
        if ($input->moduleKey !== $current['moduleKey']) {
            throw new ModuleRecordException('module_mismatch');
        }
        if ($input->expectedRevision === null) {
            throw new ModuleRecordException('expected_revision_required');
        }
        $input->assertComplete();
        $this->assertProject($organisationId, $input->projectId);
        $this->assertEvidence($organisationId, $input->projectId, $input->evidenceFileIds);
        $statement = $this->pdo->prepare('UPDATE module_records SET project_id=UNHEX(:project),reference=:reference,title=:title,detail=:detail,status=:status,severity=:severity,owner_role=:owner,due_on=:due_on,payload=:payload,evidence_file_ids=:evidence,linked_record_ids=:links,human_confirmed=:confirmed,confirmation_note=:note,revision=revision+1,updated_by=UNHEX(:user)'
            .' WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:org) AND revision=:expected');
        $this->pdo->beginTransaction();
        try {
            $statement->execute([
                'project' => $input->projectId, 'reference' => $input->reference, 'title' => $input->title, 'detail' => $input->detail,
                'status' => $input->status, 'severity' => $input->severity, 'owner' => $input->ownerRole, 'due_on' => $input->dueOn,
                'payload' => json_encode($input->payload, JSON_THROW_ON_ERROR), 'evidence' => json_encode($input->evidenceFileIds, JSON_THROW_ON_ERROR),
                'links' => json_encode($input->linkedRecordIds, JSON_THROW_ON_ERROR), 'confirmed' => $input->humanConfirmed ? 1 : 0,
                'note' => $input->confirmationNote, 'id' => $id, 'org' => $organisationId,
                'expected' => $input->expectedRevision, 'user' => $userId,
            ]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                throw new ModuleRecordException('revision_conflict', 409);
            }
            if ($input->status !== $current['status']) {
                $this->appendEvent($organisationId, $id, $input->moduleKey, (string) $current['status'], $input->status, $input->confirmationNote ?? 'Status changed with the record update', $userId);
            }
            (new AuditService($this->pdo))->append($organisationId, $userId, 'module_record.updated', 'module_record', $id, [
                'moduleKey' => $input->moduleKey, 'status' => $input->status, 'fromStatus' => $current['status'],
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

    /** @return array<string,mixed> */
    public function transition(string $organisationId, string $userId, string $id, string $toStatus, string $reason, int $expectedRevision, bool $humanConfirmed): array
    {
        $this->assertMember($organisationId, $userId);
        $current = $this->load($organisationId, $id);
        ModuleRecordSchema::assertStatus((string) $current['moduleKey'], $toStatus);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new ModuleRecordException('reason_required');
        }
        if (ModuleRecordSchema::isDecision((string) $current['moduleKey'], $toStatus) && !$humanConfirmed) {
            throw new ModuleRecordException('confirmation_required');
        }
        $statement = $this->pdo->prepare('UPDATE module_records SET status=:status,human_confirmed=:confirmed,confirmation_note=:note,revision=revision+1,updated_by=UNHEX(:user) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:org) AND revision=:expected');
        $this->pdo->beginTransaction();
        try {
            $statement->execute(['status' => $toStatus, 'confirmed' => $humanConfirmed ? 1 : 0, 'note' => $reason, 'id' => $id, 'org' => $organisationId, 'expected' => $expectedRevision, 'user' => $userId]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                throw new ModuleRecordException('revision_conflict', 409);
            }
            $this->appendEvent($organisationId, $id, (string) $current['moduleKey'], (string) $current['status'], $toStatus, $reason, $userId);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'module_record.transitioned', 'module_record', $id, [
                'moduleKey' => $current['moduleKey'], 'fromStatus' => $current['status'], 'toStatus' => $toStatus,
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

    public function remove(string $organisationId, string $userId, string $id, int $expectedRevision): void
    {
        $this->assertMember($organisationId, $userId);
        $current = $this->load($organisationId, $id);
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('DELETE FROM module_records WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:org) AND revision=:expected');
            $statement->execute(['id' => $id, 'org' => $organisationId, 'expected' => $expectedRevision]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                throw new ModuleRecordException('revision_conflict', 409);
            }
            // The row is gone; the audit event keeps the deletion itself accountable.
            (new AuditService($this->pdo))->append($organisationId, $userId, 'module_record.deleted', 'module_record', $id, [
                'moduleKey' => $current['moduleKey'], 'recordType' => $current['recordType'], 'title' => $current['title'], 'status' => $current['status'],
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function events(string $organisationId, string $userId, string $id): array
    {
        $this->assertMember($organisationId, $userId);
        $this->load($organisationId, $id);
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(id)) id,from_status fromStatus,to_status toStatus,reason,LOWER(HEX(actor_user_id)) actorUserId,created_at createdAt FROM module_record_events WHERE organisation_id=UNHEX(:org) AND record_id=UNHEX(:id) ORDER BY created_at ASC, id ASC');
        $statement->execute(['org' => $organisationId, 'id' => $id]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    private function load(string $organisationId, string $id): array
    {
        if (preg_match('/\A[a-f0-9]{32}\z/i', $id) !== 1) {
            throw new ModuleRecordException('record_not_found', 404);
        }
        $statement = $this->pdo->prepare(self::SELECT.' WHERE r.organisation_id=UNHEX(:org) AND r.id=UNHEX(:id)');
        $statement->execute(['org' => $organisationId, 'id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ModuleRecordException('record_not_found', 404);
        }
        return $this->hydrate($row);
    }

    /** @return array<string,mixed>|null */
    private function byRequestId(string $organisationId, string $clientRequestId): ?array
    {
        $statement = $this->pdo->prepare(self::SELECT.' WHERE r.organisation_id=UNHEX(:org) AND r.client_request_id=:request');
        $statement->execute(['org' => $organisationId, 'request' => $clientRequestId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrate(array $row): array
    {
        $row['id'] = (string) $row['id_hex'];
        unset($row['id_hex']);
        $row['revision'] = (int) $row['revision'];
        $row['humanConfirmed'] = (bool) $row['humanConfirmed'];
        foreach (['payload', 'evidenceFileIds', 'linkedRecordIds'] as $json) {
            $decoded = json_decode((string) ($row[$json] ?? ''), true);
            $row[$json] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    private function appendEvent(string $organisationId, string $recordId, string $moduleKey, ?string $from, string $to, string $reason, string $userId): void
    {
        $statement = $this->pdo->prepare('INSERT INTO module_record_events(id,organisation_id,record_id,module_key,from_status,to_status,reason,actor_user_id) VALUES(UNHEX(:id),UNHEX(:org),UNHEX(:record),:module,:from_status,:to_status,:reason,UNHEX(:user))');
        $statement->execute([
            'id' => Identifier::generate(), 'org' => $organisationId, 'record' => $recordId, 'module' => $moduleKey,
            'from_status' => $from, 'to_status' => $to, 'reason' => mb_substr($reason, 0, 500), 'user' => $userId,
        ]);
    }

    private function assertMember(string $organisationId, string $userId): void
    {
        if (preg_match('/\A[a-f0-9]{32}\z/i', $organisationId) !== 1 || preg_match('/\A[a-f0-9]{32}\z/i', $userId) !== 1) {
            throw new ModuleRecordException('record_not_found', 404);
        }
        $statement = $this->pdo->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:org) AND user_id=UNHEX(:user) AND status='active'");
        $statement->execute(['org' => $organisationId, 'user' => $userId]);
        if ($statement->fetchColumn() === false) {
            throw new ModuleRecordException('record_not_found', 404);
        }
    }

    private function assertProject(string $organisationId, ?string $projectId): void
    {
        if ($projectId === null) {
            return;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM projects WHERE id=UNHEX(:project) AND organisation_id=UNHEX(:org)');
        $statement->execute(['project' => $projectId, 'org' => $organisationId]);
        if ($statement->fetchColumn() === false) {
            throw new ModuleRecordException('project_not_found', 404);
        }
    }

    /** @param list<string> $fileIds */
    private function assertEvidence(string $organisationId, ?string $projectId, array $fileIds): void
    {
        if ($fileIds === []) {
            return;
        }
        // implode() takes the pieces and the glue in that order; passing an already-joined
        // string as the second argument throws, which meant every record carrying evidence
        // failed with a 500. Build the placeholder list explicitly instead.
        $list = implode(',', array_map(static fn (): string => 'UNHEX(?)', $fileIds));
        $sql = "SELECT COUNT(*) FROM stored_files WHERE organisation_id=UNHEX(?) AND status='active' AND id IN (".$list.')';
        $params = array_merge([$organisationId], $fileIds);
        if ($projectId !== null) {
            $sql .= ' AND project_id=UNHEX(?)';
            $params[] = $projectId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        if ((int) $statement->fetchColumn() !== count($fileIds)) {
            throw new ModuleRecordException('unknown_evidence');
        }
    }
}

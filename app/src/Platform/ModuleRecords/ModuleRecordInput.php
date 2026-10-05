<?php

declare(strict_types=1);

namespace Architex\Platform\ModuleRecords;

/**
 * Validated input for a module record. Everything the module's contract gap names as a
 * missing field is either a base column here or a key in payload, validated against the
 * module's own vocabulary in ModuleRecordSchema — so an unknown field is rejected rather
 * than quietly stored in a JSON blob.
 */
final readonly class ModuleRecordInput
{
    /**
     * @param list<string> $evidenceFileIds
     * @param list<string> $linkedRecordIds
     * @param array<string,mixed> $payload
     */
    private function __construct(
        public string $moduleKey,
        public string $recordType,
        public ?string $projectId,
        public string $title,
        public string $detail,
        public ?string $reference,
        public ?string $ownerRole,
        public ?string $dueOn,
        public ?string $severity,
        public string $status,
        public array $evidenceFileIds,
        public array $linkedRecordIds,
        public bool $humanConfirmed,
        public ?string $confirmationNote,
        public array $payload,
        public ?int $expectedRevision,
        public ?string $clientRequestId,
    ) {
    }

    public static function fromArray(array $body): self
    {
        $moduleKey = $body['moduleKey'] ?? null;
        if (!is_string($moduleKey) || !ModuleRecordSchema::supports($moduleKey)) {
            throw new ModuleRecordException('unknown_module', 404);
        }
        $spec = ModuleRecordSchema::for($moduleKey);

        $recordType = $body['recordType'] ?? $spec['recordType'];
        if (!is_string($recordType) || $recordType !== $spec['recordType']) {
            throw new ModuleRecordException('invalid_record_type');
        }

        $status = $body['status'] ?? $spec['initial'];
        if (!is_string($status)) {
            throw new ModuleRecordException('invalid_status');
        }
        ModuleRecordSchema::assertStatus($moduleKey, $status);

        $project = $body['projectId'] ?? null;
        if ($project !== null && (!is_string($project) || preg_match('/\A[a-f0-9]{32}\z/i', $project) !== 1)) {
            throw new ModuleRecordException('invalid_project_id');
        }

        $dueOn = $body['dueOn'] ?? null;
        if ($dueOn !== null) {
            if (!is_string($dueOn) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dueOn) !== 1) {
                throw new ModuleRecordException('invalid_due_on');
            }
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueOn);
            if ($parsed === false || $parsed->format('Y-m-d') !== $dueOn) {
                throw new ModuleRecordException('invalid_due_on');
            }
        }

        $severity = $body['severity'] ?? null;
        if ($severity !== null && (!is_string($severity) || !in_array($severity, ['low', 'medium', 'high', 'critical'], true))) {
            throw new ModuleRecordException('invalid_severity');
        }

        return new self(
            $moduleKey,
            $recordType,
            $project === null ? null : strtolower($project),
            self::text($body['title'] ?? null, 3, 220, 'invalid_title'),
            self::text($body['detail'] ?? '', 0, 4000, 'invalid_detail'),
            self::optionalText($body['reference'] ?? null, 120, 'invalid_reference'),
            self::optionalText($body['ownerRole'] ?? null, 80, 'invalid_owner_role'),
            $dueOn,
            $severity,
            $status,
            self::idList($body['evidenceFileIds'] ?? [], 'invalid_evidence_ids'),
            self::idList($body['linkedRecordIds'] ?? [], 'invalid_linked_ids'),
            self::boolean($body['humanConfirmed'] ?? false),
            self::optionalText($body['confirmationNote'] ?? null, 500, 'invalid_confirmation_note'),
            self::payload($moduleKey, $body['payload'] ?? []),
            self::revision($body['expectedRevision'] ?? null),
            self::requestId($body['clientRequestId'] ?? null),
        );
    }

    /**
     * The same completeness rule the UI enforces, enforced again server-side: a decision
     * status cannot be reached without a named human confirmation and a reason.
     */
    public function assertComplete(): void
    {
        foreach (ModuleRecordSchema::for($this->moduleKey)['required'] as $field) {
            $value = match ($field) {
                'title' => $this->title,
                'detail' => $this->detail,
                'reference' => $this->reference,
                'ownerRole' => $this->ownerRole,
                'dueOn' => $this->dueOn,
                'severity' => $this->severity,
                'evidenceFileIds' => $this->evidenceFileIds,
                // A required field can legitimately live in payload (ruleVersionId, quantity,
                // collectionInterval ...). Resolving it here keeps one definition of "required"
                // for the form and the validator.
                default => $this->payload[$field] ?? null,
            };
            if ($value === null || $value === '' || $value === []) {
                throw new ModuleRecordException('missing_'.$field);
            }
        }
        if (ModuleRecordSchema::isDecision($this->moduleKey, $this->status)) {
            if (!$this->humanConfirmed) {
                throw new ModuleRecordException('confirmation_required');
            }
            if ($this->confirmationNote === null || $this->confirmationNote === '') {
                throw new ModuleRecordException('reason_required');
            }
        }
        if ($this->expectedRevision !== null && $this->expectedRevision < 1) {
            throw new ModuleRecordException('invalid_expected_revision');
        }
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (bool) (int) $value;
        }
        if ($value === null || $value === false) {
            return false;
        }
        throw new ModuleRecordException('invalid_confirmation_flag');
    }

    private static function revision(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) && $value > 0) {
            return $value;
        }
        throw new ModuleRecordException('invalid_expected_revision');
    }

    /** @return list<string> */
    private static function idList(mixed $value, string $reason): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value) || count($value) > 20) {
            throw new ModuleRecordException($reason);
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_string($item) || preg_match('/\A[a-f0-9]{32}\z/i', $item) !== 1) {
                throw new ModuleRecordException($reason);
            }
            $out[] = strtolower($item);
        }
        return array_values(array_unique($out));
    }

    /** @return array<string,mixed> */
    private static function payload(string $moduleKey, mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            throw new ModuleRecordException('invalid_payload');
        }
        $out = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new ModuleRecordException('invalid_payload');
            }
            ModuleRecordSchema::assertPayloadField($moduleKey, $key);
            if (is_array($item)) {
                $list = [];
                if (count($item) > 50) {
                    throw new ModuleRecordException('invalid_payload');
                }
                foreach ($item as $entry) {
                    if (!is_scalar($entry)) {
                        throw new ModuleRecordException('invalid_payload');
                    }
                    $list[] = self::scalar($entry);
                }
                $out[$key] = $list;
                continue;
            }
            if (!is_scalar($item) && $item !== null) {
                throw new ModuleRecordException('invalid_payload');
            }
            $out[$key] = $item === null ? null : self::scalar($item);
        }
        return $out;
    }

    private static function scalar(mixed $value): string|int|float|bool
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        $text = trim((string) $value);
        if (mb_strlen($text) > 2000) {
            throw new ModuleRecordException('invalid_payload');
        }
        return $text;
    }

    private static function text(mixed $value, int $min, int $max, string $reason): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw new ModuleRecordException($reason);
        }
        $value = trim($value);
        if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
            throw new ModuleRecordException($reason);
        }
        return $value;
    }

    private static function optionalText(mixed $value, int $max, string $reason): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return self::text($value, 1, $max, $reason);
    }

    private static function requestId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || preg_match('/\A[A-Za-z0-9_-]{16,64}\z/', $value) !== 1) {
            throw new ModuleRecordException('invalid_client_request_id');
        }
        return $value;
    }
}

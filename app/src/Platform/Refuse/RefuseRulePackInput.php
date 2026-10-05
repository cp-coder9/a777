<?php

declare(strict_types=1);

namespace Architex\Platform\Refuse;

/**
 * A rule pack: the thresholds an assessment is judged against.
 *
 * `sourceReference` is mandatory. A criterion with no traceable source is indistinguishable from
 * an invented one, so the API refuses to store it — and a pack stays `review_required` until a
 * named professional verifies it, which is the only state in which its criteria are applied.
 */
final readonly class RefuseRulePackInput
{
    /** The criterion keys the calculator understands. An unknown key is rejected, not stored. */
    private const CRITERIA = [
        'max_collection_interval_days' => 'days',
        'min_vehicle_access_width_m' => 'metres',
        'ventilation_strategies' => 'strategies',
        'drainage_required' => 'flag',
    ];

    /**
     * @param array<string,array{value:mixed,source:string,note:string}> $criteria
     */
    private function __construct(
        public string $municipalityKey,
        public string $municipalityName,
        public string $version,
        public string $sourceReference,
        public float $binFootprintM2,
        public int $defaultBinCapacityLitres,
        public int $storageHeightMm,
        public int $circulationAllowancePercent,
        public array $criteria,
    ) {
    }

    public static function fromArray(array $body): self
    {
        $key = self::text($body['municipalityKey'] ?? null, 2, 120, 'invalid_municipality_key');
        if (preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $key) !== 1) {
            throw new RefuseException('invalid_municipality_key');
        }
        return new self(
            $key,
            self::text($body['municipalityName'] ?? null, 2, 180, 'invalid_municipality_name'),
            self::text($body['version'] ?? null, 1, 100, 'invalid_version'),
            self::text($body['sourceReference'] ?? null, 3, 500, 'source_reference_required'),
            self::decimal($body['binFootprintM2'] ?? 0.438, 0.05, 5.0, 'invalid_bin_footprint'),
            self::integer($body['defaultBinCapacityLitres'] ?? 240, 30, 5000, 'invalid_bin_capacity'),
            self::integer($body['storageHeightMm'] ?? 2000, 500, 6000, 'invalid_storage_height'),
            self::integer($body['circulationAllowancePercent'] ?? 0, 0, 200, 'invalid_circulation_allowance'),
            self::criteria($body['criteria'] ?? []),
        );
    }

    /** @return array<string,array{value:mixed,source:string,note:string}> */
    private static function criteria(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            throw new RefuseException('invalid_criteria');
        }
        $out = [];
        foreach ($value as $key => $entry) {
            if (!is_string($key) || !isset(self::CRITERIA[$key])) {
                throw new RefuseException('unknown_criterion');
            }
            if (!is_array($entry) || !array_key_exists('value', $entry)) {
                throw new RefuseException('invalid_criterion');
            }
            $source = $entry['source'] ?? null;
            if ($source !== null && !is_string($source)) {
                throw new RefuseException('invalid_criterion_source');
            }
            $note = $entry['note'] ?? null;
            if ($note !== null && (!is_string($note) || mb_strlen($note) > 300)) {
                throw new RefuseException('invalid_criterion_note');
            }
            $out[$key] = [
                'value' => self::criterionValue($key, $entry['value']),
                'source' => is_string($source) ? trim($source) : '',
                'note' => is_string($note) ? trim($note) : '',
            ];
        }
        return $out;
    }

    private static function criterionValue(string $key, mixed $value): mixed
    {
        return match (self::CRITERIA[$key]) {
            'days' => self::integer($value, 1, 31, 'invalid_criterion_value'),
            'metres' => self::decimal($value, 0.0, 50.0, 'invalid_criterion_value'),
            'flag' => self::boolean($value),
            'strategies' => self::strategies($value),
        };
    }

    /** @return list<string> */
    private static function strategies(mixed $value): array
    {
        if (!is_array($value) || $value === [] || count($value) > 4) {
            throw new RefuseException('invalid_criterion_value');
        }
        $out = [];
        foreach ($value as $strategy) {
            if (!is_string($strategy) || !in_array($strategy, ['natural', 'mechanical'], true)) {
                throw new RefuseException('invalid_criterion_value');
            }
            $out[] = $strategy;
        }
        return array_values(array_unique($out));
    }

    private static function text(mixed $value, int $min, int $max, string $reason): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw new RefuseException($reason);
        }
        $value = trim($value);
        if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
            throw new RefuseException($reason);
        }
        return $value;
    }

    private static function integer(mixed $value, int $min, int $max, string $reason): int
    {
        if (is_string($value) && preg_match('/\A\d+\z/', trim($value)) === 1) {
            $value = (int) trim($value);
        }
        if (is_bool($value) || !is_int($value) || $value < $min || $value > $max) {
            throw new RefuseException($reason);
        }
        return $value;
    }

    private static function decimal(mixed $value, float $min, float $max, string $reason): float
    {
        if (is_string($value) && preg_match('/\A\d+(\.\d+)?\z/', trim($value)) === 1) {
            $value = (float) trim($value);
        }
        if (is_int($value)) {
            $value = (float) $value;
        }
        if (!is_float($value) || !is_finite($value) || $value < $min || $value > $max) {
            throw new RefuseException($reason);
        }
        return round($value, 3);
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (bool) (int) $value;
        }
        throw new RefuseException('invalid_criterion_value');
    }
}

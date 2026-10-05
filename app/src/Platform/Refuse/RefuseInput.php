<?php

declare(strict_types=1);

namespace Architex\Platform\Refuse;

/**
 * Validated inputs for one refuse-area calculation.
 *
 * The optional fields exist because the arithmetic genuinely needs them to judge anything:
 * without a provided storage area there is nothing to compare the requirement against, and the
 * calculator says so rather than guessing a value.
 */
final readonly class RefuseInput
{
    private const VENTILATION = ['natural', 'mechanical', 'none'];

    private function __construct(
        public string $title,
        public string $municipality,
        public string $buildingType,
        public int $occupancy,
        public ?float $grossFloorAreaM2,
        public float $generationRate,
        public int $collectionIntervalDays,
        public int $binCapacityLitres,
        public ?int $binsProvided,
        public ?float $providedStorageAreaM2,
        public ?float $accessWidthM,
        public ?string $ventilationType,
        public bool $drainageProvided,
        public ?int $expectedRevision,
        public ?string $clientRequestId,
    ) {
    }

    public static function fromArray(array $body): self
    {
        $title = self::text($body['title'] ?? null, 3, 220, 'invalid_title');
        $municipality = self::text($body['municipality'] ?? null, 2, 180, 'invalid_municipality');
        $buildingType = self::text($body['buildingType'] ?? null, 2, 120, 'invalid_building_type');
        $occupancy = self::integer($body['occupancy'] ?? null, 1, 100000, 'invalid_occupancy');
        $rate = self::decimal($body['generationRateLitresPerPersonDay'] ?? null, 0.1, 500.0, 'invalid_generation_rate');
        $interval = self::integer($body['collectionIntervalDays'] ?? null, 1, 31, 'invalid_collection_interval');
        $binCapacity = self::integer($body['binCapacityLitres'] ?? 240, 30, 5000, 'invalid_bin_capacity');

        $ventilation = $body['ventilationType'] ?? null;
        if ($ventilation !== null && (!is_string($ventilation) || !in_array($ventilation, self::VENTILATION, true))) {
            throw new RefuseException('invalid_ventilation_type');
        }

        return new self(
            $title,
            $municipality,
            $buildingType,
            $occupancy,
            self::optionalDecimal($body['grossFloorAreaM2'] ?? null, 0.01, 10_000_000, 'invalid_gross_floor_area'),
            $rate,
            $interval,
            $binCapacity,
            self::optionalInteger($body['binsProvided'] ?? null, 0, 10000, 'invalid_bins_provided'),
            self::optionalDecimal($body['providedStorageAreaM2'] ?? null, 0.0, 1_000_000, 'invalid_provided_storage_area'),
            self::optionalDecimal($body['accessWidthM'] ?? null, 0.0, 200, 'invalid_access_width'),
            $ventilation,
            self::boolean($body['drainageProvided'] ?? false),
            self::optionalInteger($body['expectedRevision'] ?? null, 1, 4_000_000_000, 'invalid_expected_revision'),
            self::requestId($body['clientRequestId'] ?? null),
        );
    }

    /** @return array{occupancy:int,generationRate:float,collectionIntervalDays:int,binCapacityLitres:int,binsProvided:?int,providedStorageAreaM2:?float,accessWidthM:?float,ventilationType:?string,drainageProvided:bool} */
    public function calculatorInputs(): array
    {
        return [
            'occupancy' => $this->occupancy,
            'generationRate' => $this->generationRate,
            'collectionIntervalDays' => $this->collectionIntervalDays,
            'binCapacityLitres' => $this->binCapacityLitres,
            'binsProvided' => $this->binsProvided,
            'providedStorageAreaM2' => $this->providedStorageAreaM2,
            'accessWidthM' => $this->accessWidthM,
            'ventilationType' => $this->ventilationType,
            'drainageProvided' => $this->drainageProvided,
        ];
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return $this->calculatorInputs() + [
            'title' => $this->title,
            'municipality' => $this->municipality,
            'buildingType' => $this->buildingType,
            'grossFloorAreaM2' => $this->grossFloorAreaM2,
        ];
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
        if (is_string($value) && preg_match('/\A\d+\z/', $value) === 1) {
            $value = (int) $value;
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new RefuseException($reason);
        }
        return $value;
    }

    private static function optionalInteger(mixed $value, int $min, int $max, string $reason): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return self::integer($value, $min, $max, $reason);
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

    private static function optionalDecimal(mixed $value, float $min, float $max, string $reason): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return self::decimal($value, $min, $max, $reason);
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (bool) (int) $value;
        }
        if ($value === null || $value === '') {
            return false;
        }
        throw new RefuseException('invalid_drainage_flag');
    }

    private static function requestId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || preg_match('/\A[A-Za-z0-9_-]{16,64}\z/', $value) !== 1) {
            throw new RefuseException('invalid_client_request_id');
        }
        return $value;
    }
}

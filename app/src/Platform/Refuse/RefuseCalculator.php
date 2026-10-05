<?php

declare(strict_types=1);

namespace Architex\Platform\Refuse;

/**
 * The refuse-area arithmetic, done on the professional's inputs.
 *
 * Three rules this class exists to enforce:
 *
 *  1. Every figure is either arithmetic on an input the user supplied, or a comparison against a
 *     criterion taken from a named, versioned rule pack. No constant is invented here.
 *  2. A criterion that is not loaded (or not yet verified) produces `not_assessed` — never a
 *     green pass. A verdict you cannot justify is worse than no verdict.
 *  3. Every assessment row carries its basis in words, so a reviewer can see exactly what the
 *     pass or fail was measured against.
 *
 * Pure and static so the arithmetic can be tested without a database.
 */
final class RefuseCalculator
{
    private const VERDICT_PASS = 'pass';
    private const VERDICT_WARNING = 'warning';
    private const VERDICT_FAIL = 'fail';
    private const VERDICT_NOT_ASSESSED = 'not_assessed';

    /** Physical dimensions of a standard 240 L wheeled bin, used only when no rule pack says otherwise. */
    private const DEFAULT_BIN_FOOTPRINT_M2 = 0.438;

    /**
     * @param array{occupancy:int,generationRate:float,collectionIntervalDays:int,binCapacityLitres:int,
     *              binsProvided:?int,providedStorageAreaM2:?float,accessWidthM:?float,
     *              ventilationType:?string,drainageProvided:bool} $inputs
     * @param array{binFootprintM2?:float,circulationAllowancePercent?:int,criteria?:array<string,mixed>} $rule
     * @return array{results:array<string,mixed>,assessments:list<array<string,mixed>>,advisory:string,basis:string}
     */
    public static function compute(array $inputs, array $rule = [], ?string $ruleVersion = null, bool $ruleVerified = false, string $municipalityName = ''): array
    {
        $footprint = isset($rule['binFootprintM2']) ? (float) $rule['binFootprintM2'] : self::DEFAULT_BIN_FOOTPRINT_M2;
        $circulation = isset($rule['circulationAllowancePercent']) ? max(0, min(200, (int) $rule['circulationAllowancePercent'])) : 0;
        $loaded = is_array($rule['criteria'] ?? null) ? $rule['criteria'] : [];
        // An unverified rule pack is a draft, not an authority: its criteria are carried through
        // only so the screen can show what is waiting to be verified, never so a verdict can be
        // asserted from them.
        $criteria = $ruleVerified ? $loaded : [];

        // ---- arithmetic on the inputs -------------------------------------------------------
        $dailyLitres = $inputs['occupancy'] * $inputs['generationRate'];
        $intervalLitres = $dailyLitres * $inputs['collectionIntervalDays'];
        $binsRequired = (int) ceil($intervalLitres / max(1, $inputs['binCapacityLitres']));
        $standingArea = $binsRequired * $footprint;
        $circulationArea = $standingArea * ($circulation / 100);
        $requiredArea = $standingArea + $circulationArea;

        $results = [
            'dailyWasteLitres' => round($dailyLitres, 1),
            'intervalWasteLitres' => round($intervalLitres, 1),
            'wasteVolumeM3PerDay' => round($dailyLitres / 1000, 2),
            'binsRequired' => $binsRequired,
            'binCapacityLitres' => $inputs['binCapacityLitres'],
            'requiredCapacityLitres' => $binsRequired * $inputs['binCapacityLitres'],
            'binFootprintM2' => round($footprint, 3),
            'binStandingAreaM2' => round($standingArea, 2),
            'circulationAllowancePercent' => $circulation,
            'circulationAreaM2' => round($circulationArea, 2),
            'requiredStorageAreaM2' => round($requiredArea, 2),
            'collectionIntervalDays' => $inputs['collectionIntervalDays'],
        ];

        $assessments = [];

        // Storage area: pure comparison of what was provided against what the inputs require.
        if ($inputs['providedStorageAreaM2'] === null) {
            $assessments[] = self::row('storage_area', 'Storage area', self::VERDICT_NOT_ASSESSED,
                'Enter the storage area provided to compare it with the '.self::num($requiredArea).' m² this calculation requires.',
                'required '.self::num($requiredArea).' m²');
        } else {
            $provided = (float) $inputs['providedStorageAreaM2'];
            $pass = $provided >= $requiredArea;
            $assessments[] = self::row('storage_area', 'Storage area', $pass ? self::VERDICT_PASS : self::VERDICT_FAIL,
                self::num($requiredArea).' m² required / '.self::num($provided).' m² provided',
                'arithmetic on occupancy, generation rate and collection interval' . ($circulation > 0 ? ', plus the rule pack circulation allowance' : ''));
        }

        // Bin allocation: what was provided against what the interval volume needs.
        if ($inputs['binsProvided'] === null) {
            $assessments[] = self::row('bin_allocation', 'Bin allocation', self::VERDICT_NOT_ASSESSED,
                'Enter the bins provided to compare them with the '.$binsRequired.' this calculation requires.',
                'required '.$binsRequired.' × '.$inputs['binCapacityLitres'].' L');
        } else {
            $pass = $inputs['binsProvided'] >= $binsRequired;
            $assessments[] = self::row('bin_allocation', 'Bin allocation', $pass ? self::VERDICT_PASS : self::VERDICT_FAIL,
                $inputs['binsProvided'].' × '.$inputs['binCapacityLitres'].' L provided / '.$binsRequired.' required',
                'arithmetic on the collection interval volume and bin capacity');
        }

        // Collection interval: only assessable against a stated criterion.
        $intervalCriteria = self::criterion($criteria, 'max_collection_interval_days');
        if ($intervalCriteria === null) {
            $assessments[] = self::row('collection_interval', 'Collection interval', self::VERDICT_NOT_ASSESSED,
                $inputs['collectionIntervalDays'].'-day interval recorded. '.self::unverifiedNote($loaded, 'max_collection_interval_days', 'No maximum collection interval is loaded for this municipality'),
                'no verified criterion');
        } else {
            $limit = (float) $intervalCriteria['value'];
            $pass = $inputs['collectionIntervalDays'] <= $limit;
            $assessments[] = self::row('collection_interval', 'Collection interval', $pass ? self::VERDICT_PASS : self::VERDICT_WARNING,
                $inputs['collectionIntervalDays'].'-day interval recorded, maximum '.self::num($limit).($limit == 1.0 ? ' day' : ' days'),
                self::basis($intervalCriteria));
        }

        // Vehicle access: width is an input, the minimum is a criterion.
        $accessCriteria = self::criterion($criteria, 'min_vehicle_access_width_m');
        if ($inputs['accessWidthM'] === null) {
            $assessments[] = self::row('vehicle_access', 'Collection vehicle access', self::VERDICT_NOT_ASSESSED,
                'Enter the access width to judge vehicle access.', 'no width recorded');
        } elseif ($accessCriteria === null) {
            $assessments[] = self::row('vehicle_access', 'Collection vehicle access', self::VERDICT_NOT_ASSESSED,
                self::num((float) $inputs['accessWidthM']).' m access recorded. '.self::unverifiedNote($loaded, 'min_vehicle_access_width_m', 'No minimum access width is loaded for this municipality'),
                'no verified criterion');
        } else {
            $limit = (float) $accessCriteria['value'];
            $pass = (float) $inputs['accessWidthM'] >= $limit;
            $assessments[] = self::row('vehicle_access', 'Collection vehicle access', $pass ? self::VERDICT_PASS : self::VERDICT_FAIL,
                self::num((float) $inputs['accessWidthM']).' m access recorded, minimum '.self::num($limit).' m',
                self::basis($accessCriteria));
        }

        // Ventilation: acceptable strategies come from the rule pack.
        $ventCriteria = self::criterion($criteria, 'ventilation_strategies');
        if ($inputs['ventilationType'] === null) {
            $assessments[] = self::row('ventilation', 'Ventilation', self::VERDICT_NOT_ASSESSED,
                'Record the ventilation strategy to judge it.', 'no strategy recorded');
        } elseif ($ventCriteria === null) {
            $assessments[] = self::row('ventilation', 'Ventilation', self::VERDICT_NOT_ASSESSED,
                ucfirst($inputs['ventilationType']).' ventilation recorded. '.self::unverifiedNote($loaded, 'ventilation_strategies', 'No accepted ventilation strategies are loaded for this municipality'),
                'no verified criterion');
        } else {
            $accepted = array_map('strval', (array) $ventCriteria['value']);
            $pass = in_array((string) $inputs['ventilationType'], $accepted, true);
            $assessments[] = self::row('ventilation', 'Ventilation', $pass ? self::VERDICT_PASS : self::VERDICT_WARNING,
                ucfirst($inputs['ventilationType']).' ventilation recorded, accepted: '.implode(', ', $accepted),
                self::basis($ventCriteria));
        }

        // Drainage: recorded fact plus, where it exists, the criterion that makes it required.
        $drainCriteria = self::criterion($criteria, 'drainage_required');
        if ($drainCriteria === null) {
            $assessments[] = self::row('drainage', 'Drainage', self::VERDICT_NOT_ASSESSED,
                'Wash-down drainage is recorded as '.($inputs['drainageProvided'] ? 'provided' : 'not provided').'. '.self::unverifiedNote($loaded, 'drainage_required', 'No drainage requirement is loaded for this municipality'),
                'no verified criterion');
        } else {
            $required = (bool) $drainCriteria['value'];
            $pass = !$required || $inputs['drainageProvided'];
            $assessments[] = self::row('drainage', 'Drainage', $pass ? self::VERDICT_PASS : self::VERDICT_FAIL,
                'Drainage '.($inputs['drainageProvided'] ? 'provided' : 'not provided').', '.($required ? 'required' : 'not required').' for this municipality',
                self::basis($drainCriteria));
        }

        return [
            'results' => $results,
            'assessments' => $assessments,
            'advisory' => self::advisory($ruleVersion, $ruleVerified, $municipalityName, $criteria !== []),
            'basis' => $ruleVersion === null
                ? 'arithmetic on the recorded inputs only — no rule pack loaded, so nothing is judged against municipal criteria'
                : ($ruleVerified
                    ? 'arithmetic on the recorded inputs, judged against '.$municipalityName.' rule pack '.$ruleVersion.' (verified)'
                    : 'arithmetic on the recorded inputs; rule pack '.$ruleVersion.' is NOT verified, so no compliance verdict is asserted'),
        ];
    }

    /** @param array<string,mixed> $criteria @return array{value:mixed,source:string,note:string}|null */
    private static function criterion(array $criteria, string $key): ?array
    {
        $entry = $criteria[$key] ?? null;
        if (!is_array($entry) || !array_key_exists('value', $entry) || $entry['value'] === null || $entry['value'] === '') {
            return null;
        }
        return [
            'value' => $entry['value'],
            'source' => is_string($entry['source'] ?? null) ? $entry['source'] : '',
            'note' => is_string($entry['note'] ?? null) ? $entry['note'] : '',
        ];
    }

    /** @param array{value:mixed,source:string,note:string} $criterion */
    private static function basis(array $criterion): string
    {
        $basis = 'rule pack criterion';
        if ($criterion['source'] !== '') {
            $basis .= ' — '.$criterion['source'];
        }
        if ($criterion['note'] !== '') {
            $basis .= ' ('.$criterion['note'].')';
        }
        return $basis;
    }

    /** @return array<string,mixed> */
    private static function row(string $key, string $label, string $verdict, string $detail, string $basis): array
    {
        return ['key' => $key, 'label' => $label, 'verdict' => $verdict, 'detail' => $detail, 'basis' => $basis];
    }

    private static function advisory(?string $ruleVersion, bool $verified, string $municipalityName, bool $hasCriteria): string
    {
        $tail = 'Advisory only — confirm with the municipality before submission.';
        if ($ruleVersion === null) {
            return 'No rule pack is loaded for this municipality. The volume and area figures are arithmetic on your inputs; nothing is judged against municipal criteria. '.$tail;
        }
        if (!$verified) {
            return 'Rule pack '.$ruleVersion.' is loaded but not verified, so no compliance verdict is asserted. '.$tail;
        }
        if (!$hasCriteria) {
            return 'Rule pack '.$ruleVersion.' is verified but carries no criteria, so only the arithmetic is reported. '.$tail;
        }
        return 'Assessed against '.$municipalityName.' rule pack '.$ruleVersion.' (verified). '.$tail;
    }

    /** Distinguishes "nothing loaded" from "loaded, waiting on a professional to verify it". */
    private static function unverifiedNote(array $loaded, string $key, string $nothingLoaded): string
    {
        $entry = $loaded[$key] ?? null;
        $value = is_array($entry) ? ($entry['value'] ?? null) : null;
        if ($value === null || $value === '') {
            return $nothingLoaded.', so it is not judged.';
        }
        $shown = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
        return 'A criterion of '.$shown.' is loaded but not verified, so it is not judged against it.';
    }

    private static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

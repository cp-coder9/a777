<?php

declare(strict_types=1);

namespace Architex\Platform\NuancedQc;

final class NuancedQcEvaluator
{
    private const SEVERITY_RANK = ['blocker' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    /**
     * @param list<array<string,mixed>> $categoryResults
     * @param list<array<string,mixed>> $findings
     * @return array{recommendation:string,counts:array{blocker:int,high:int,medium:int,low:int,unreviewed:int,acceptedRisk:int},readyForSignoff:bool,blockReason:?string,orderedFindings:list<array<string,mixed>>}
     */
    public function summarise(array $categoryResults, array $findings): array
    {
        $ordered = array_values($findings);
        usort($ordered, static fn (array $left, array $right): int =>
            (self::SEVERITY_RANK[(string) ($right['severity'] ?? '')] ?? 0)
            <=> (self::SEVERITY_RANK[(string) ($left['severity'] ?? '')] ?? 0)
        );

        $active = array_values(array_filter(
            $findings,
            static fn (array $finding): bool => !in_array($finding['status'] ?? null, ['retest_passed', 'not_applicable'], true),
        ));
        $acceptedRisk = array_values(array_filter(
            $active,
            static fn (array $finding): bool => ($finding['riskAccepted'] ?? false) === true,
        ));
        $unaccepted = array_values(array_filter(
            $active,
            static fn (array $finding): bool => ($finding['riskAccepted'] ?? false) !== true,
        ));
        $bySeverity = static fn (string $severity): array => array_values(array_filter(
            $unaccepted,
            static fn (array $finding): bool => ($finding['severity'] ?? null) === $severity,
        ));
        $unreviewed = array_values(array_filter(
            $categoryResults,
            static fn (array $result): bool => ($result['status'] ?? 'not_reviewed') === 'not_reviewed',
        ));

        $blockers = $bySeverity('blocker');
        $highs = $bySeverity('high');
        $mediums = $bySeverity('medium');
        $lows = $bySeverity('low');

        $recommendation = match (true) {
            $blockers !== [] => 'block_merge_or_deploy',
            $highs !== [] => 'changes_requested',
            $unreviewed !== [] => 'continue_qc',
            $mediums !== [] || $lows !== [] || $acceptedRisk !== [] => 'accept_with_notes',
            default => 'accept',
        };
        $blockReason = $blockers !== []
            ? 'open_blocker_findings'
            : ($unreviewed !== [] ? 'unreviewed_gap_categories' : null);

        return [
            'recommendation' => $recommendation,
            'counts' => [
                'blocker' => count($blockers),
                'high' => count($highs),
                'medium' => count($mediums),
                'low' => count($lows),
                'unreviewed' => count($unreviewed),
                'acceptedRisk' => count($acceptedRisk),
            ],
            'readyForSignoff' => $blockReason === null,
            'blockReason' => $blockReason,
            'orderedFindings' => $ordered,
        ];
    }
}

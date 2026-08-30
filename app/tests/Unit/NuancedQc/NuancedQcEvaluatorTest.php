<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\NuancedQc;

use Architex\Platform\NuancedQc\NuancedQcEvaluator;
use Architex\Platform\NuancedQc\NuancedQcRegistry;
use PHPUnit\Framework\TestCase;

final class NuancedQcEvaluatorTest extends TestCase
{
    public function testUnreviewedCategoriesPreventSignoff(): void
    {
        $summary = (new NuancedQcEvaluator())->summarise($this->results('not_reviewed'), []);

        self::assertSame('continue_qc', $summary['recommendation']);
        self::assertFalse($summary['readyForSignoff']);
        self::assertSame('unreviewed_gap_categories', $summary['blockReason']);
        self::assertSame(35, $summary['counts']['unreviewed']);
    }

    public function testAllPassedCategoriesAreReadyForAcceptance(): void
    {
        $summary = (new NuancedQcEvaluator())->summarise($this->results('pass'), []);

        self::assertSame('accept', $summary['recommendation']);
        self::assertTrue($summary['readyForSignoff']);
        self::assertNull($summary['blockReason']);
    }

    public function testOpenBlockerFailsClosedAndFindingsAreSeverityRanked(): void
    {
        $findings = [
            ['id' => 'low', 'severity' => 'low', 'status' => 'open', 'riskAccepted' => false],
            ['id' => 'blocker', 'severity' => 'blocker', 'status' => 'open', 'riskAccepted' => false],
            ['id' => 'high', 'severity' => 'high', 'status' => 'open', 'riskAccepted' => false],
        ];

        $summary = (new NuancedQcEvaluator())->summarise($this->results('pass'), $findings);

        self::assertSame('block_merge_or_deploy', $summary['recommendation']);
        self::assertFalse($summary['readyForSignoff']);
        self::assertSame('open_blocker_findings', $summary['blockReason']);
        self::assertSame(['blocker', 'high', 'low'], array_column($summary['orderedFindings'], 'id'));
    }

    public function testAcceptedBlockerRiskRemainsVisibleButPermitsSignoff(): void
    {
        $summary = (new NuancedQcEvaluator())->summarise($this->results('pass'), [[
            'id' => 'accepted-blocker',
            'severity' => 'blocker',
            'status' => 'accepted_risk',
            'riskAccepted' => true,
        ]]);

        self::assertSame('accept_with_notes', $summary['recommendation']);
        self::assertTrue($summary['readyForSignoff']);
        self::assertSame(1, $summary['counts']['acceptedRisk']);
        self::assertSame('accepted-blocker', $summary['orderedFindings'][0]['id']);
    }

    public function testOpenHighFindingRequestsChanges(): void
    {
        $summary = (new NuancedQcEvaluator())->summarise($this->results('pass'), [[
            'id' => 'high',
            'severity' => 'high',
            'status' => 'open',
            'riskAccepted' => false,
        ]]);

        self::assertSame('changes_requested', $summary['recommendation']);
        self::assertTrue($summary['readyForSignoff']);
        self::assertSame(1, $summary['counts']['high']);
    }

    /** @return list<array{category:string,status:string}> */
    private function results(string $status): array
    {
        return array_map(
            static fn (array $category): array => ['category' => $category['key'], 'status' => $status],
            (new NuancedQcRegistry())->categories(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Demo;

use Architex\Platform\Demo\DemoSeedCatalog;
use PHPUnit\Framework\TestCase;

final class DemoSeedCatalogTest extends TestCase
{
    public function testCatalogIsDeterministicCompleteAndUsesSafeSyntheticIdentity(): void
    {
        $catalog = new DemoSeedCatalog();

        self::assertSame('pack21-v1', $catalog->version());
        self::assertCount(22, $catalog->personas());
        self::assertCount(12, $catalog->projects());
        self::assertSame($catalog->hash(), (new DemoSeedCatalog())->hash());
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $catalog->hash());
        self::assertSame(22, count(array_unique(array_column($catalog->personas(), 'roleKey'))));
        foreach ($catalog->personas() as $persona) {
            self::assertStringEndsWith('.example', $persona['email']);
        }

        $projects = $catalog->projects();
        self::assertSame(12, count(array_unique(array_column($projects, 'sourceKey'))));
        foreach (['onboarding', 'feasibility', 'appointment', 'concept_design', 'design_development', 'tender_procurement', 'construction_execution', 'closeout'] as $phase) {
            self::assertContains($phase, array_column($projects, 'currentPhase'));
        }
        foreach ($projects as $project) {
            self::assertIsInt($project['budgetCents']);
            self::assertGreaterThan(0, $project['budgetCents']);
            self::assertMatchesRegularExpression('/\A20\d{2}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $project['createdAt']);
        }
    }

    public function testSeedItemsHaveStableKeysExactLearningCountsAndRelianceBoundaries(): void
    {
        $items = (new DemoSeedCatalog())->items();
        self::assertNotEmpty($items);
        self::assertSame(count($items), count(array_unique(array_column($items, 'sourceKey'))));
        $types = array_count_values(array_column($items, 'itemType'));
        self::assertSame(6, $types['learning_article']);
        self::assertSame(4, $types['learning_assessment']);
        self::assertSame(3, $types['learning_module']);
        self::assertSame(5, $types['learning_certificate']);
        foreach ($items as $item) {
            self::assertContains($item['relianceLabel'], ['illustrative_demo_not_for_reliance', 'not_accredited_demo_record']);
            self::assertMatchesRegularExpression('/\A20\d{2}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $item['occurredAt']);
            if (str_starts_with($item['itemType'], 'learning_')) {
                self::assertSame('not_accredited_demo_record', $item['relianceLabel']);
            }
        }
    }
}

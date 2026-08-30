<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\NuancedQc;

use Architex\Platform\NuancedQc\NuancedQcRegistry;
use PHPUnit\Framework\TestCase;

final class NuancedQcRegistryTest extends TestCase
{
    public function testDefinesEveryMandatoryCategoryInStableOrder(): void
    {
        $categories = (new NuancedQcRegistry())->categories();

        self::assertCount(35, $categories);
        self::assertSame(range(1, 35), array_column($categories, 'sortOrder'));
        self::assertSame('contract_legal_basis', $categories[0]['key']);
        self::assertSame('Contract and legal basis', $categories[0]['title']);
        self::assertSame('analytics_learning_loop', $categories[34]['key']);
        self::assertSame('Analytics and learning loop', $categories[34]['title']);
        self::assertContains('authority_matrix', array_column($categories, 'key'));
        self::assertContains('tenant_project_isolation', array_column($categories, 'key'));
        self::assertContains('security_remote_desktop', array_column($categories, 'key'));
        self::assertCount(35, array_unique(array_column($categories, 'key')));
    }
}

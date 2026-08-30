<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\PlatformCompleteness;

use Architex\Platform\PlatformCompleteness\PlatformCompletenessRegistry;
use PHPUnit\Framework\TestCase;

final class PlatformCompletenessRegistryTest extends TestCase
{
    public function testPackCatalogueIsCompleteAndImmutable(): void
    {
        $catalogue=(new PlatformCompletenessRegistry())->catalogue();
        self::assertCount(12,$catalogue['workstreams']);
        self::assertCount(21,$catalogue['qualityCriteria']);
        self::assertCount(10,$catalogue['comparisonQuestions']);
        self::assertCount(14,$catalogue['sections']);
        self::assertSame(95,array_sum(array_map(static fn(array $section):int=>count($section['items']),$catalogue['sections'])));
        self::assertSame('account_identity_roles',$catalogue['sections'][0]['key']);
        self::assertSame('testing_deployment_acceptance',$catalogue['sections'][13]['key']);
        self::assertSame('User signup/login works.',$catalogue['sections'][0]['items'][0]);
        self::assertSame('Leor sign-off recorded.',$catalogue['sections'][13]['items'][8]);
        self::assertSame(['critical','high','medium','low'],$catalogue['priorities']);
        self::assertContains('accepted',$catalogue['implementationStatuses']);
        self::assertContains('blocked',$catalogue['qcStatuses']);
        self::assertCount(11,$catalogue['evidenceCategories']);
    }
}

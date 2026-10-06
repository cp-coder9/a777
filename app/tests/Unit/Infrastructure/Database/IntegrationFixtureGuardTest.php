<?php
declare(strict_types=1);
namespace Architex\Tests\Unit\Infrastructure\Database;

use Architex\Tests\Support\MariaDbTestCase;
use Architex\Tests\Integration\Feedback\FeedbackHttpTest;
use Architex\Tests\Integration\Feedback\FeedbackServiceTest;
use Architex\Tests\Integration\Project\ProjectDetailsCasTest;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class IntegrationFixtureGuardTest extends TestCase
{
    public function testFormerFocusedFixturesUseTheSharedGuardedDatabaseLifecycle(): void
    {
        foreach ([FeedbackHttpTest::class, FeedbackServiceTest::class, ProjectDetailsCasTest::class] as $fixture) {
            $class = new ReflectionClass($fixture);
            // Central lifecycle validates the actual process environment before any connection
            // or destructive SQL, and uses one random task schema for the complete suite.
            foreach (['setUp', 'tearDown'] as $method) {
                self::assertSame(MariaDbTestCase::class, $class->getMethod($method)->getDeclaringClass()->getName(), $fixture . ' must retain the shared guarded ' . $method);
            }
        }
    }
}

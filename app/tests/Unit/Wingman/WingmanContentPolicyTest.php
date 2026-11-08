<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Wingman;

use Architex\Platform\Wingman\WingmanContentPolicy;
use Architex\Platform\Wingman\WingmanException;
use PHPUnit\Framework\TestCase;

final class WingmanContentPolicyTest extends TestCase
{
    public function testPromptBoundaries(): void
    {
        self::assertSame('abc', WingmanContentPolicy::prompt(' abc '));
        self::assertSame(str_repeat('a', 4000), WingmanContentPolicy::prompt(str_repeat('a', 4000)));
        foreach ([null, '', '  ', 'ab', str_repeat('a', 4001)] as $input) {
            try {
                WingmanContentPolicy::prompt($input);
                self::fail('Invalid prompt was accepted');
            } catch (WingmanException $exception) {
                self::assertSame('invalid_prompt', $exception->reason);
            }
        }
    }

    public function testResponsesAreLabelledAndLongResponsesAreTruncated(): void
    {
        self::assertSame("Hello\n\n" . WingmanContentPolicy::DISCLAIMER, WingmanContentPolicy::response('Hello'));
        $long = WingmanContentPolicy::response(str_repeat('é', 8001));
        self::assertSame(str_repeat('é', 8000) . "\n\n" . WingmanContentPolicy::TRUNCATED . "\n\n" . WingmanContentPolicy::DISCLAIMER, $long);
        self::assertStringNotContainsString(WingmanContentPolicy::TRUNCATED, WingmanContentPolicy::response(str_repeat('a', 8000)));
    }

    public function testEmptyProviderResponseIsRejected(): void
    {
        $this->expectException(WingmanException::class);
        WingmanContentPolicy::response('  ');
    }
}

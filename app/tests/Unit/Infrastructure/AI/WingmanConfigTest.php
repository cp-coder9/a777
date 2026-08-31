<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure\AI;

use Architex\Infrastructure\AI\WingmanConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WingmanConfigTest extends TestCase
{
    public function testUsesTheApprovedNvidiaEndpointModelAndBoundedTimeouts(): void
    {
        $config = WingmanConfig::fromEnvironment([
            'NVIDIA_API_KEY' => 'test-only-secret',
            'NVIDIA_CONNECT_TIMEOUT_SECONDS' => '4',
            'NVIDIA_TIMEOUT_SECONDS' => '50',
            'NVIDIA_MAX_RESPONSE_BYTES' => '1048576',
        ]);

        self::assertTrue($config->enabled());
        self::assertSame('https://integrate.api.nvidia.com/v1/chat/completions', $config->endpoint);
        self::assertSame('nvidia/nemotron-3.5-lightning-30b-a3b', $config->model);
        self::assertSame(4, $config->connectTimeoutSeconds);
        self::assertSame(50, $config->timeoutSeconds);
        self::assertSame(1_048_576, $config->maxResponseBytes);
        self::assertSame(['provider' => 'nvidia', 'model' => $config->model, 'configured' => true], $config->diagnostics());
        self::assertStringNotContainsString('test-only-secret', json_encode($config->diagnostics(), JSON_THROW_ON_ERROR));
    }

    public function testMissingKeyProducesAnHonestDisabledConfiguration(): void
    {
        $config = WingmanConfig::fromEnvironment([]);
        self::assertFalse($config->enabled());
        self::assertSame(['provider' => 'nvidia', 'model' => $config->model, 'configured' => false], $config->diagnostics());
    }

    public function testRejectsUnboundedRuntimeSettings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WingmanConfig::fromEnvironment(['NVIDIA_TIMEOUT_SECONDS' => '500']);
    }
}

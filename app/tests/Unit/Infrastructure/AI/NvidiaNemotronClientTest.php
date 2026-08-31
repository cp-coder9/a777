<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure\AI;

use Architex\Infrastructure\AI\NvidiaNemotronClient;
use Architex\Infrastructure\AI\NvidiaNemotronException;
use Architex\Infrastructure\AI\NvidiaTransport;
use Architex\Infrastructure\AI\WingmanConfig;
use PHPUnit\Framework\TestCase;

final class NvidiaNemotronClientTest extends TestCase
{
    public function testSendsABoundedNemotronRequestAndNormalisesTheResponse(): void
    {
        $transport = new RecordingNvidiaTransport(200, json_encode([
            'id' => 'chatcmpl-test',
            'model' => 'nvidia/nemotron-3.5-lightning-30b-a3b',
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Review the submission pack, then open Comply.',
                    'tool_calls' => [[
                        'id' => 'call-1',
                        'type' => 'function',
                        'function' => ['name' => 'propose_navigation', 'arguments' => '{"route":"municipal"}'],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 32],
        ], JSON_THROW_ON_ERROR), ['x-request-id' => 'request-test']);
        $client = new NvidiaNemotronClient($this->config(), $transport);

        $result = $client->complete(
            [['role' => 'user', 'content' => 'What should I do next?']],
            [[
                'type' => 'function',
                'function' => [
                    'name' => 'propose_navigation',
                    'description' => 'Propose an authorised route.',
                    'parameters' => ['type' => 'object', 'properties' => ['route' => ['type' => 'string']]],
                ],
            ]],
        );

        self::assertSame('Review the submission pack, then open Comply.', $result['content']);
        self::assertSame('propose_navigation', $result['toolCalls'][0]['name']);
        self::assertSame(['route' => 'municipal'], $result['toolCalls'][0]['arguments']);
        self::assertSame('request-test', $result['requestId']);
        self::assertSame(['input' => 120, 'output' => 32], $result['usage']);
        self::assertSame('nvidia/nemotron-3.5-lightning-30b-a3b', $transport->request['payload']['model']);
        self::assertSame('Bearer test-only-secret', $transport->request['headers']['Authorization']);
        self::assertFalse($transport->request['payload']['stream']);
        self::assertSame(['enable_thinking' => false], $transport->request['payload']['chat_template_kwargs']);
        self::assertLessThanOrEqual(2_048, $transport->request['payload']['max_tokens']);
    }

    public function testMapsRateLimitsWithoutLeakingProviderResponseBodies(): void
    {
        $transport = new RecordingNvidiaTransport(429, 'provider secret diagnostic body', []);
        $client = new NvidiaNemotronClient($this->config(), $transport);

        try {
            $client->complete([['role' => 'user', 'content' => 'Help']]);
            self::fail('A provider rate limit must fail closed.');
        } catch (NvidiaNemotronException $exception) {
            self::assertSame('rate_limited', $exception->reason);
            self::assertStringNotContainsString('provider secret', $exception->getMessage());
        }
    }

    public function testRefusesToCallProviderWhenServerConfigurationIsMissing(): void
    {
        $transport = new RecordingNvidiaTransport(200, '{}', []);
        $client = new NvidiaNemotronClient(WingmanConfig::fromEnvironment([]), $transport);

        $this->expectExceptionObject(new NvidiaNemotronException('configuration_required'));
        $client->complete([['role' => 'user', 'content' => 'Help']]);
    }

    public function testRejectsOversizedOrUntrustedMessageShapesBeforeTransport(): void
    {
        $transport = new RecordingNvidiaTransport(200, '{}', []);
        $client = new NvidiaNemotronClient($this->config(), $transport);

        $this->expectExceptionObject(new NvidiaNemotronException('invalid_request'));
        $client->complete([['role' => 'browser_dom', 'content' => str_repeat('x', 20_001)]]);
    }

    private function config(): WingmanConfig
    {
        return WingmanConfig::fromEnvironment(['NVIDIA_API_KEY' => 'test-only-secret']);
    }
}

final class RecordingNvidiaTransport implements NvidiaTransport
{
    /** @var array<string,mixed> */
    public array $request = [];

    /** @param array<string,string> $responseHeaders */
    public function __construct(private int $status, private string $body, private array $responseHeaders) {}

    /** @param array<string,string> $headers @return array{status:int,body:string,headers:array<string,string>} */
    public function send(string $endpoint, array $headers, string $body, int $connectTimeoutSeconds, int $timeoutSeconds, int $maxResponseBytes): array
    {
        $this->request = [
            'endpoint' => $endpoint,
            'headers' => $headers,
            'payload' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
            'connectTimeoutSeconds' => $connectTimeoutSeconds,
            'timeoutSeconds' => $timeoutSeconds,
            'maxResponseBytes' => $maxResponseBytes,
        ];
        return ['status' => $this->status, 'body' => $this->body, 'headers' => $this->responseHeaders];
    }
}

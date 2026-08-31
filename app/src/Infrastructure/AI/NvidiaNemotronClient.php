<?php

declare(strict_types=1);

namespace Architex\Infrastructure\AI;

use JsonException;

final readonly class NvidiaNemotronClient
{
    public function __construct(private WingmanConfig $config, private NvidiaTransport $transport) {}

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param list<array<string,mixed>> $tools
     * @return array{content:string,toolCalls:list<array{id:string,name:string,arguments:array<string,mixed>}>,requestId:?string,model:string,finishReason:?string,usage:array{input:int,output:int}}
     */
    public function complete(array $messages, array $tools = []): array
    {
        if (!$this->config->enabled()) throw new NvidiaNemotronException('configuration_required');
        $this->validateMessages($messages);
        if (count($tools) > 8) throw new NvidiaNemotronException('invalid_request');

        $payload = [
            'model' => $this->config->model,
            'messages' => $messages,
            'temperature' => 1.0,
            'top_p' => 0.95,
            'max_tokens' => 2_048,
            'stream' => false,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new NvidiaNemotronException('invalid_request');
        }
        $response = $this->transport->send(
            $this->config->endpoint,
            [
                'Authorization' => $this->config->authorizationHeader(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            $body,
            $this->config->connectTimeoutSeconds,
            $this->config->timeoutSeconds,
            $this->config->maxResponseBytes,
        );
        $this->guardStatus($response['status']);

        try {
            $decoded = json_decode($response['body'], true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new NvidiaNemotronException('provider_response_invalid');
        }
        if (!is_array($decoded) || !is_array($decoded['choices'][0]['message'] ?? null)) {
            throw new NvidiaNemotronException('provider_response_invalid');
        }
        $message = $decoded['choices'][0]['message'];
        $content = $message['content'] ?? '';
        if (!is_string($content)) throw new NvidiaNemotronException('provider_response_invalid');

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            if (!is_array($call) || !is_array($call['function'] ?? null) || !is_string($call['function']['name'] ?? null) || !is_string($call['function']['arguments'] ?? null)) {
                throw new NvidiaNemotronException('provider_response_invalid');
            }
            try {
                $arguments = json_decode($call['function']['arguments'], true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new NvidiaNemotronException('provider_response_invalid');
            }
            if (!is_array($arguments) || array_is_list($arguments)) throw new NvidiaNemotronException('provider_response_invalid');
            $toolCalls[] = [
                'id' => is_string($call['id'] ?? null) ? $call['id'] : '',
                'name' => $call['function']['name'],
                'arguments' => $arguments,
            ];
        }
        $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
        return [
            'content' => $content,
            'toolCalls' => $toolCalls,
            'requestId' => is_string($response['headers']['x-request-id'] ?? null) ? $response['headers']['x-request-id'] : null,
            'model' => is_string($decoded['model'] ?? null) ? $decoded['model'] : $this->config->model,
            'finishReason' => is_string($decoded['choices'][0]['finish_reason'] ?? null) ? $decoded['choices'][0]['finish_reason'] : null,
            'usage' => ['input' => (int) ($usage['prompt_tokens'] ?? 0), 'output' => (int) ($usage['completion_tokens'] ?? 0)],
        ];
    }

    /** @param list<array{role:string,content:string}> $messages */
    private function validateMessages(array $messages): void
    {
        if ($messages === [] || count($messages) > 30) throw new NvidiaNemotronException('invalid_request');
        $total = 0;
        foreach ($messages as $message) {
            if (!is_array($message) || !in_array($message['role'] ?? null, ['system', 'user', 'assistant', 'tool'], true) || !is_string($message['content'] ?? null)) {
                throw new NvidiaNemotronException('invalid_request');
            }
            $length = mb_strlen($message['content']);
            if ($length < 1 || $length > 20_000) throw new NvidiaNemotronException('invalid_request');
            $total += $length;
        }
        if ($total > 100_000) throw new NvidiaNemotronException('invalid_request');
    }

    private function guardStatus(int $status): void
    {
        if ($status >= 200 && $status < 300) return;
        throw new NvidiaNemotronException(match ($status) {
            401, 403 => 'configuration_required',
            408, 504 => 'provider_timeout',
            429 => 'rate_limited',
            default => 'provider_unavailable',
        });
    }
}

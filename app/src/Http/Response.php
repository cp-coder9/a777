<?php

declare(strict_types=1);

namespace Architex\Http;

use JsonException;

final readonly class Response
{
    /** @param array<string,mixed> $payload @param array<string,string> $headers */
    private function __construct(
        private int $status,
        private array $payload,
        private array $headers,
        private bool $suppressBody,
    ) {}

    /** @param array<string,mixed> $meta */
    public static function success(mixed $data, array $meta = []): self
    {
        return self::make(200, ['data' => $data, 'meta' => $meta, 'error' => null]);
    }

    /** @param array<string,mixed>|null $details @param array<string,string> $headers */
    public static function error(int $status, string $code, string $message, ?string $requestId = null, ?array $details = null, array $headers = []): self
    {
        $meta = $requestId === null ? [] : ['requestId' => $requestId];
        return self::make($status, ['data' => null, 'meta' => $meta, 'error' => ['code' => $code, 'message' => $message, 'details' => $details]], $headers);
    }

    /** @param array<string,mixed> $meta */
    public function withMeta(array $meta): self
    {
        return self::make($this->status, [
            'data' => $this->payload['data'],
            'meta' => array_replace($this->payload['meta'], $meta),
            'error' => $this->payload['error'],
        ], $this->headers, $this->suppressBody);
    }

    public function withoutBody(): self
    {
        return new self($this->status, $this->payload, $this->headers, true);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $header => $value) {
            if (strcasecmp($header, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /** @return array{data:mixed,meta:array<string,mixed>,error:array{code:string,message:string,details:array<string,mixed>|null}|null} */
    public function json(): array
    {
        return $this->payload;
    }

    public function body(): string
    {
        if ($this->suppressBody) {
            return '';
        }

        try {
            return json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return json_encode(self::encodingFailurePayload($this->payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
    }

    public function emit(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $this->body();
    }

    /** @param array<string,mixed> $payload @param array<string,string> $headers */
    private static function make(int $status, array $payload, array $headers = [], bool $suppressBody = false): self
    {
        $headers = ['Content-Type' => 'application/json; charset=utf-8', ...$headers];
        try {
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            return new self($status, $payload, $headers, $suppressBody);
        } catch (JsonException) {
            return new self(500, self::encodingFailurePayload($payload), $headers, $suppressBody);
        }
    }

    /** @param array<string,mixed> $payload @return array{data:null,meta:array<string,mixed>,error:array{code:string,message:string,details:null}} */
    private static function encodingFailurePayload(array $payload): array
    {
        $requestId = $payload['meta']['requestId'] ?? null;
        return [
            'data' => null,
            'meta' => is_string($requestId) ? ['requestId' => $requestId] : [],
            'error' => ['code' => 'internal_error', 'message' => 'The response could not be encoded.', 'details' => null],
        ];
    }
}

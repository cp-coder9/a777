<?php

declare(strict_types=1);

namespace Architex\Http;

final readonly class Request
{
    /** @param array<string,string> $headers */
    private function __construct(
        private string $method,
        private string $path,
        private array $headers,
        private string $body,
    ) {}

    /** @param array<string,string> $headers */
    public static function json(string $method, string $uri, array $headers = [], string $body = ''): self
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return new self(strtoupper($method), self::normalisePath($path), self::normaliseHeaders($headers), $body);
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }

            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($name, 5))] = (string) $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace('_', '-', $name)] = (string) $value;
            }
        }

        return self::json(
            is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/',
            $headers,
            (string) file_get_contents('php://input'),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function body(): string
    {
        return $this->body;
    }

    private static function normalisePath(string $path): string
    {
        $normalised = '/' . ltrim($path, '/');
        return $normalised === '/' ? $normalised : rtrim($normalised, '/');
    }

    /** @param array<string,string> $headers @return array<string,string> */
    private static function normaliseHeaders(array $headers): array
    {
        $normalised = [];
        foreach ($headers as $name => $value) {
            $normalised[strtolower($name)] = $value;
        }

        return $normalised;
    }
}

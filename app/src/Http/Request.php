<?php

declare(strict_types=1);

namespace Architex\Http;

final readonly class Request
{
    /** @param array<string,string> $headers @param array<string,UploadedFile> $uploadedFiles @param array<string,string> $form */
    private function __construct(
        private string $method,
        private string $path,
        private array $headers,
        private string $body,
        private array $attributes = [],
        private array $uploadedFiles = [],
        private array $query = [],
        private array $form = [],
    ) {}

    /** @param array<string,string> $headers */
    public static function json(string $method, string $uri, array $headers = [], string $body = ''): self
    {
        $parts = explode('?', $uri, 2);
        $path = $parts[0];
        $query = [];
        if (isset($parts[1])) {
            parse_str($parts[1], $parsed);
            foreach ($parsed as $name => $value) {
                if (is_string($name) && is_string($value)) $query[$name] = $value;
            }
        }

        return new self(strtoupper($method), $path === '' ? '/' : $path, self::normaliseHeaders($headers), $body, query: $query);
    }

    /** @param array<string,mixed>|null $server */
    public static function fromGlobals(?array $server = null, ?string $body = null, ?array $files = null, ?array $form = null): self
    {
        $server ??= $_SERVER;
        $headers = [];
        foreach ($server as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }

            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($name, 5))] = (string) $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace('_', '-', $name)] = (string) $value;
            }
        }

        $request = self::json(
            is_string($server['REQUEST_METHOD'] ?? null) ? $server['REQUEST_METHOD'] : 'GET',
            is_string($server['REQUEST_URI'] ?? null) ? $server['REQUEST_URI'] : '/',
            $headers,
            $body ?? (string) file_get_contents('php://input'),
        );
        $remoteIp = $server['REMOTE_ADDR'] ?? null;
        if (is_string($remoteIp) && filter_var($remoteIp, FILTER_VALIDATE_IP) !== false) {
            $request = $request->withAttribute('remoteIp', $remoteIp);
        }
        $files ??= $_FILES;
        foreach ($files as $field => $upload) {
            if (!is_string($field) || !is_array($upload)) {
                continue;
            }
            try {
                $request = $request->withUploadedFile($field, UploadedFile::fromPhpUpload($upload));
            } catch (\InvalidArgumentException) {
            }
        }
        $form ??= $_POST;
        $normalisedForm = [];
        foreach ($form as $field => $value) {
            if (is_string($field) && is_scalar($value)) {
                $normalisedForm[$field] = (string) $value;
            }
        }
        $request = $request->withForm($normalisedForm);
        return $request;
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

    public function query(string $name): ?string
    {
        return $this->query[$name] ?? null;
    }

    public function form(string $name): ?string
    {
        return $this->form[$name] ?? null;
    }

    public function withFormField(string $name, string $value): self
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/', $name) !== 1) {
            throw new \InvalidArgumentException('The form field name is invalid.');
        }
        return $this->withForm(array_replace($this->form, [$name => $value]));
    }

    public function attribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        return new self($this->method, $this->path, $this->headers, $this->body, array_replace($this->attributes, [$name => $value]), $this->uploadedFiles, $this->query, $this->form);
    }

    public function uploadedFile(string $field): ?UploadedFile
    {
        return $this->uploadedFiles[$field] ?? null;
    }

    public function withUploadedFile(string $field, UploadedFile $file): self
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/', $field) !== 1) {
            throw new \InvalidArgumentException('The upload field name is invalid.');
        }
        return new self($this->method, $this->path, $this->headers, $this->body, $this->attributes, array_replace($this->uploadedFiles, [$field => $file]), $this->query, $this->form);
    }

    /** @param array<string,string> $form */
    private function withForm(array $form): self
    {
        return new self($this->method, $this->path, $this->headers, $this->body, $this->attributes, $this->uploadedFiles, $this->query, $form);
    }

    /** @return array<string,mixed> */
    public function jsonBody(): array
    {
        $topLevel = json_decode($this->body, false, 32, JSON_THROW_ON_ERROR);
        if (!is_object($topLevel)) {
            throw new \JsonException('JSON request body must be an object.');
        }
        $decoded = json_decode($this->body, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \JsonException('JSON request body must be an object.');
        }
        return $decoded;
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

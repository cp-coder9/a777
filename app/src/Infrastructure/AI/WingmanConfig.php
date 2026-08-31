<?php

declare(strict_types=1);

namespace Architex\Infrastructure\AI;

use InvalidArgumentException;

final readonly class WingmanConfig
{
    public const ENDPOINT = 'https://integrate.api.nvidia.com/v1/chat/completions';
    public const MODEL = 'nvidia/nemotron-3.5-lightning-30b-a3b';

    private function __construct(
        private string $apiKey,
        public string $endpoint,
        public string $model,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
        public int $maxResponseBytes,
    ) {}

    /** @param array<string,mixed> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $apiKey = self::string($environment, 'NVIDIA_API_KEY', '');
        if ($apiKey !== '' && preg_match('/[\x00-\x20\x7f]/', $apiKey) === 1) {
            throw new InvalidArgumentException('NVIDIA_API_KEY contains invalid whitespace or control characters');
        }

        return new self(
            $apiKey,
            self::ENDPOINT,
            self::MODEL,
            self::integer($environment, 'NVIDIA_CONNECT_TIMEOUT_SECONDS', 5, 1, 30),
            self::integer($environment, 'NVIDIA_TIMEOUT_SECONDS', 45, 5, 120),
            self::integer($environment, 'NVIDIA_MAX_RESPONSE_BYTES', 2_097_152, 65_536, 4_194_304),
        );
    }

    public function enabled(): bool
    {
        return $this->apiKey !== '';
    }

    public function authorizationHeader(): string
    {
        if (!$this->enabled()) {
            throw new NvidiaNemotronException('configuration_required');
        }
        return 'Bearer ' . $this->apiKey;
    }

    /** @return array{provider:string,model:string,configured:bool} */
    public function diagnostics(): array
    {
        return ['provider' => 'nvidia', 'model' => $this->model, 'configured' => $this->enabled()];
    }

    /** @param array<string,mixed> $environment */
    private static function string(array $environment, string $key, string $default): string
    {
        $value = $environment[$key] ?? $default;
        if (!is_string($value)) throw new InvalidArgumentException($key . ' must be a string');
        return trim($value);
    }

    /** @param array<string,mixed> $environment */
    private static function integer(array $environment, string $key, int $default, int $minimum, int $maximum): int
    {
        $value = $environment[$key] ?? (string) $default;
        if ((!is_string($value) && !is_int($value)) || preg_match('/^[0-9]+$/D', (string) $value) !== 1) {
            throw new InvalidArgumentException($key . ' must be an integer');
        }
        $parsed = (int) $value;
        if ($parsed < $minimum || $parsed > $maximum) {
            throw new InvalidArgumentException(sprintf('%s must be between %d and %d', $key, $minimum, $maximum));
        }
        return $parsed;
    }
}

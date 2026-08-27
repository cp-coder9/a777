<?php

declare(strict_types=1);

namespace Architex\Platform\Jobs;

use JsonException;
use RuntimeException;

final readonly class PayloadCipher
{
    private const CIPHER = 'aes-256-gcm';

    private function __construct(private string $key) {}

    public static function fromBase64Key(string $encodedKey): self
    {
        $key = base64_decode($encodedKey, true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('APP_KEY must be a base64-encoded 32-byte key.');
        }
        return new self($key);
    }

    /** @param array<string,mixed> $payload */
    public function encrypt(array $payload): string
    {
        try {
            $plain = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new RuntimeException('The job payload could not be encoded.');
        }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if (!is_string($ciphertext)) {
            throw new RuntimeException('The job payload could not be encrypted.');
        }
        return base64_encode($nonce . $tag . $ciphertext);
    }

    /** @return array<string,mixed> */
    public function decrypt(string $encoded): array
    {
        $envelope = base64_decode($encoded, true);
        if (!is_string($envelope) || strlen($envelope) < 29) {
            throw new RuntimeException('The job payload is invalid.');
        }
        $plain = openssl_decrypt(substr($envelope, 28), self::CIPHER, $this->key, OPENSSL_RAW_DATA, substr($envelope, 0, 12), substr($envelope, 12, 16));
        if (!is_string($plain)) {
            throw new RuntimeException('The job payload could not be decrypted.');
        }
        try {
            $payload = json_decode($plain, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The job payload is invalid.');
        }
        if (!is_array($payload)) {
            throw new RuntimeException('The job payload is invalid.');
        }
        return $payload;
    }
}

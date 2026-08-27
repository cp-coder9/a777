<?php

declare(strict_types=1);

namespace Architex\Platform\Identity;

use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;

final class PasswordHasher
{
    /** @var array{memory_cost:int,time_cost:int,threads:int} */
    private const OPTIONS = ['memory_cost' => 19_456, 'time_cost' => 2, 'threads' => 1];

    public function hash(#[SensitiveParameter] string $password): string
    {
        $this->assertValidLength($password);
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException('Argon2id password hashing is unavailable.');
        }
        $hash = password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }
        return $hash;
    }

    public function verify(#[SensitiveParameter] string $password, #[SensitiveParameter] string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(#[SensitiveParameter] string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    private function assertValidLength(string $password): void
    {
        $length = strlen($password);
        if ($length < 12 || $length > 128) {
            throw new InvalidArgumentException('Password must contain between 12 and 128 characters.');
        }
    }
}

<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

use InvalidArgumentException;

final class Identifier
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function binary(string $identifier): string
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $identifier) !== 1) {
            throw new InvalidArgumentException('Invalid identifier.');
        }
        $binary = hex2bin($identifier);
        if (!is_string($binary)) {
            throw new InvalidArgumentException('Invalid identifier.');
        }
        return $binary;
    }
}

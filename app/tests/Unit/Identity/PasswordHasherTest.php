<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Identity;

use Architex\Platform\Identity\PasswordHasher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashesVerifiesAndNeverStoresPlaintext(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct horse battery staple');

        self::assertNotSame('correct horse battery staple', $hash);
        self::assertTrue($hasher->verify('correct horse battery staple', $hash));
        self::assertFalse($hasher->verify('wrong password', $hash));
    }

    public function testSignalsThatAnObsoleteHashNeedsRehash(): void
    {
        $obsolete = password_hash('correct horse battery staple', PASSWORD_BCRYPT, ['cost' => 4]);
        self::assertIsString($obsolete);
        self::assertTrue((new PasswordHasher())->needsRehash($obsolete));
    }

    public function testRejectsPasswordsOutsideTheSupportedLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain between 12 and 128 characters.');
        (new PasswordHasher())->hash('too-short');
    }
}

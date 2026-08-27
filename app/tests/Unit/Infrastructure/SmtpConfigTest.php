<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use Architex\Infrastructure\Mail\Message;
use Architex\Infrastructure\Mail\SmtpConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmtpConfigTest extends TestCase
{
    public function testEmptyEnvironmentIsTruthfullyUnconfigured(): void
    {
        self::assertFalse(SmtpConfig::fromEnvironment([])->isConfigured());
    }

    public function testCompleteConfigurationIsAccepted(): void
    {
        $config = SmtpConfig::fromEnvironment([
            'SMTP_HOST' => 'smtp.example.test',
            'SMTP_PORT' => '587',
            'SMTP_ENCRYPTION' => 'starttls',
            'SMTP_USERNAME' => 'account',
            'SMTP_PASSWORD' => 'secret',
            'SMTP_FROM_EMAIL' => 'noreply@example.test',
            'SMTP_FROM_NAME' => 'Architex',
        ]);

        self::assertTrue($config->isConfigured());
        self::assertSame(587, $config->port);
        self::assertSame('starttls', $config->encryption);
    }

    /** @return iterable<string,array{array<string,string>}> */
    public static function invalidConfiguration(): iterable
    {
        yield 'header injection' => [[
            'SMTP_HOST' => "smtp.example.test\r\nRCPT TO:attacker@example.test",
            'SMTP_FROM_EMAIL' => 'noreply@example.test',
        ]];
        yield 'partial credentials' => [[
            'SMTP_HOST' => 'smtp.example.test',
            'SMTP_FROM_EMAIL' => 'noreply@example.test',
            'SMTP_USERNAME' => 'account',
        ]];
        yield 'invalid encryption' => [[
            'SMTP_HOST' => 'smtp.example.test',
            'SMTP_FROM_EMAIL' => 'noreply@example.test',
            'SMTP_ENCRYPTION' => 'optional',
        ]];
    }

    /** @param array<string,string> $environment */
    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidConfiguration(array $environment): void
    {
        $this->expectException(InvalidArgumentException::class);
        SmtpConfig::fromEnvironment($environment);
    }

    public function testMessageRejectsHeaderInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Message('person@example.test', "Subject\r\nBcc: attacker@example.test", 'Body');
    }
}

<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

use InvalidArgumentException;

final readonly class SmtpConfig
{
    private function __construct(
        public ?string $host,
        public int $port,
        public string $encryption,
        public string $username,
        public string $password,
        public string $fromEmail,
        public string $fromName,
    ) {}

    /** @param array<string,mixed> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $host = self::value($environment, 'SMTP_HOST');
        $from = self::value($environment, 'SMTP_FROM_EMAIL');
        $username = self::value($environment, 'SMTP_USERNAME');
        $password = self::value($environment, 'SMTP_PASSWORD', false);
        $encryption = strtolower(self::value($environment, 'SMTP_ENCRYPTION') ?: 'starttls');
        $portRaw = self::value($environment, 'SMTP_PORT') ?: ($encryption === 'tls' ? '465' : '587');
        $fromName = self::value($environment, 'SMTP_FROM_NAME') ?: 'Architex';

        if ($host === '' && $from === '' && $username === '' && $password === '') {
            return new self(null, (int)$portRaw, $encryption, '', '', '', $fromName);
        }
        if ($host === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('SMTP_HOST and a valid SMTP_FROM_EMAIL are required.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) === false && preg_match('/\A(?=.{1,253}\z)[A-Za-z0-9.-]+\z/', $host) !== 1) {
            throw new InvalidArgumentException('SMTP_HOST is invalid.');
        }
        if (preg_match('/\A[0-9]{1,5}\z/', $portRaw) !== 1 || (int)$portRaw < 1 || (int)$portRaw > 65535) {
            throw new InvalidArgumentException('SMTP_PORT must be between 1 and 65535.');
        }
        if (!in_array($encryption, ['none', 'starttls', 'tls'], true)) {
            throw new InvalidArgumentException('SMTP_ENCRYPTION must be none, starttls, or tls.');
        }
        if (($username === '') !== ($password === '')) {
            throw new InvalidArgumentException('SMTP_USERNAME and SMTP_PASSWORD must be configured together.');
        }
        if ($fromName === '' || strlen($fromName) > 120 || preg_match('/[\r\n]/', $fromName) === 1) {
            throw new InvalidArgumentException('SMTP configuration contains invalid characters.');
        }
        return new self($host, (int)$portRaw, $encryption, $username, $password, $from, $fromName);
    }

    public function isConfigured(): bool
    {
        return $this->host !== null;
    }

    /** @param array<string,mixed> $environment */
    private static function value(array $environment, string $key, bool $trim = true): string
    {
        $value = $environment[$key] ?? '';
        if (!is_string($value)) {
            throw new InvalidArgumentException($key . ' must be a string.');
        }
        return $trim ? trim($value) : $value;
    }
}

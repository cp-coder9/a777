<?php
declare(strict_types=1);

namespace Architex\Infrastructure\Integration;

final readonly class BridgeConfig
{
    private function __construct(public string $url, public string $token, public string $webhookSecret) {}

    /** Values are supplied by EnvironmentLoader; no secret files are read here. */
    public static function fromEnvironment(string $prefix): self
    {
        if (preg_match('/\A(?:FINANCE_BRIDGE|DESKTOP_BRIDGE)_[A-F0-9]{32}\z/D', $prefix) !== 1) {
            throw new IntegrationException('integration_configuration_invalid');
        }
        $values = [];
        foreach (['URL','TOKEN','WEBHOOK_SECRET'] as $suffix) {
            $value = getenv($prefix.'_'.$suffix);
            if (is_string($value)) $values[$suffix] = $value;
        }
        return self::fromValues($values);
    }

    /** @param array<string,mixed> $values */
    public static function fromValues(array $values): self
    {
        foreach (['URL','TOKEN','WEBHOOK_SECRET'] as $key) {
            if (!is_string($values[$key] ?? null) || $values[$key] === '') throw new IntegrationException('integration_not_configured');
        }
        $url = rtrim($values['URL'], '/');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user'], $parts['pass'])
            || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1
            || !self::publicHostname((string)($parts['host'] ?? ''))
            || preg_match('#\A(?:/[A-Za-z0-9_-]+)*\z#D', (string)($parts['path'] ?? '')) !== 1
            || strlen($values['TOKEN']) < 32 || strlen($values['TOKEN']) > 4096
            || preg_match('/[\x00-\x20\x7f]/', $values['TOKEN']) === 1
            || strlen($values['WEBHOOK_SECRET']) < 32 || strlen($values['WEBHOOK_SECRET']) > 4096) {
            throw new IntegrationException('integration_configuration_invalid');
        }
        return new self($url, $values['TOKEN'], $values['WEBHOOK_SECRET']);
    }

    private static function publicHostname(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) === false
            && preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/iD', $host) === 1
            && preg_match('/\.(?:localhost|local|internal|test|invalid)\z/iD', $host) !== 1;
    }
}

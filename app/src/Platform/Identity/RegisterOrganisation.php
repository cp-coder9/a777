<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

use InvalidArgumentException;

final readonly class RegisterOrganisation
{
    /** @param array<string,string> $acceptedNoticeVersions @param array<string,mixed> $deviceMetadata */
    private function __construct(
        public string $email,
        public string $password,
        public string $organisationName,
        public string $organisationSlug,
        public array $acceptedNoticeVersions,
        public ?string $ipAddress = null,
        public array $deviceMetadata = [],
    ) {}

    /** @param array<string,mixed> $input */
    public static function fromArray(array $input): self
    {
        $email = strtolower(trim(self::text($input, 'email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        $name = trim(self::text($input, 'organisationName'));
        if ($name === '' || strlen($name) > 160) {
            throw new InvalidArgumentException('Organisation name is invalid.');
        }
        $slug = strtolower(trim(self::text($input, 'organisationSlug')));
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{1,78}[a-z0-9])?$/D', $slug) !== 1) {
            throw new InvalidArgumentException('Organisation slug is invalid.');
        }
        $notices = $input['acceptedNoticeVersions'] ?? null;
        if (!is_array($notices)) {
            throw new InvalidArgumentException('Legal notice acceptances are required.');
        }
        $accepted = [];
        foreach ($notices as $type => $version) {
            if (!is_string($type) || !is_string($version) || $version === '' || strlen($version) > 32) {
                throw new InvalidArgumentException('Legal notice acceptances are invalid.');
            }
            $accepted[$type] = $version;
        }
        $device = $input['deviceMetadata'] ?? [];
        if (!is_array($device)) {
            throw new InvalidArgumentException('Device metadata is invalid.');
        }
        $ip = $input['ipAddress'] ?? null;
        if ($ip !== null && (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false)) {
            throw new InvalidArgumentException('IP address is invalid.');
        }
        return new self($email, self::text($input, 'password'), $name, $slug, $accepted, $ip, $device);
    }

    /** @param array<string,mixed> $input */
    private static function text(array $input, string $key): string
    {
        if (!isset($input[$key]) || !is_string($input[$key])) {
            throw new InvalidArgumentException($key . ' must be a string.');
        }
        return $input[$key];
    }
}

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
        public ?string $fullName = null,
        public ?string $roleKey = null,
        public array $profile = [],
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
        $fullName = self::optionalText($input, 'fullName', 180);
        $roleKey = self::optionalText($input, 'roleKey', 80);
        $roles = ['architect','client','bep','engineer','quantity_surveyor','town_planner','land_surveyor','energy_professional','fire_engineer','cpm','contractor','subcontractor','supplier','site_manager','health_safety','developer','freelancer','firm_admin','organisation_admin','admin','platform_admin'];
        if ($roleKey !== null && !in_array($roleKey, $roles, true)) {
            throw new InvalidArgumentException('Role profile is invalid.');
        }
        $profile = $input['profile'] ?? [];
        if (!is_array($profile) || count($profile) > 20) {
            throw new InvalidArgumentException('Role profile details are invalid.');
        }
        $normalisedProfile = [];
        foreach ($profile as $key => $value) {
            if (!is_string($key) || preg_match('/\A[a-z][a-z0-9_]{0,79}\z/D', $key) !== 1 || !is_string($value) || strlen(trim($value)) > 500) {
                throw new InvalidArgumentException('Role profile details are invalid.');
            }
            if (trim($value) !== '') $normalisedProfile[$key] = trim($value);
        }
        if (($fullName === null) !== ($roleKey === null)) {
            throw new InvalidArgumentException('Full name and role profile must be supplied together.');
        }
        return new self($email, self::text($input, 'password'), $name, $slug, $accepted, $fullName, $roleKey, $normalisedProfile, $ip, $device);
    }

    /** @param array<string,mixed> $input */
    private static function text(array $input, string $key): string
    {
        if (!isset($input[$key]) || !is_string($input[$key])) {
            throw new InvalidArgumentException($key . ' must be a string.');
        }
        return $input[$key];
    }

    /** @param array<string,mixed> $input */
    private static function optionalText(array $input, string $key, int $maximum): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') return null;
        if (!is_string($input[$key])) throw new InvalidArgumentException($key . ' must be a string.');
        $value = trim($input[$key]);
        if ($value === '' || strlen($value) > $maximum) throw new InvalidArgumentException($key . ' is invalid.');
        return $value;
    }
}

<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use RuntimeException;

/**
 * Pure, fail-closed policy for the guarded disposable verification database
 * provisioner (tools/v1-clone/provision-verification-db.php).
 *
 * Scope
 *   This class decides, and only decides, whether a process environment may
 *   provision the disposable verification databases. It is pure: it inspects
 *   the array it is handed and returns a descriptor. It never opens a socket,
 *   reads a file, falls back to a runtime config file or an .env, and never
 *   mutates its input. The connector (vendor/autoload, explicit AppConfig, the
 *   administrative PDO) lives in the CLI tool, so this decision can be unit
 *   tested and reviewed on its own.
 *
 * The two databases
 *   A verification run uses two DISTINCT task-owned schemas: the integration
 *   database (DB_NAME) and the browser database the managed launcher binds the
 *   application to (ARCHITEX_BROWSER_DB_NAME). The reviewed
 *   DisposableDatabaseGuard is applied to BOTH names — the second by
 *   substituting it for DB_NAME — before any caller can open a connection, so a
 *   name outside the task-owned namespace is refused for either target rather
 *   than only the first.
 *
 * Credentials
 *   Presence is validated; values are never returned, echoed, trimmed into the
 *   descriptor, or logged. The descriptor carries no password, no application
 *   user credential and no administrative principal: the caller supplies those
 *   to its connector directly. The administrative principal is required to be
 *   explicit under ARCHITEX_TEST_ADMIN_USER / ARCHITEX_TEST_ADMIN_PASSWORD and
 *   is never defaulted to root. The application user is required to be
 *   nonempty, non-root and a validated identifier, because it is interpolated
 *   into CREATE USER / GRANT / CREATE DATABASE statements by the caller.
 *
 * Messages
 *   Every refusal is a constant in MESSAGES and is never built from an input
 *   value, so a refusal can never echo a password or a database name.
 *   isKnownRefusal() lets a caller print a caught message only when this class
 *   produced it, and collapse anything foreign to a generic constant.
 */
final class VerificationProvisioningPolicy
{
    public const MODE_CHECK = 'check';
    public const MODE_CREATE = 'create';

    /** The two accepted modes. */
    public const MODES = [self::MODE_CHECK, self::MODE_CREATE];

    /** Exact loopback literals. A resolver-dependent hostname is not accepted. */
    public const LOOPBACK_HOSTS = ['127.0.0.1', '::1'];

    /**
     * MySQL account names are limited to 32 characters. The shape below keeps
     * the identifier quotable and free of separators, whitespace, quotes and
     * backticks, so the caller may interpolate it into DDL after backtick
     * quoting.
     */
    private const USER_PATTERN = '/^[A-Za-z][A-Za-z0-9_.-]{0,31}$/D';

    private const CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';

    /** Constant, secret-free refusal messages. Never built from an input value. */
    public const MESSAGES = [
        'mode' => 'Verification provisioning requires the check or create mode.',
        'optIn' => 'Verification provisioning requires ARCHITEX_ALLOW_DISPOSABLE_DB=1.',
        'appEnv' => 'Verification provisioning requires APP_ENV=test.',
        'host' => 'Verification provisioning requires a loopback database host.',
        'port' => 'Verification provisioning requires a decimal database port between 1 and 65535.',
        'integrationDatabase' => 'Verification provisioning requires the integration database name to be a nonempty string.',
        'browserDatabase' => 'Verification provisioning requires the browser database name to be a nonempty string.',
        'integrationGuard' => 'The disposable database guard refused the integration database.',
        'browserGuard' => 'The disposable database guard refused the browser database.',
        'databasesDistinct' => 'Verification provisioning requires the integration and browser databases to be distinct.',
        'user' => 'Verification provisioning requires a nonempty, non-root application database user.',
        'userShape' => 'Verification provisioning requires a validated application database user identifier.',
        'password' => 'Verification provisioning requires a nonempty application database password.',
        'passwordShape' => 'Verification provisioning requires a control-character-free application database password.',
        'adminUser' => 'Verification provisioning requires an explicit nonempty ARCHITEX_TEST_ADMIN_USER, which is never defaulted.',
        'adminPassword' => 'Verification provisioning requires an explicit nonempty ARCHITEX_TEST_ADMIN_PASSWORD, which is never defaulted.',
        'adminPasswordShape' => 'Verification provisioning requires a control-character-free ARCHITEX_TEST_ADMIN_PASSWORD.',
        'adminUserConflict' => 'Verification provisioning requires the application database user to differ from the administrative user.',
        'repositoryRoot' => 'Verification provisioning requires a repository root path.',
    ];

    /**
     * Validate an environment for one mode.
     *
     * The order is deliberate: the cheap, explicit checks first, then the
     * reviewed DisposableDatabaseGuard for the integration database, then the
     * same guard for the browser database. A caller may therefore treat a
     * successful return as "both names passed the guard", and no administrative
     * connection may exist yet because this method performs no I/O at all.
     *
     * @param array<string,mixed> $environment
     * @return array{mode:string, host:string, port:int, integrationDatabase:string, browserDatabase:string, appUser:string}
     * @throws RuntimeException with a MESSAGES constant when the environment is
     *   not an explicitly opted-in, loopback, task-owned, double-disposable
     *   environment, or when the requested mode is unknown.
     */
    public static function validate(array $environment, string $mode = self::MODE_CHECK): array
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new RuntimeException(self::MESSAGES['mode']);
        }

        if (($environment['ARCHITEX_ALLOW_DISPOSABLE_DB'] ?? null) !== '1') {
            throw new RuntimeException(self::MESSAGES['optIn']);
        }
        if (($environment['APP_ENV'] ?? null) !== 'test') {
            throw new RuntimeException(self::MESSAGES['appEnv']);
        }

        $host = $environment['DB_HOST'] ?? null;
        if (!is_string($host) || !in_array($host, self::LOOPBACK_HOSTS, true)) {
            throw new RuntimeException(self::MESSAGES['host']);
        }

        $port = self::port($environment['DB_PORT'] ?? null);

        $integration = self::databaseName($environment, 'DB_NAME', self::MESSAGES['integrationDatabase']);
        $browser = self::databaseName($environment, 'ARCHITEX_BROWSER_DB_NAME', self::MESSAGES['browserDatabase']);
        if ($integration === $browser) {
            throw new RuntimeException(self::MESSAGES['databasesDistinct']);
        }

        // The guard stays the single source of truth for the task-owned
        // namespace, and it is applied to BOTH names.
        self::assertGuardAllows($environment, self::MESSAGES['integrationGuard']);
        $browserEnvironment = $environment;
        $browserEnvironment['DB_NAME'] = $browser;
        self::assertGuardAllows($browserEnvironment, self::MESSAGES['browserGuard']);

        $appUser = self::appUser($environment);
        self::assertSecret($environment, 'DB_PASSWORD', self::MESSAGES['password'], self::MESSAGES['passwordShape']);

        if ($mode === self::MODE_CREATE) {
            // An explicit administrative principal only. Nothing here defaults
            // to root: an absent value is a refusal, not a fallback.
            $adminUser = self::adminUser($environment);
            self::assertSecret(
                $environment,
                'ARCHITEX_TEST_ADMIN_PASSWORD',
                self::MESSAGES['adminPassword'],
                self::MESSAGES['adminPasswordShape'],
            );
            if (strcasecmp($adminUser, $appUser) === 0) {
                throw new RuntimeException(self::MESSAGES['adminUserConflict']);
            }
        }

        return [
            'mode' => $mode,
            'host' => $host,
            'port' => $port,
            'integrationDatabase' => $integration,
            'browserDatabase' => $browser,
            'appUser' => $appUser,
        ];
    }

    /**
     * Whether both administrative credentials are explicitly usable. Pure, and
     * used only to report presence; the values are never returned.
     *
     * @param array<string,mixed> $environment
     */
    public static function adminCredentialsPresent(array $environment): bool
    {
        return self::usable($environment['ARCHITEX_TEST_ADMIN_USER'] ?? null)
            && self::usable($environment['ARCHITEX_TEST_ADMIN_PASSWORD'] ?? null);
    }

    /**
     * Whether a message was produced by this policy. A caller may print a
     * caught message only when this returns true; anything else is foreign and
     * must collapse to the caller's own constant.
     */
    public static function isKnownRefusal(string $message): bool
    {
        return in_array($message, self::MESSAGES, true);
    }

    /**
     * The migration directory, derived from the repository root and nothing
     * else. There is no runtime-config or .env fallback: the CLI hands its own
     * repository root in.
     *
     * @throws RuntimeException when the root cannot name a directory.
     */
    public static function migrationDirectoryFor(string $repositoryRoot): string
    {
        $root = rtrim($repositoryRoot, "/\\");
        if ($root === '') {
            throw new RuntimeException(self::MESSAGES['repositoryRoot']);
        }

        return $root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ['app', 'database', 'migrations']);
    }

    /** @param array<string,mixed> $environment */
    private static function assertGuardAllows(array $environment, string $message): void
    {
        try {
            DisposableDatabaseGuard::assertAllowed($environment);
        } catch (RuntimeException) {
            // The guard's own text is dropped: the policy speaks its own
            // constant so a refusal can never carry an input value.
            throw new RuntimeException($message);
        }
    }

    /** @param array<string,mixed> $environment */
    private static function databaseName(array $environment, string $key, string $message): string
    {
        $value = $environment[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException($message);
        }

        return $value;
    }

    /** @param array<string,mixed> $environment */
    private static function appUser(array $environment): string
    {
        $value = $environment['DB_USER'] ?? null;
        if (!is_string($value) || trim($value) === '' || strcasecmp(trim($value), 'root') === 0) {
            throw new RuntimeException(self::MESSAGES['user']);
        }

        $user = trim($value);
        if (preg_match(self::USER_PATTERN, $user) !== 1) {
            throw new RuntimeException(self::MESSAGES['userShape']);
        }

        return $user;
    }

    /** @param array<string,mixed> $environment */
    private static function adminUser(array $environment): string
    {
        $value = $environment['ARCHITEX_TEST_ADMIN_USER'] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(self::MESSAGES['adminUser']);
        }

        // The administrative principal is never interpolated into DDL — it only
        // ever reaches a PDO constructor — so no identifier shape is imposed on
        // it, and an explicitly supplied root administrative principal is
        // honoured because root is never a default here.
        return trim($value);
    }

    /**
     * Presence and shape only. The password is required to be a nonempty,
     * non-blank, control-character-free string; it is never trimmed for use
     * (AppConfig passes DB_PASSWORD through verbatim) and never returned.
     *
     * @param array<string,mixed> $environment
     */
    private static function assertSecret(array $environment, string $key, string $message, string $shapeMessage): void
    {
        $value = $environment[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException($message);
        }
        if (preg_match(self::CONTROL_CHARACTER_PATTERN, $value) === 1) {
            throw new RuntimeException($shapeMessage);
        }
    }

    private static function usable(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function port(mixed $value): int
    {
        if (is_int($value)) {
            $port = $value;
        } elseif (is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1) {
            $port = (int) $value;
        } else {
            throw new RuntimeException(self::MESSAGES['port']);
        }

        if ($port < 1 || $port > 65_535) {
            throw new RuntimeException(self::MESSAGES['port']);
        }

        return $port;
    }
}

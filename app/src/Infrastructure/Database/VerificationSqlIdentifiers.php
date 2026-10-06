<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

/**
 * Pure SQL identifier and privilege-pattern helpers for the guarded disposable
 * verification database provisioner
 * (tools/v1-clone/provision-verification-db.php).
 *
 * Why two kinds of quoting exist here
 *   CREATE DATABASE, DROP DATABASE, CREATE USER and DROP USER name a resource
 *   OUTRIGHT, so the name is quoted as a literal identifier: `identifier()`
 *   backtick-quotes it and doubles any embedded backtick, and nothing else.
 *   Escaping a wildcard there would change the resource being named.
 *
 *   GRANT is different. In MySQL the database name in the privilege level
 *   (`GRANT ... ON db.*`) is a PATTERN, not a literal, and it is a pattern even
 *   when it is backtick-quoted. `_` therefore matches any single character and
 *   `%` matches any run of characters. The task-owned schema names this tool
 *   creates always contain `_` separators, so an unescaped `GRANT ... ON
 *   `architex_taskvp_deadbeef_test`.*` would also grant on sibling names such as
 *   `architexXtaskvpYdeadbeefZtest` — a wider scope than the two schemas this
 *   tool claims to grant on. `grantPattern()` therefore escapes the pattern
 *   metacharacters before quoting: `\` -> `\\` (first, so the escaping
 *   backslashes are never themselves escaped), then `_` -> `\_`, then
 *   `%` -> `\%`.
 *
 *   The escaping depends on `\` being an escape character, which is why the
 *   provisioner pins the session sql_mode to a value that does not contain
 *   NO_BACKSLASH_ESCAPES before it issues any statement.
 *
 * Everything here is a pure string transform: no I/O, no state, no secrets.
 */
final class VerificationSqlIdentifiers
{
    /**
     * Quote a name as a literal MySQL identifier: doubled backticks only, no
     * wildcard escaping. Use this for every statement that names a resource.
     */
    public static function identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Quote a database name as a MySQL GRANT privilege pattern: pattern
     * metacharacters escaped, then backtick-quoted. Use this ONLY in a GRANT
     * privilege level, never to name a resource.
     */
    public static function grantPattern(string $database): string
    {
        // Order matters: the backslash is doubled before the wildcards are
        // introduced, so the backslashes that escape `_` and `%` are not
        // themselves escaped again.
        $escaped = str_replace('\\', '\\\\', $database);
        $escaped = str_replace('_', '\\_', $escaped);
        $escaped = str_replace('%', '\\%', $escaped);

        return self::identifier($escaped);
    }

    /**
     * The exact CREATE DATABASE statement for one task-owned schema. The name is
     * a literal identifier: this statement can only ever create that schema.
     */
    public static function createDatabaseStatement(string $database): string
    {
        return 'CREATE DATABASE ' . self::identifier($database)
            . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    /** The exact DROP statement for one schema this invocation created itself. */
    public static function dropDatabaseStatement(string $database): string
    {
        return 'DROP DATABASE IF EXISTS ' . self::identifier($database);
    }

    /**
     * The exact CREATE USER statement for the validated loopback account host.
     * `$quotedPassword` is the already-quoted password literal (PDO::quote
     * output); it is passed through verbatim and never stored here.
     */
    public static function createUserStatement(string $user, string $host, string $quotedPassword): string
    {
        return 'CREATE USER ' . self::identifier($user) . '@' . self::identifier($host)
            . ' IDENTIFIED BY ' . $quotedPassword;
    }

    /** The exact DROP statement for the account this invocation created itself. */
    public static function dropUserStatement(string $user, string $host): string
    {
        return 'DROP USER IF EXISTS ' . self::identifier($user) . '@' . self::identifier($host);
    }

    /**
     * The exact GRANT statement for one schema. The schema is an ESCAPED pattern
     * so the privilege cannot reach any schema other than this one; the account
     * is a literal identifier. No global privilege level, no GRANT OPTION.
     */
    public static function grantStatement(string $database, string $user, string $host): string
    {
        return 'GRANT ALL PRIVILEGES ON ' . self::grantPattern($database) . '.* TO '
            . self::identifier($user) . '@' . self::identifier($host);
    }
}

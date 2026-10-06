<?php
// Isolated executable API fixture: no application config, DB, or network reads.
namespace {
    function fixture_fail(string $stage): void {
        if (getenv('PROBE_SCENARIO') === $stage) throw new \RuntimeException('SECRET password/path/SQL');
    }
    fixture_fail('bootstrap');
    if (getenv('PROBE_SCENARIO') === 'exit') exit('SECRET bootstrap body');
}
namespace Architex\Infrastructure\Config {
    final class AppConfig {
        public static function fromEnvironment(array $environment): self { \fixture_fail('config'); return new self(); }
    }
}
namespace Architex\Infrastructure\Database {
    final class FixtureStatement extends \PDOStatement {
        public function fetchColumn(int $column = 0): mixed { return 1; }
    }
    final class FixturePdo extends \PDO {
        public function __construct() {}
        public function query(string $query, ?int $fetchMode = null, mixed ...$args): \PDOStatement|false { return new FixtureStatement(); }
    }
    final class ConnectionFactory {
        public static function create(\Architex\Infrastructure\Config\AppConfig $config): \PDO { \fixture_fail('database'); return new FixturePdo(); }
    }
    // Exercise the repository's real report class, not an invented isCurrent API.
    require getenv('FIXTURE_REPORT_PATH');
    final class MigrationRunner {
        public function __construct(private readonly \PDO $pdo, private readonly string $migrationDirectory) {}
        public function status(): array|MigrationReport {
            \fixture_fail('migrations');
            return match (getenv('PROBE_SCENARIO')) {
                'report' => new MigrationReport(0, 0, [], []),
                'pending' => ['pendingCount' => 2, 'pendingVersions' => ['SECRET_version']],
                'missing' => ['pendingVersions' => []],
                'string' => ['pendingCount' => '0'],
                'negative' => ['pendingCount' => -1],
                default => ['appliedCount' => 0, 'pendingCount' => 0, 'appliedVersions' => [], 'pendingVersions' => []],
            };
        }
    }
}
namespace Architex\Infrastructure\Mail {
    final class MailRuntime {
        public static function fromEnvironment(\PDO $pdo, array $environment): self { \fixture_fail('mail'); return new self(); }
        public function isReady(): bool { return getenv('PROBE_SCENARIO') !== 'mail_not_ready'; }
    }
}

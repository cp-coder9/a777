<?php
declare(strict_types=1);

// Operator-only local bridge. Never included in a public runtime route.
ini_set('display_errors', '0');
try {
    if (PHP_SAPI !== 'cli' || $argc !== 4) throw new RuntimeException('CLI arguments required.');
    $environment = getenv();
    if (!is_array($environment)) throw new RuntimeException('Explicit restore environment required.');
    $root = dirname(__DIR__);
    $autoload = $root . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('Local runtime dependencies unavailable.');
    require $autoload;
    // Prepend AFTER Composer, including when dependencies are borrowed locally.
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'Architex\\';
        if (!str_starts_with($class, $prefix)) return;
        $file = $root . '/app/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require $file;
    }, true, true);
    if ((new ReflectionClass(\Architex\Infrastructure\Backup\RestoreService::class))->getFileName() !== $root . '/app/src/Infrastructure/Backup/RestoreService.php') {
        // Native Windows Reflection paths may use a different separator.
        $resolved = realpath((new ReflectionClass(\Architex\Infrastructure\Backup\RestoreService::class))->getFileName());
        if ($resolved !== realpath($root . '/app/src/Infrastructure/Backup/RestoreService.php')) throw new RuntimeException('Restore source identity mismatch.');
    }
    \Architex\Infrastructure\Database\DisposableDatabaseGuard::assertAllowed($environment);
    $password = $environment['BACKUP_ARCHIVE_PASSWORD'] ?? '';
    $key = $environment['APP_KEY'] ?? '';
    $archive = realpath($argv[1]);
    $migrations = realpath($argv[2]);
    if ($archive === false || $migrations === false || is_link($argv[1]) || is_link($argv[2])) throw new RuntimeException('Invalid local input.');
    $before = hash_file('sha256', $archive);
    $service = new \Architex\Infrastructure\Backup\RestoreService($migrations, $password, $key);
    $report = $service->restore($archive, $environment, $environment['DB_NAME'], $argv[3]);
    if (!is_string($before) || !hash_equals($before, (string) hash_file('sha256', $archive))) throw new RuntimeException('Archive changed during restore.');
    // RestoreService has authenticated manifest HMAC and all entry hashes and
    // compared restored table/row/file counts and applied migration set.
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true || !$zip->setPassword($password)) throw new RuntimeException('Manifest unavailable.');
    $manifest = $zip->getFromName('manifest.json');
    $zip->close();
    if (!is_string($manifest)) throw new RuntimeException('Manifest unavailable.');
    echo json_encode(['backupId'=>$report->backupId, 'database'=>$report->database,
        'rowCount'=>$report->rowCount, 'fileCount'=>$report->fileCount,
        'manifestSha256'=>hash('sha256', $manifest)], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "Guarded local restore failed; retain encrypted archive and recovery journal.\n");
    exit(1);
}

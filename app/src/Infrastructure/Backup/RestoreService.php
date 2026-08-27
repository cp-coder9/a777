<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Backup;

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Database\DisposableDatabaseGuard;
use Architex\Infrastructure\Database\MigrationRunner;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

final readonly class RestoreService
{
    private string $hmacKey;

    public function __construct(private string $migrationDirectory, private string $archivePassword, string $base64HmacKey)
    {
        if (strlen($archivePassword) < 20) throw new RuntimeException('The backup archive password is too short.');
        $key = base64_decode($base64HmacKey, true);
        if (!is_string($key) || strlen($key) !== 32) throw new RuntimeException('The backup HMAC key is invalid.');
        $this->hmacKey = $key;
    }

    /** @param array<string,string> $environment */
    public function restore(string $archive, array $environment, string $confirmedDatabase, string $fileRoot): RestoreReport
    {
        DisposableDatabaseGuard::assertAllowed($environment);
        $config = AppConfig::fromEnvironment($environment);
        if (!hash_equals($config->dbName, $confirmedDatabase)) throw new RuntimeException('The confirmed restore database does not match DB_NAME.');
        if (is_dir($fileRoot) && (new FilesystemIterator($fileRoot))->valid()) throw new RuntimeException('The restore file root must be empty.');
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true || !$zip->setPassword($this->archivePassword)) throw new RuntimeException('The backup archive could not be opened.');
        $databaseCreated = false;
        try {
            $manifestJson = $zip->getFromName('manifest.json');
            if (!is_string($manifestJson)) throw new RuntimeException('The backup manifest is unavailable.');
            $manifest = json_decode($manifestJson, true, 64, JSON_THROW_ON_ERROR);
            $this->verifyManifest($zip, $manifest);
            $admin = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->dbHost, $config->dbPort), $config->dbUser, $config->dbPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
            $admin->exec('DROP DATABASE IF EXISTS `' . $config->dbName . '`');
            $admin->exec('CREATE DATABASE `' . $config->dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $databaseCreated = true;
            $pdo = ConnectionFactory::create($config);
            $migrationReport = (new MigrationRunner($pdo, $this->migrationDirectory))->migrate();
            if ($migrationReport->appliedVersions !== $manifest['database']['migrations']) throw new RuntimeException('The backup migration set does not match the release migrations.');
            $currentTables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
            sort($currentTables, SORT_STRING);
            $backupTables = array_keys($manifest['database']['tables']); sort($backupTables, SORT_STRING);
            if ($currentTables !== $backupTables) {
                $missing = array_values(array_diff($backupTables, $currentTables));
                $extra = array_values(array_diff($currentTables, $backupTables));
                throw new RuntimeException('The backup schema does not match the release migrations. Missing: ' . implode(',', $missing) . '; extra: ' . implode(',', $extra));
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->beginTransaction();
            $rowCount = 0;
            try {
                foreach ($currentTables as $table) $pdo->exec('DELETE FROM `' . $table . '`');
                foreach ($manifest['database']['tables'] as $table => $definition) {
                    if (!is_string($table) || preg_match('/\A[a-z][a-z0-9_]*\z/', $table) !== 1 || !is_array($definition) || !is_string($definition['entry'] ?? null)) throw new RuntimeException('A backup table definition is invalid.');
                    $stream = $zip->getStream($definition['entry']);
                    if (!is_resource($stream)) throw new RuntimeException('A backup table entry is unavailable.');
                    $tableRows = 0;
                    try {
                        while (($line = fgets($stream)) !== false) {
                            $encoded = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
                            if (!is_array($encoded) || $encoded === []) throw new RuntimeException('A backup table row is invalid.');
                            $columns = array_keys($encoded);
                            foreach ($columns as $column) if (preg_match('/\A[a-z][a-z0-9_]*\z/', $column) !== 1) throw new RuntimeException('A backup column name is invalid.');
                            $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')';
                            $statement = $pdo->prepare($sql);
                            foreach ($encoded as $column => $value) $this->bindValue($statement, $column, $value);
                            $statement->execute(); $rowCount++; $tableRows++;
                        }
                    } finally { fclose($stream); }
                    if ($tableRows !== (int)($definition['rows'] ?? -1)) throw new RuntimeException('A restored table row count does not match the backup manifest.');
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            } finally { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); }
            $fileCount = $this->restoreFiles($zip, $manifest['files'], $fileRoot);
            $zip->close();
            return new RestoreReport((string)$manifest['backupId'], $config->dbName, $rowCount, $fileCount);
        } catch (Throwable $exception) {
            $zip->close();
            if ($databaseCreated) {
                try {
                    $admin ??= new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->dbHost, $config->dbPort), $config->dbUser, $config->dbPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                    $admin->exec('DROP DATABASE IF EXISTS `' . $config->dbName . '`');
                } catch (Throwable) {}
            }
            $this->removeTree($fileRoot);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $manifest */
    private function verifyManifest(ZipArchive $zip, array $manifest): void
    {
        if (($manifest['format'] ?? null) !== 'architex-backup-v1' || !is_array($manifest['entries'] ?? null) || !is_array($manifest['database']['tables'] ?? null) || !is_array($manifest['files'] ?? null)) throw new RuntimeException('The backup manifest is invalid.');
        $hmac = $manifest['integrityHmac'] ?? null; $backupId = $manifest['backupId'] ?? null;
        if (!is_string($hmac) || !is_string($backupId)) throw new RuntimeException('The backup manifest is invalid.');
        unset($manifest['integrityHmac'], $manifest['backupId']);
        $canonical = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (!hash_equals(hash_hmac('sha256', $canonical, $this->hmacKey), $hmac) || !hash_equals(hash('sha256', $canonical), $backupId)) throw new RuntimeException('The backup manifest signature is invalid.');
        foreach ($manifest['entries'] as $entry => $expected) {
            if (!is_string($entry) || preg_match('#\A(?:database/[a-z][a-z0-9_]*\.jsonl|files/[a-f0-9]{32}/[a-f0-9]{48})\z#', $entry) !== 1 || !is_array($expected)) throw new RuntimeException('A backup entry is invalid.');
            $stream = $zip->getStream($entry);
            if (!is_resource($stream)) throw new RuntimeException('A backup entry is unavailable.');
            $hash = hash_init('sha256'); $bytes = 0;
            while (!feof($stream)) { $chunk = fread($stream, 1_048_576); if (!is_string($chunk)) { fclose($stream); throw new RuntimeException('A backup entry could not be read.'); } hash_update($hash, $chunk); $bytes += strlen($chunk); }
            fclose($stream);
            if (!hash_equals((string)($expected['sha256'] ?? ''), hash_final($hash)) || $bytes !== (int)($expected['bytes'] ?? -1)) throw new RuntimeException('A backup entry failed integrity validation.');
        }
    }

    /** @param array<string,mixed> $encoded */
    private function bindValue(\PDOStatement $statement, string $column, array $encoded): void
    {
        $type = $encoded['type'] ?? null;
        if ($type === 'null') { $statement->bindValue($column, null, PDO::PARAM_NULL); return; }
        if ($type === 'integer') { $statement->bindValue($column, (int)$encoded['value'], PDO::PARAM_INT); return; }
        if ($type === 'double') { $statement->bindValue($column, (string)$encoded['value'], PDO::PARAM_STR); return; }
        if ($type !== 'base64' || !is_string($encoded['value'] ?? null)) throw new RuntimeException('A backup value is invalid.');
        $decoded = base64_decode($encoded['value'], true);
        if (!is_string($decoded)) throw new RuntimeException('A backup value is invalid.');
        $statement->bindValue($column, $decoded, PDO::PARAM_STR);
    }

    /** @param list<array<string,mixed>> $files */
    private function restoreFiles(ZipArchive $zip, array $files, string $root): int
    {
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new RuntimeException('The restore file root is unavailable.');
        $count = 0;
        foreach ($files as $file) {
            if (!is_array($file)) throw new RuntimeException('A backup file definition is invalid.');
            $organisation = $file['organisationId'] ?? ''; $storage = $file['storageName'] ?? ''; $entry = $file['entry'] ?? '';
            if (!is_string($organisation) || !is_string($storage) || !is_string($entry) || preg_match('/\A[a-f0-9]{32}\z/', $organisation) !== 1 || preg_match('/\A[a-f0-9]{48}\z/', $storage) !== 1) throw new RuntimeException('A backup file definition is invalid.');
            $directory = $root . DIRECTORY_SEPARATOR . substr($organisation, 0, 2) . DIRECTORY_SEPARATOR . $organisation;
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('A restore file directory is unavailable.');
            $stream = $zip->getStream($entry); if (!is_resource($stream)) throw new RuntimeException('A backup file is unavailable.');
            $temporary = $directory . DIRECTORY_SEPARATOR . $storage . '.part'; $handle = fopen($temporary, 'xb');
            if (!is_resource($handle)) { fclose($stream); throw new RuntimeException('A restored file could not be created.'); }
            $hash = hash_init('sha256'); $bytes = 0;
            try {
                while (!feof($stream)) { $chunk = fread($stream, 1_048_576); if (!is_string($chunk)) throw new RuntimeException('A backup file could not be read.'); if ($chunk !== '') $this->writeAll($handle, $chunk); hash_update($hash, $chunk); $bytes += strlen($chunk); }
            } finally { fclose($stream); fclose($handle); }
            if (!hash_equals((string)$file['sha256'], hash_final($hash)) || $bytes !== (int)$file['bytes'] || !chmod($temporary, 0600) || !rename($temporary, $directory . DIRECTORY_SEPARATOR . $storage)) throw new RuntimeException('A restored file failed integrity validation.');
            $count++;
        }
        return $count;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        @rmdir($path);
    }

    /** @param resource $handle */
    private function writeAll($handle, string $data): void
    {
        $offset = 0;
        while ($offset < strlen($data)) {
            $written = fwrite($handle, substr($data, $offset));
            if (!is_int($written) || $written < 1) throw new RuntimeException('A restored file could not be written.');
            $offset += $written;
        }
    }
}

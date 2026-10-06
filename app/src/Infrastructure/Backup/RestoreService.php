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
            [$rowCount, $fileCount] = $this->importVerifiedArchive($pdo, $zip, $manifest, $fileRoot);
            $zip->close();
            return new RestoreReport((string)$manifest['backupId'], $config->dbName, $rowCount, $fileCount);
        } catch (Throwable $exception) {
            $zip->close();
            // DDL is not rollbackable. Retain the owned failed schema and file staging
            // for operator inspection; importVerifiedArchive rolls back row mutations.
            throw $exception;
        }
    }

    /** A separate capability path: never creates or drops a database; failures are retained for the operator. */
    public function restoreIntoProvisionedEmptyDatabase(string $archive, PDO $targetPdo, AccountOwnedRestoreTarget $target, string $fileRoot): RestoreReport
    {
        $target->assertEmpty($targetPdo);
        if (is_link($fileRoot) || (file_exists($fileRoot) && (!is_dir($fileRoot) || (new FilesystemIterator($fileRoot))->valid()))) throw new RuntimeException('The restore file root must be empty and not a symlink.');
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true || !$zip->setPassword($this->archivePassword)) throw new RuntimeException('The backup archive could not be opened.');
        try {
            $json = $zip->getFromName('manifest.json');
            if (!is_string($json)) throw new RuntimeException('The backup manifest is unavailable.');
            $manifest = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            $this->verifyManifest($zip, $manifest);
            $target->assertArchiveSource($manifest['database']['name'] ?? null);
            $this->assertArchivedMigrationTree($zip, $manifest);
            $target->assertEmpty($targetPdo);
            [$rows, $files] = $this->importVerifiedArchive($targetPdo, $zip, $manifest, $fileRoot);
            return new RestoreReport((string)$manifest['backupId'], $target->database(), $rows, $files);
        } finally { $zip->close(); }
    }

    /** @param array<string,mixed> $manifest @return array{int,int} */
    private function importVerifiedArchive(PDO $pdo, ZipArchive $zip, array $manifest, string $fileRoot): array
    {
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
                    $generatedColumns = array_fill_keys($this->generatedColumns($pdo, $table), true);
                    $stream = $zip->getStream($definition['entry']);
                    if (!is_resource($stream)) throw new RuntimeException('A backup table entry is unavailable.');
                    $tableRows = 0;
                    try {
                        while (($line = fgets($stream)) !== false) {
                            $encoded = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
                            if (!is_array($encoded) || $encoded === []) throw new RuntimeException('A backup table row is invalid.');
                            $columns = array_keys($encoded);
                            foreach ($columns as $column) if (preg_match('/\A[a-z][a-z0-9_]*\z/', $column) !== 1) throw new RuntimeException('A backup column name is invalid.');
                            $encoded = array_diff_key($encoded, $generatedColumns);
                            if ($encoded === []) throw new RuntimeException('A backup table row has no restorable columns.');
                            $columns = array_keys($encoded);
                            $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')';
                            $statement = $pdo->prepare($sql);
                            foreach ($encoded as $column => $value) $this->bindValue($statement, $column, $value);
                            $statement->execute(); $rowCount++; $tableRows++;
                        }
                    } finally { fclose($stream); }
                    if ($tableRows !== (int)($definition['rows'] ?? -1)) throw new RuntimeException('A restored table row count does not match the backup manifest.');
                }
                $this->assertForeignKeys($pdo);
                // Files are owned staging until commit; retain them and DDL on failure.
                $fileCount = $this->restoreFiles($zip, $manifest['files'], $fileRoot);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            } finally { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); }
        return [$rowCount, $fileCount];
    }

    private function assertForeignKeys(PDO $pdo): void
    {
        $rows = $pdo->query("SELECT k.CONSTRAINT_SCHEMA,k.TABLE_NAME,k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
        $groups = [];
        foreach ($rows as $row) $groups[json_encode([$row['CONSTRAINT_SCHEMA'],$row['TABLE_NAME'],$row['CONSTRAINT_NAME']], JSON_THROW_ON_ERROR)][] = $row;
        $quote = static fn(string $id): string => '`' . str_replace('`', '``', $id) . '`';
        foreach ($groups as $columns) {
            $first = $columns[0]; $nonnull = []; $matches = [];
            foreach ($columns as $column) {
                $child = 'c.' . $quote($column['COLUMN_NAME']);
                $nonnull[] = $child . ' IS NOT NULL';
                $matches[] = 'p.' . $quote($column['REFERENCED_COLUMN_NAME']) . '=' . $child;
            }
            $sql = 'SELECT 1 FROM ' . $quote($first['CONSTRAINT_SCHEMA']) . '.' . $quote($first['TABLE_NAME']) . ' c WHERE ' . implode(' AND ', $nonnull) . ' AND NOT EXISTS (SELECT 1 FROM ' . $quote($first['REFERENCED_TABLE_SCHEMA']) . '.' . $quote($first['REFERENCED_TABLE_NAME']) . ' p WHERE ' . implode(' AND ', $matches) . ') LIMIT 1';
            if ($pdo->query($sql)->fetchColumn() !== false) throw new RuntimeException('Restored rows violate foreign key ' . $first['CONSTRAINT_NAME'] . '.');
        }
    }

    private function assertArchivedMigrationTree(ZipArchive $zip, array $manifest): void
    {
        $entry = $manifest['database']['tables']['schema_migrations']['entry'] ?? null;
        if (!is_string($entry) || !isset($manifest['entries'][$entry])) throw new RuntimeException('Archived migration evidence unavailable.');
        $stream = $zip->getStream($entry);
        if (!is_resource($stream)) throw new RuntimeException('Archived migration evidence unavailable.');
        $archived = [];
        try {
            while (($line = fgets($stream)) !== false) {
                $row = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
                $version = base64_decode($row['version']['value'] ?? '', true);
                $checksum = base64_decode($row['checksum']['value'] ?? '', true);
                if (!is_string($version) || !is_string($checksum) || isset($archived[$version])) throw new RuntimeException('Invalid archived migration evidence.');
                $archived[$version] = $checksum;
            }
        } finally { fclose($stream); }
        $active = [];
        foreach (glob($this->migrationDirectory . '/*.sql') ?: [] as $file) {
            if (is_link($file)) throw new RuntimeException('Symlink migration refused.');
            $active[basename($file, '.sql')] = hash_file('sha256', $file);
        }
        ksort($active); ksort($archived);
        if ($active === [] || $active !== $archived || array_keys($archived) !== $manifest['database']['migrations']) throw new RuntimeException('Active migration tree does not exactly match archived checksums.');
    }

    /** @return list<string> */
    private function generatedColumns(PDO $pdo, string $table): array
    {
        $statement = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND is_generated='ALWAYS' ORDER BY ordinal_position");
        $statement->execute(['table' => $table]);
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
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
        $bindings = [];
        foreach ($manifest['database']['tables'] as $table => $definition) {
            if (!is_string($table) || preg_match('/\A[a-z][a-z0-9_]*\z/', $table) !== 1 || !is_array($definition) || ($definition['entry'] ?? null) !== 'database/' . $table . '.jsonl' || !is_int($definition['rows'] ?? null) || $definition['rows'] < 0) throw new RuntimeException('A backup table binding is invalid.');
            $entry = $definition['entry'];
            if (!isset($manifest['entries'][$entry]) || isset($bindings[$entry])) throw new RuntimeException('A backup entry binding is missing or duplicate.');
            $bindings[$entry] = true;
        }
        foreach ($manifest['files'] as $file) {
            if (!is_array($file) || !is_string($file['organisationId'] ?? null) || !is_string($file['storageName'] ?? null) || preg_match('/\A[a-f0-9]{32}\z/', $file['organisationId']) !== 1 || preg_match('/\A[a-f0-9]{48}\z/', $file['storageName']) !== 1) throw new RuntimeException('A backup file binding is invalid.');
            $entry = 'files/' . $file['organisationId'] . '/' . $file['storageName'];
            $expected = $manifest['entries'][$entry] ?? null;
            if (($file['entry'] ?? null) !== $entry || !is_array($expected) || isset($bindings[$entry]) || ($file['sha256'] ?? null) !== ($expected['sha256'] ?? null) || ($file['bytes'] ?? null) !== ($expected['bytes'] ?? null)) throw new RuntimeException('A backup file binding is missing, duplicate or inconsistent.');
            $bindings[$entry] = true;
        }
        if (count($bindings) !== count($manifest['entries'])) throw new RuntimeException('An archive entry has no binding.');
        $seen = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || isset($seen[$name]) || ($name !== 'manifest.json' && !isset($bindings[$name]))) throw new RuntimeException('An archive entry is unlisted or duplicate.');
            $seen[$name] = true;
        }
        foreach ($manifest['entries'] as $entry => $expected) {
            if (!is_string($entry) || preg_match('#\A(?:database/[a-z][a-z0-9_]*\.jsonl|files/[a-f0-9]{32}/[a-f0-9]{48})\z#', $entry) !== 1 || !is_array($expected) || !is_string($expected['sha256'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/', $expected['sha256']) !== 1 || !is_int($expected['bytes'] ?? null) || $expected['bytes'] < 0) throw new RuntimeException('A backup entry is invalid.');
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

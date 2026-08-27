<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Backup;

use Architex\Platform\Files\LocalFileStorage;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

final readonly class BackupService
{
    private string $hmacKey;

    public function __construct(private PDO $pdo, private string $fileRoot, private string $archivePassword, string $base64HmacKey)
    {
        if (strlen($archivePassword) < 20) throw new RuntimeException('The backup archive password is too short.');
        $key = base64_decode($base64HmacKey, true);
        if (!is_string($key) || strlen($key) !== 32) throw new RuntimeException('The backup HMAC key is invalid.');
        $this->hmacKey = $key;
    }

    public function create(string $output): BackupReport
    {
        $output = str_ends_with($output, '.axbackup') ? $output : $output . '.axbackup';
        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('The backup output directory is unavailable.');
        if (file_exists($output)) throw new RuntimeException('The immutable backup already exists.');
        $temporaryArchive = $output . '.part-' . bin2hex(random_bytes(6));
        $working = $directory . DIRECTORY_SEPARATOR . '.backup-' . bin2hex(random_bytes(8));
        if (!mkdir($working, 0700, true)) throw new RuntimeException('The backup working directory is unavailable.');
        $zip = new ZipArchive();
        try {
            if ($zip->open($temporaryArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true || !$zip->setPassword($this->archivePassword)) {
                throw new RuntimeException('The encrypted backup archive could not be created.');
            }
            $database = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($database) || $database === '') throw new RuntimeException('No database is selected for backup.');
            $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->pdo->beginTransaction();
            $entries = []; $tables = []; $rowCount = 0;
            foreach ($this->tables() as $table) {
                if (preg_match('/\A[a-z][a-z0-9_]*\z/', $table) !== 1) throw new RuntimeException('The backup table name is invalid.');
                $entry = 'database/' . $table . '.jsonl';
                $path = $working . DIRECTORY_SEPARATOR . $table . '.jsonl';
                $handle = fopen($path, 'wb');
                if (!is_resource($handle)) throw new RuntimeException('A backup table stream could not be created.');
                $count = 0;
                try {
                    $query = $this->pdo->query('SELECT * FROM `' . $table . '`' . $this->orderClause($table));
                    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                        $line = json_encode($this->encodeRow($row), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
                        $this->writeAll($handle, $line);
                        $count++;
                    }
                } finally { fclose($handle); }
                $this->addEncryptedFile($zip, $entry, $path);
                $entryHash = hash_file('sha256', $path); $entrySize = filesize($path);
                if (!is_string($entryHash) || !is_int($entrySize)) throw new RuntimeException('A backup table entry failed integrity validation.');
                $entries[$entry] = ['sha256'=>$entryHash, 'bytes'=>$entrySize];
                $tables[$table] = ['entry'=>$entry,'rows'=>$count];
                $rowCount += $count;
            }
            $files = []; $storage = new LocalFileStorage($this->fileRoot);
            $query = $this->pdo->query("SELECT LOWER(HEX(organisation_id)) organisation_id,storage_name,sha256,byte_size FROM stored_files WHERE status='active' ORDER BY organisation_id,storage_name");
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $organisation = (string)$row['organisation_id']; $storageName = (string)$row['storage_name'];
                $path = $storage->path($organisation, $storageName);
                $entry = 'files/' . $organisation . '/' . $storageName;
                $actualHash = hash_file('sha256', $path); $actualSize = filesize($path);
                if (!is_string($actualHash) || !hash_equals((string)$row['sha256'], $actualHash) || $actualSize !== (int)$row['byte_size']) throw new RuntimeException('A private file failed backup integrity validation.');
                $this->addEncryptedFile($zip, $entry, $path);
                $entries[$entry] = ['sha256'=>$actualHash,'bytes'=>$actualSize];
                $files[] = ['entry'=>$entry,'organisationId'=>$organisation,'storageName'=>$storageName,'sha256'=>$actualHash,'bytes'=>$actualSize];
            }
            $migrations = $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
            $this->pdo->commit();
            ksort($entries, SORT_STRING); ksort($tables, SORT_STRING);
            $manifest = ['format'=>'architex-backup-v1','createdAt'=>gmdate('c'),'database'=>['name'=>$database,'migrations'=>$migrations,'tables'=>$tables],'files'=>$files,'entries'=>$entries];
            $canonical = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $manifest['backupId'] = hash('sha256', $canonical);
            $manifest['integrityHmac'] = hash_hmac('sha256', $canonical, $this->hmacKey);
            $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (!$zip->addFromString('manifest.json', $encoded) || !$zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256)) throw new RuntimeException('The backup manifest could not be encrypted.');
            if (!$zip->close() || !chmod($temporaryArchive, 0600)) throw new RuntimeException('The backup archive could not be finalised.');
            if (!rename($temporaryArchive, $output)) throw new RuntimeException('The backup archive could not be published atomically.');
            return new BackupReport($output, $manifest['backupId'], $rowCount, count($files));
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $zip->close();
            if (is_file($temporaryArchive)) @unlink($temporaryArchive);
            throw $exception;
        } finally {
            $this->removeTree($working);
        }
    }

    /** @return list<string> */
    private function tables(): array
    {
        return $this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    }

    private function orderClause(string $table): string
    {
        if (preg_match('/\A[a-z][a-z0-9_]*\z/', $table) !== 1) throw new RuntimeException('The backup table name is invalid.');
        $statement = $this->pdo->prepare("SELECT column_name FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=:table AND constraint_name='PRIMARY' ORDER BY ordinal_position");
        $statement->execute(['table'=>$table]);
        $columns = $statement->fetchAll(PDO::FETCH_COLUMN);
        if ($columns === []) $columns = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='" . $table . "' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
        return $columns === [] ? '' : ' ORDER BY ' . implode(',', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
    }

    /** @param array<string,mixed> $row @return array<string,array{type:string,value?:string|int|float}> */
    private function encodeRow(array $row): array
    {
        $encoded = [];
        foreach ($row as $column => $value) {
            $encoded[$column] = $value === null ? ['type'=>'null'] : (is_int($value) || is_float($value) ? ['type'=>gettype($value),'value'=>$value] : ['type'=>'base64','value'=>base64_encode((string)$value)]);
        }
        return $encoded;
    }

    private function addEncryptedFile(ZipArchive $zip, string $entry, string $path): void
    {
        if (!$zip->addFile($path, $entry) || !$zip->setEncryptionName($entry, ZipArchive::EM_AES_256)) throw new RuntimeException('A backup entry could not be encrypted.');
    }

    /** @param resource $handle */
    private function writeAll($handle, string $data): void
    {
        $offset = 0;
        while ($offset < strlen($data)) {
            $written = fwrite($handle, substr($data, $offset));
            if (!is_int($written) || $written < 1) throw new RuntimeException('A backup table stream could not be written.');
            $offset += $written;
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        @rmdir($path);
    }
}

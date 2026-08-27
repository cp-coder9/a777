<?php

declare(strict_types=1);

namespace Architex\Tests\Release;

use Architex\Http\UploadedFile;
use Architex\Infrastructure\Backup\BackupService;
use Architex\Infrastructure\Backup\RestoreService;
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Platform\Files\FileService;
use Architex\Platform\Files\LocalFileStorage;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class BackupRestoreTest extends MariaDbTestCase
{
    private string $workRoot;
    private string $targetDatabase = 'architex_task10_badc0ffe_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->workRoot = dirname(__DIR__, 2) . '/storage/tmp/backup-test-' . bin2hex(random_bytes(8));
        mkdir($this->workRoot . '/source-files', 0700, true);
    }

    protected function tearDown(): void
    {
        $environment = $this->targetEnvironment();
        try {
            $config = AppConfig::fromEnvironment($environment);
            $admin = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->dbHost, $config->dbPort), $config->dbUser, $config->dbPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $admin->exec('DROP DATABASE IF EXISTS `' . $this->targetDatabase . '`');
        } catch (\Throwable) {}
        if (isset($this->workRoot) && is_dir($this->workRoot)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->workRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($this->workRoot);
        }
        parent::tearDown();
    }

    public function testEncryptedBackupRestoresDatabaseAndPrivateFilesIntoIsolatedTarget(): void
    {
        [$organisation, $user, $project] = $this->seedProject();
        $sourceStorage = new LocalFileStorage($this->workRoot . '/source-files');
        $fixture = $this->workRoot . '/fixture.pdf';
        file_put_contents($fixture, "%PDF-1.4\nbackup-evidence\n%%EOF");
        $stored = (new FileService($this->pdo, $sourceStorage))->upload($organisation, $user, $project, UploadedFile::forTesting('backup.pdf', $fixture));
        $key = base64_encode(str_repeat('b', 32));
        $backup = (new BackupService($this->pdo, $this->workRoot . '/source-files', 'correct horse archive password', $key))->create($this->workRoot . '/rehearsal');

        $report = (new RestoreService(dirname(__DIR__, 2) . '/database/migrations', 'correct horse archive password', $key))->restore(
            $backup->path,
            $this->targetEnvironment(),
            $this->targetDatabase,
            $this->workRoot . '/restored-files',
        );

        self::assertGreaterThan(0, $report->rowCount);
        self::assertSame(1, $report->fileCount);
        $target = ConnectionFactory::create(AppConfig::fromEnvironment($this->targetEnvironment()));
        self::assertSame(1, (int)$target->query('SELECT COUNT(*) FROM stored_files')->fetchColumn());
        $download = (new FileService($target, new LocalFileStorage($this->workRoot . '/restored-files')))->download($organisation, $user, $stored->id);
        self::assertSame("%PDF-1.4\nbackup-evidence\n%%EOF", $download->contents);
    }

    /** @return array<string,string> */
    private function targetEnvironment(): array
    {
        $environment = [];
        foreach (['DB_HOST','DB_PORT','DB_USER','DB_PASSWORD'] as $key) $environment[$key] = (string)getenv($key);
        return $environment + ['ARCHITEX_ALLOW_DISPOSABLE_DB'=>'1','APP_ENV'=>'test','APP_DEBUG'=>'0','DB_NAME'=>$this->targetDatabase];
    }

    /** @return array{string,string,string} */
    private function seedProject(): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'backup@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Backup Practice','backup-practice','active')")->execute(['id'=>$organisation]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisation,'user'=>$user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),'BACKUP-1','Backup Project','Client','City','Erf 1','Office','','architect',UNHEX(:user))")->execute(['id'=>$project,'organisation'=>$organisation,'user'=>$user]);
        return [$organisation,$user,$project];
    }
}

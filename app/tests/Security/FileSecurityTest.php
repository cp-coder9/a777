<?php

declare(strict_types=1);

namespace Architex\Tests\Security;

use Architex\Http\Controller\FileController;
use Architex\Http\Request;
use Architex\Http\UploadedFile;
use Architex\Platform\Files\FileException;
use Architex\Platform\Files\FileService;
use Architex\Platform\Files\LocalFileStorage;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileSecurityTest extends MariaDbTestCase
{
    private string $storageRoot;
    /** @var array{organisation:string,user:string,project:string} */
    private array $tenantA;
    /** @var array{organisation:string,user:string,project:string} */
    private array $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageRoot = dirname(__DIR__, 2) . '/storage/tmp/file-security-' . bin2hex(random_bytes(8));
        mkdir($this->storageRoot, 0700, true);
        $this->tenantA = $this->seedTenant('file-alpha');
        $this->tenantB = $this->seedTenant('file-beta');
    }

    protected function tearDown(): void
    {
        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->storageRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->storageRoot);
        }
        parent::tearDown();
    }

    public function testPhpPayloadIsRejectedEvenWhenNamedAsAnImage(): void
    {
        $temporary = $this->fixture('spoof.jpg', "<?php echo 'owned';");
        $service = $this->service();

        try {
            $service->upload($this->tenantA['organisation'], $this->tenantA['user'], $this->tenantA['project'], UploadedFile::forTesting('evidence.jpg', $temporary));
            self::fail('Executable content must be rejected.');
        } catch (FileException $exception) {
            self::assertSame('unsafe_file', $exception->reason);
        }

        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM stored_files')->fetchColumn());
        self::assertSame(['spoof.jpg'], array_values(array_filter(scandir($this->storageRoot) ?: [], static fn (string $name): bool => !in_array($name, ['.','..'], true))));
    }

    public function testAnotherTenantCannotDownloadByKnowingTheFileId(): void
    {
        $temporary = $this->fixture('evidence.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $stored = $this->service()->upload($this->tenantA['organisation'], $this->tenantA['user'], $this->tenantA['project'], UploadedFile::forTesting('Evidence Plan.pdf', $temporary));
        $controller = new FileController($this->service());
        $request = Request::json('GET', '/api/v1/files/' . $stored->id)
            ->withAttribute('fileId', $stored->id)
            ->withAttribute('organisationId', $this->tenantB['organisation'])
            ->withAttribute('identity', new \Architex\Platform\Identity\AuthenticatedIdentity($this->tenantB['user'], 'b@example.test', [['organisationId'=>$this->tenantB['organisation'],'role'=>'owner']]));

        $response = $controller->download($request);

        self::assertSame(404, $response->status());
        self::assertSame('not_found', $response->json()['error']['code']);
    }

    public function testValidPdfUsesRandomNonPublicPathAndDownloadsWithSafeHeaders(): void
    {
        $contents = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $stored = $this->service()->upload($this->tenantA['organisation'], $this->tenantA['user'], $this->tenantA['project'], UploadedFile::forTesting('../../Evidence Plan.pdf', $this->fixture('valid.pdf', $contents)));
        $download = $this->service()->download($this->tenantA['organisation'], $this->tenantA['user'], $stored->id);

        self::assertSame('Evidence Plan.pdf', $stored->originalName);
        self::assertSame($contents, $download->contents);
        self::assertSame('application/pdf', $download->mimeType);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{48}\z/', $stored->storageName);
        self::assertStringNotContainsString('Evidence Plan', $download->path);
        self::assertStringStartsWith(realpath($this->storageRoot), realpath($download->path));
    }

    private function service(): FileService
    {
        return new FileService($this->pdo, new LocalFileStorage($this->storageRoot));
    }

    private function fixture(string $name, string $contents): string
    {
        $path = $this->storageRoot . '/' . $name;
        file_put_contents($path, $contents);
        return $path;
    }

    /** @return array{organisation:string,user:string,project:string} */
    private function seedTenant(string $slug): array
    {
        $organisation = Identifier::generate();
        $user = Identifier::generate();
        $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$slug,'slug'=>$slug]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisation,'user'=>$user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),:code,:name,'Client','Municipality','Erf 1','Office','','architect',UNHEX(:user))")->execute(['id'=>$project,'organisation'=>$organisation,'code'=>strtoupper(substr(hash('sha256',$slug),0,8)),'name'=>$slug,'user'=>$user]);
        return ['organisation'=>$organisation,'user'=>$user,'project'=>$project];
    }
}

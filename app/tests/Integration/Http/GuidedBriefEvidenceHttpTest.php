<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Http\UploadedFile;
use Architex\Platform\Brief\GuidedBriefService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class GuidedBriefEvidenceHttpTest extends MariaDbTestCase
{
    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageRoot = dirname(__DIR__, 3) . '/storage/tmp/brief-http-' . bin2hex(random_bytes(8));
        mkdir($this->storageRoot, 0700, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->storageRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($this->storageRoot);
        }
        parent::tearDown();
    }

    public function testAuthenticatedMultipartUploadListAndPrivateDownload(): void
    {
        [$organisationId, $userId] = $this->seedTenant();
        $brief = (new GuidedBriefService($this->pdo))->create($organisationId, $userId, 'HTTP evidence');
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier { public function isAvailable(): bool { return false; } public function send(string $email, string $token, string $expiresAt): void {} };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier, fileStorageRoot: $this->storageRoot))->create();
        $session = (new SessionService($this->pdo))->rotate($userId);
        $contents = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $path = $this->storageRoot . '/source.pdf';
        file_put_contents($path, $contents);
        $headers = ['Cookie'=>'architex_session=' . $session->token, 'X-Organisation-Id'=>$organisationId, 'X-CSRF-Token'=>$session->csrfToken, 'Content-Type'=>'multipart/form-data; boundary=test'];
        $request = Request::json('POST', '/api/v1/guided-briefs/' . $brief['id'] . '/files', $headers)
            ->withUploadedFile('file', UploadedFile::forTesting('Existing plan.pdf', $path))
            ->withFormField('category', 'existing_plan')
            ->withFormField('processingPurpose', 'Assess guided brief evidence')
            ->withFormField('retentionUntil', '2031-08-28')
            ->withFormField('consentGiven', 'true');
        $uploaded = $kernel->handle($request);
        self::assertSame(201, $uploaded->status(), json_encode($uploaded->json(), JSON_THROW_ON_ERROR));
        $fileId = $uploaded->json()['data']['id'];

        $listed = $kernel->handle(Request::json('GET', '/api/v1/guided-briefs/' . $brief['id'] . '/files', $headers));
        self::assertSame(200, $listed->status());
        self::assertSame('Existing plan.pdf', $listed->json()['data'][0]['originalName']);
        $download = $kernel->handle(Request::json('GET', '/api/v1/guided-brief-files/' . $fileId, $headers));
        self::assertSame(200, $download->status());
        self::assertSame('no-store', $download->header('Cache-Control'));
        self::assertSame($contents, $download->body());
    }

    /** @return array{string,string} */
    private function seedTenant(): array
    {
        $organisationId = Identifier::generate(); $userId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'brief-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Brief HTTP','brief-http','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);
        return [$organisationId, $userId];
    }
}

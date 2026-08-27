<?php

declare(strict_types=1);

namespace Architex\Platform\Files;

use Architex\Http\UploadedFile;
use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use finfo;
use PDO;
use Throwable;

final readonly class FileService
{
    /** @var array<string,list<string>> */
    private const ALLOWED_TYPES = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg','jpeg'],
        'image/png' => ['png'],
        'application/zip' => ['zip','docx','xlsx','pptx'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
    ];

    public function __construct(private PDO $pdo, private LocalFileStorage $storage, private int $maximumBytes = 25_000_000) {}

    public function upload(string $organisationId, string $userId, string $projectId, UploadedFile $upload): StoredFile
    {
        Identifier::binary($organisationId);
        Identifier::binary($userId);
        Identifier::binary($projectId);
        if ($upload->error !== UPLOAD_ERR_OK || $upload->size < 1 || !is_file($upload->temporaryPath)) {
            throw new FileException('invalid_upload', 'The uploaded file is invalid.');
        }
        if ($upload->size > $this->maximumBytes) {
            throw new FileException('file_too_large', 'The uploaded file is too large.');
        }
        $authorised = $this->pdo->prepare("SELECT 1 FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=UNHEX(:user) AND m.status='active' WHERE p.id=UNHEX(:project) AND p.organisation_id=UNHEX(:organisation)");
        $authorised->execute(['user'=>$userId,'project'=>$projectId,'organisation'=>$organisationId]);
        if ((int)$authorised->fetchColumn() !== 1) {
            throw new FileException('not_found', 'Project not found.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload->temporaryPath);
        $originalName = $this->safeOriginalName($upload->clientName);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!is_string($mime) || !isset(self::ALLOWED_TYPES[$mime]) || !in_array($extension, self::ALLOWED_TYPES[$mime], true)) {
            throw new FileException('unsafe_file', 'The file type is not allowed.');
        }
        $actualSize = filesize($upload->temporaryPath);
        $sha256 = hash_file('sha256', $upload->temporaryPath);
        if (!is_int($actualSize) || $actualSize !== $upload->size || !is_string($sha256)) {
            throw new FileException('invalid_upload', 'The uploaded file changed during validation.');
        }
        $id = Identifier::generate();
        $storageName = bin2hex(random_bytes(24));
        $storedPath = $this->storage->put($organisationId, $storageName, $upload->temporaryPath, $upload->httpUpload);
        $storedSize = filesize($storedPath);
        $storedHash = hash_file('sha256', $storedPath);
        if (!is_int($storedSize) || $storedSize !== $actualSize || !is_string($storedHash) || !hash_equals($sha256, $storedHash)) {
            $this->storage->delete($organisationId, $storageName);
            throw new FileException('storage_unavailable', 'The stored file failed its integrity check.');
        }
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare('INSERT INTO stored_files(id,organisation_id,project_id,uploaded_by,original_name,storage_name,mime_type,byte_size,sha256) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),UNHEX(:user),:original_name,:storage_name,:mime_type,:byte_size,:sha256)');
            $insert->execute(['id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'user'=>$userId,'original_name'=>$originalName,'storage_name'=>$storageName,'mime_type'=>$mime,'byte_size'=>$actualSize,'sha256'=>$sha256]);
            (new AuditService($this->pdo))->append($organisationId, $userId, 'file.uploaded', 'stored_file', $id, ['projectId'=>$projectId,'mimeType'=>$mime,'byteSize'=>$actualSize,'sha256'=>$sha256]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->storage->delete($organisationId, $storageName);
            throw $exception;
        }
        return new StoredFile($id, $projectId, $originalName, $storageName, $mime, $actualSize, $sha256);
    }

    public function download(string $organisationId, string $userId, string $fileId): FileDownload
    {
        Identifier::binary($organisationId);
        Identifier::binary($userId);
        Identifier::binary($fileId);
        $statement = $this->pdo->prepare("SELECT f.original_name,f.storage_name,f.mime_type,f.byte_size,f.sha256 FROM stored_files f JOIN organisation_memberships m ON m.organisation_id=f.organisation_id AND m.user_id=UNHEX(:user) AND m.status='active' WHERE f.id=UNHEX(:file) AND f.organisation_id=UNHEX(:organisation) AND f.status='active'");
        $statement->execute(['user'=>$userId,'file'=>$fileId,'organisation'=>$organisationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new FileException('not_found', 'File not found.');
        }
        $path = $this->storage->path($organisationId, (string)$row['storage_name']);
        $contents = file_get_contents($path);
        if (!is_string($contents) || strlen($contents) !== (int)$row['byte_size'] || !hash_equals((string)$row['sha256'], hash('sha256', $contents))) {
            throw new FileException('storage_unavailable', 'The stored file failed its integrity check.');
        }
        return new FileDownload((string)$row['original_name'], (string)$row['mime_type'], (int)$row['byte_size'], (string)$row['sha256'], $path, $contents);
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        if ($name === '' || $name === '.' || $name === '..' || strlen($name) > 255) {
            throw new FileException('invalid_upload', 'The uploaded filename is invalid.');
        }
        return $name;
    }
}

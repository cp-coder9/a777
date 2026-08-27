<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Files\FileException;
use Architex\Platform\Files\FileService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use InvalidArgumentException;

final readonly class FileController
{
    public function __construct(private FileService $files) {}

    public function upload(Request $request): Response
    {
        try {
            [$organisationId, $userId] = $this->scope($request);
            $projectId = $request->attribute('projectId');
            $upload = $request->uploadedFile('file');
            if (!is_string($projectId) || $upload === null) {
                return Response::error(422, 'invalid_upload', 'A project file is required.');
            }
            $stored = $this->files->upload($organisationId, $userId, $projectId, $upload);
            return Response::success([
                'id'=>$stored->id,
                'projectId'=>$stored->projectId,
                'originalName'=>$stored->originalName,
                'mimeType'=>$stored->mimeType,
                'byteSize'=>$stored->byteSize,
                'sha256'=>$stored->sha256,
            ], status: 201);
        } catch (FileException $exception) {
            return $this->fileError($exception);
        } catch (InvalidArgumentException) {
            return Response::error(404, 'not_found', 'Project not found.');
        }
    }

    public function download(Request $request): Response
    {
        try {
            [$organisationId, $userId] = $this->scope($request);
            $fileId = $request->attribute('fileId');
            if (!is_string($fileId)) {
                return Response::error(404, 'not_found', 'File not found.');
            }
            $file = $this->files->download($organisationId, $userId, $fileId);
            $ascii = preg_replace('/[^A-Za-z0-9._ -]/', '_', $file->originalName) ?: 'download';
            return Response::binary($file->contents, $file->mimeType, [
                'Content-Length'=>(string)$file->byteSize,
                'Content-Disposition'=>'attachment; filename="' . addcslashes($ascii, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($file->originalName),
                'X-Content-Sha256'=>$file->sha256,
                'Cache-Control'=>'private, no-store',
            ]);
        } catch (FileException $exception) {
            return $this->fileError($exception);
        } catch (InvalidArgumentException) {
            return Response::error(404, 'not_found', 'File not found.');
        }
    }

    /** @return array{string,string} */
    private function scope(Request $request): array
    {
        $organisationId = $request->attribute('organisationId');
        $identity = $request->attribute('identity');
        if (!is_string($organisationId) || !$identity instanceof AuthenticatedIdentity) {
            throw new FileException('not_found', 'File not found.');
        }
        return [$organisationId, $identity->userId];
    }

    private function fileError(FileException $exception): Response
    {
        $status = match ($exception->reason) {
            'unsafe_file','invalid_upload' => 422,
            'file_too_large' => 413,
            'storage_unavailable' => 503,
            default => 404,
        };
        $message = $status === 404 ? 'File not found.' : $exception->getMessage();
        return Response::error($status, $exception->reason, $message);
    }
}

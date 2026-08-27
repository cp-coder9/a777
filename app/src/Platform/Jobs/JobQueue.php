<?php

declare(strict_types=1);

namespace Architex\Platform\Jobs;

use Architex\Platform\Identity\Identifier;
use PDO;
use PDOException;
use RuntimeException;

final readonly class JobQueue
{
    public function __construct(private PDO $pdo, private PayloadCipher $cipher) {}

    /** @param array<string,mixed> $payload */
    public function enqueue(string $jobType, array $payload, ?string $deduplicationKey = null, int $maxAttempts = 5): string
    {
        if (preg_match('/\A[a-z][a-z0-9_]{2,99}\z/', $jobType) !== 1 || $maxAttempts < 1 || $maxAttempts > 20) {
            throw new RuntimeException('The scheduled job definition is invalid.');
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        $id = Identifier::generate();
        try {
            $statement = $this->pdo->prepare("INSERT INTO scheduled_jobs (id,job_type,payload,status,attempts,max_attempts,available_at) VALUES (UNHEX(:id),:job_type,:payload,'pending',0,:max_attempts,UTC_TIMESTAMP(6))");
            $statement->execute([
                'id' => $id,
                'job_type' => $jobType,
                'payload' => json_encode(['encrypted' => $this->cipher->encrypt($payload)], JSON_THROW_ON_ERROR),
                'max_attempts' => $maxAttempts,
            ]);
            if ($deduplicationKey !== null) {
                $dedupe = $this->pdo->prepare('INSERT INTO scheduled_job_deduplication_keys (deduplication_key,job_id) VALUES (:dedupe,UNHEX(:id))');
                try {
                    $dedupe->execute(['dedupe' => hash('sha256', $deduplicationKey), 'id' => $id]);
                } catch (PDOException $exception) {
                    if ((string)$exception->getCode() !== '23000') {
                        throw $exception;
                    }
                    $this->pdo->prepare('DELETE FROM scheduled_jobs WHERE id=UNHEX(:id)')->execute(['id' => $id]);
                    $existing = $this->pdo->prepare('SELECT LOWER(HEX(job_id)) FROM scheduled_job_deduplication_keys WHERE deduplication_key=:dedupe');
                    $existing->execute(['dedupe' => hash('sha256', $deduplicationKey)]);
                    $existingId = $existing->fetchColumn();
                    if (!is_string($existingId)) {
                        throw new RuntimeException('The scheduled job could not be deduplicated.');
                    }
                    $id = $existingId;
                }
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $id;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}

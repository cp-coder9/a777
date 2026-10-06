<?php

declare(strict_types=1);

namespace Architex\Platform\Jobs;

use Architex\Infrastructure\Mail\Mailer;
use Architex\Infrastructure\Mail\Message;
use Architex\Infrastructure\Mail\DeliveryReceipt;
use Architex\Infrastructure\Mail\DeliveryOutcomeUnknown;
use Architex\Platform\Inbox\InboxMailQueue;
use PDO;
use RuntimeException;
use Throwable;

final readonly class JobDispatcher
{
    private string $workerId;

    public function __construct(
        private PDO $pdo,
        private PayloadCipher $cipher,
        private Mailer $mailer,
        private string $appUrl,
    ) {
        $this->workerId = gethostname() . '-' . getmypid() . '-' . bin2hex(random_bytes(4));
    }

    public function run(int $limit = 25): DispatchReport
    {
        $limit = max(1, min(100, $limit));
        (new InboxMailQueue($this->pdo, new JobQueue($this->pdo, $this->cipher)))->enqueue($limit);
        $claimed = $succeeded = $retried = $dead = 0;
        for ($i = 0; $i < $limit; $i++) {
            $job = $this->claim();
            if ($job === null) {
                break;
            }
            $claimed++;
            $receipt = null;
            try {
                $receipt = $this->dispatch($job);
                try {
                    $this->complete((string)$job['id'], $receipt);
                } catch (Throwable) {
                    // Transport accepted the message. A DB failure must not schedule another send.
                    $this->fail((string)$job['id'], true, (int)$job['attempts'], 'delivery_receipt_persistence_failed');
                    $dead++;
                    continue;
                }
                $succeeded++;
            } catch (DeliveryOutcomeUnknown) {
                $this->fail((string)$job['id'], true, (int)$job['attempts'], 'delivery_outcome_unknown');
                $dead++;
            } catch (PermanentJobFailure $failure) {
                $this->fail((string)$job['id'], true, (int)$job['attempts'], $failure->errorCode);
                $dead++;
            } catch (Throwable) {
                if ($receipt !== null) {
                    // If even recording the failure fails, leave the running job for review.
                    $this->fail((string)$job['id'], true, (int)$job['attempts'], 'delivery_receipt_persistence_failed');
                    $dead++;
                    continue;
                }
                if ((int)$job['attempts'] >= (int)$job['max_attempts']) {
                    $this->fail((string)$job['id'], true);
                    $dead++;
                } else {
                    $this->fail((string)$job['id'], false, (int)$job['attempts']);
                    $retried++;
                }
            }
        }
        return new DispatchReport($claimed, $succeeded, $retried, $dead);
    }

    /** @return array<string,mixed>|null */
    private function claim(): ?array
    {
        $this->pdo->beginTransaction();
        try {
            // A crashed worker may already have handed mail to SMTP. Do not replay it blindly.
            $this->pdo->exec("UPDATE scheduled_jobs SET status='dead',failed_at=UTC_TIMESTAMP(6),last_error_code='delivery_outcome_unknown',locked_by=NULL,locked_at=NULL WHERE status='running' AND locked_at<UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE");
            $select = $this->pdo->query("SELECT LOWER(HEX(id)) id,job_type,payload,attempts,max_attempts FROM scheduled_jobs WHERE status IN ('pending','failed') AND available_at<=UTC_TIMESTAMP(6) ORDER BY available_at,id LIMIT 1 FOR UPDATE SKIP LOCKED");
            $job = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($job)) {
                $this->pdo->commit();
                return null;
            }
            $update = $this->pdo->prepare("UPDATE scheduled_jobs SET status='running',attempts=attempts+1,locked_by=:worker,locked_at=UTC_TIMESTAMP(6),last_error_code=NULL WHERE id=UNHEX(:id)");
            $update->execute(['worker' => $this->workerId, 'id' => $job['id']]);
            $job['attempts'] = (int)$job['attempts'] + 1;
            $this->pdo->commit();
            return $job;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $job */
    private function dispatch(array $job): DeliveryReceipt
    {
        if (!$this->mailer->isConfigured()) {
            throw new RuntimeException('Mail delivery is unavailable.');
        }
        if (!in_array($job['job_type'], ['send_verification_email', 'send_password_reset_email', 'send_inbox_email'], true)) {
            throw new PermanentJobFailure('job_type_unsupported');
        }
        try {
            $stored = json_decode((string)$job['payload'], true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($stored) || !isset($stored['encrypted']) || !is_string($stored['encrypted'])) {
                throw new RuntimeException('invalid');
            }
            $payload = $this->cipher->decrypt($stored['encrypted']);
        } catch (Throwable) {
            throw new PermanentJobFailure('payload_invalid');
        }
        if ($job['job_type'] === 'send_inbox_email') {
            foreach (['inboxId', 'userId'] as $key) {
                if (!is_string($payload[$key] ?? null) || preg_match('/\A[a-f0-9]{32}\z/', $payload[$key]) !== 1) {
                    throw new PermanentJobFailure('payload_invalid');
                }
            }
            $to = (new InboxMailQueue($this->pdo, new JobQueue($this->pdo, $this->cipher)))->recipient($payload['inboxId'], $payload['userId']);
            if ($to === null) throw new PermanentJobFailure('notification_recipient_ineligible');
            // Keep confidential workflow details behind authenticated inbox access.
            return $this->mailer->send(new Message($to, 'Architex project notification', "A project notification requires your attention. Sign in to your Architex inbox:\n\n" . rtrim($this->appUrl, '/') . '/app'));
        }
        $to = $payload['to'] ?? null;
        $token = $payload['token'] ?? null;
        $expiresAt = $payload['expiresAt'] ?? null;
        if (!is_string($to) || !is_string($token) || !is_string($expiresAt)) {
            throw new PermanentJobFailure('payload_invalid');
        }
        $base = rtrim($this->appUrl, '/');
        if ($job['job_type'] === 'send_verification_email') {
            $url = $base . '/app?verify=' . rawurlencode($token);
            $message = new Message($to, 'Verify your Architex account', "Verify your Architex account before {$expiresAt}:\n\n{$url}");
        } elseif ($job['job_type'] === 'send_password_reset_email') {
            $url = $base . '/app?reset=' . rawurlencode($token);
            $message = new Message($to, 'Reset your Architex password', "Reset your Architex password before {$expiresAt}:\n\n{$url}");
        }
        return $this->mailer->send($message);
    }

    private function complete(string $id, DeliveryReceipt $receipt): void
    {
        if ($receipt->messageId === '' || $receipt->acceptedAt === '') throw new RuntimeException('Mail receipt is invalid.');
        $statement = $this->pdo->prepare("UPDATE scheduled_jobs SET status='completed',completed_at=UTC_TIMESTAMP(6),payload=JSON_SET(payload,'$.deliveryReceipt',JSON_OBJECT('messageId',:message_id,'acceptedAt',:accepted_at)),locked_by=NULL,locked_at=NULL,last_error_code=NULL WHERE id=UNHEX(:id) AND locked_by=:worker AND status='running'");
        $statement->execute(['id' => $id, 'worker' => $this->workerId, 'message_id'=>$receipt->messageId, 'accepted_at'=>$receipt->acceptedAt]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Mail job ownership was lost.');
    }

    private function fail(string $id, bool $dead, int $attempt = 1, string $errorCode = 'mail_delivery_failed'): void
    {
        $delay = min(3600, 30 * (2 ** max(0, min(6, $attempt - 1))));
        $sql = $dead
            ? "UPDATE scheduled_jobs SET status='dead',failed_at=UTC_TIMESTAMP(6),locked_by=NULL,locked_at=NULL,last_error_code=:error_code WHERE id=UNHEX(:id) AND locked_by=:worker"
            : "UPDATE scheduled_jobs SET status='failed',available_at=UTC_TIMESTAMP(6)+INTERVAL {$delay} SECOND,failed_at=UTC_TIMESTAMP(6),locked_by=NULL,locked_at=NULL,last_error_code=:error_code WHERE id=UNHEX(:id) AND locked_by=:worker";
        $this->pdo->prepare($sql)->execute(['id' => $id, 'worker' => $this->workerId, 'error_code' => $errorCode]);
    }
}

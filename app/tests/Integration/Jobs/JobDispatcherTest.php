<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Jobs;

use Architex\Infrastructure\Mail\CapturedMailer;
use Architex\Infrastructure\Mail\DeliveryReceipt;
use Architex\Infrastructure\Mail\Mailer;
use Architex\Infrastructure\Mail\Message;
use Architex\Platform\Jobs\JobDispatcher;
use Architex\Platform\Jobs\JobQueue;
use Architex\Platform\Jobs\PayloadCipher;
use Architex\Tests\Support\MariaDbTestCase;
use RuntimeException;

final class JobDispatcherTest extends MariaDbTestCase
{
    private PayloadCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cipher = PayloadCipher::fromBase64Key(base64_encode(str_repeat('k', 32)));
    }

    public function testClaimsDecryptsAndDeliversQueuedMailExactlyOnce(): void
    {
        $queue = new JobQueue($this->pdo, $this->cipher);
        $queue->enqueue('send_verification_email', ['to'=>'person@example.test','token'=>'secret-token','expiresAt'=>'2099-01-01T00:00:00Z'], 'verify:user-1');
        self::assertSame(0, (int)$this->pdo->query("SELECT COUNT(*) FROM scheduled_jobs WHERE payload LIKE '%secret-token%'")->fetchColumn());
        $mailer = new CapturedMailer();

        $report = (new JobDispatcher($this->pdo, $this->cipher, $mailer, 'https://example.test'))->run(10);

        self::assertSame(1, $report->claimed);
        self::assertSame(1, $report->succeeded);
        self::assertSame(0, $report->retried);
        self::assertCount(1, $mailer->messages());
        self::assertSame('completed', $this->pdo->query('SELECT status FROM scheduled_jobs')->fetchColumn());
        self::assertSame(0, (new JobDispatcher($this->pdo, $this->cipher, $mailer, 'https://example.test'))->run(10)->claimed);
    }

    public function testMailFailureBecomesRetryableWithoutLeakingTheFailureMessage(): void
    {
        (new JobQueue($this->pdo, $this->cipher))->enqueue('send_password_reset_email', ['to'=>'person@example.test','token'=>'reset-secret','expiresAt'=>'2099-01-01T00:00:00Z']);
        $mailer = new class implements Mailer {
            public function isConfigured(): bool { return true; }
            public function send(Message $message): DeliveryReceipt { throw new RuntimeException('smtp password top-secret'); }
        };

        $report = (new JobDispatcher($this->pdo, $this->cipher, $mailer, 'https://example.test'))->run(10);

        self::assertSame(1, $report->retried);
        $row = $this->pdo->query('SELECT status,attempts,last_error_code,payload FROM scheduled_jobs')->fetch();
        self::assertSame('failed', $row['status']);
        self::assertSame(1, (int)$row['attempts']);
        self::assertSame('mail_delivery_failed', $row['last_error_code']);
        self::assertStringNotContainsString('top-secret', (string)$row['payload']);
    }

    public function testDoesNotClaimAnActivelyLockedJob(): void
    {
        (new JobQueue($this->pdo, $this->cipher))->enqueue('send_verification_email', ['to'=>'person@example.test','token'=>'secret','expiresAt'=>'2099-01-01T00:00:00Z']);
        $this->pdo->exec("UPDATE scheduled_jobs SET status='running',locked_by='other-worker',locked_at=UTC_TIMESTAMP(6)");
        self::assertSame(0, (new JobDispatcher($this->pdo, $this->cipher, new CapturedMailer(), 'https://example.test'))->run(10)->claimed);
    }

    public function testDeduplicationKeyReturnsExistingJobWithoutAddingAnother(): void
    {
        $queue = new JobQueue($this->pdo, $this->cipher);
        $first = $queue->enqueue('send_verification_email', ['to'=>'person@example.test','token'=>'one','expiresAt'=>'2099-01-01T00:00:00Z'], 'same-operation');
        $second = $queue->enqueue('send_verification_email', ['to'=>'person@example.test','token'=>'two','expiresAt'=>'2099-01-01T00:00:00Z'], 'same-operation');

        self::assertSame($first, $second);
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM scheduled_jobs')->fetchColumn());
    }

    public function testUnsupportedJobDiesWithSafeErrorCode(): void
    {
        (new JobQueue($this->pdo, $this->cipher))->enqueue('unsupported_job', ['secret'=>'must-not-leak']);

        $report = (new JobDispatcher($this->pdo, $this->cipher, new CapturedMailer(), 'https://example.test'))->run(10);

        self::assertSame(1, $report->dead);
        $row = $this->pdo->query('SELECT status,last_error_code,payload FROM scheduled_jobs')->fetch();
        self::assertSame('dead', $row['status']);
        self::assertSame('job_type_unsupported', $row['last_error_code']);
        self::assertStringNotContainsString('must-not-leak', (string)$row['payload']);
    }
}

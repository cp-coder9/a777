<?php
declare(strict_types=1);
namespace Architex\Tests\Unit\Inbox;
use Architex\Infrastructure\Mail\SmtpMailer;
use Architex\Infrastructure\Mail\SmtpConfig;
use Architex\Infrastructure\Mail\Message;
use PHPUnit\Framework\TestCase;

final class SmtpAcceptanceTest extends TestCase
{
    public function testDisconnectAfterAcceptanceDoesNotCauseRetry(): void
    {
        $this->peer('accepted', function (SmtpMailer $mailer): void {
            self::assertNotSame('', $mailer->send(new Message('recipient@example.test','Test','Body'))->messageId);
        });
    }
    public function testDisconnectBeforeAcceptanceIsExplicitlyUncertain(): void
    {
        $this->expectException(\Architex\Infrastructure\Mail\DeliveryOutcomeUnknown::class);
        $this->peer('uncertain', static fn(SmtpMailer $mailer)=>$mailer->send(new Message('recipient@example.test','Test','Body')));
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('inconclusiveAcknowledgements')]
    public function testInconclusiveFinalAcknowledgementIsUnknown(string $mode): void
    {
        $this->expectException(\Architex\Infrastructure\Mail\DeliveryOutcomeUnknown::class);
        $this->peer($mode, static fn(SmtpMailer $mailer) => $mailer->send(new Message('recipient@example.test', 'Test', 'Body')));
    }
    public static function inconclusiveAcknowledgements(): array
    {
        return array_map(static fn(string $mode): array => [$mode], [
            'malformed', 'truncated-250', 'truncated-2', 'partial-multiline',
            'inconsistent-multiline', 'unexpected-positive', 'bare-lf', 'invalid-code', 'invalid-separator',
            'partial-negative', 'truncated-negative', 'unexpected-intermediate',
        ]);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('negativeAcknowledgements')]
    public function testCompleteNegativeAcknowledgementIsKnownRejection(string $mode): void
    {
        $this->peer($mode, static function (SmtpMailer $mailer): void {
            try {
                $mailer->send(new Message('recipient@example.test', 'Test', 'Body'));
                self::fail('Expected a conclusive SMTP rejection.');
            } catch (\RuntimeException $error) {
                self::assertNotInstanceOf(\Architex\Infrastructure\Mail\DeliveryOutcomeUnknown::class, $error);
                self::assertSame('SMTP server rejected the operation.', $error->getMessage());
            }
        });
    }
    public static function negativeAcknowledgements(): array
    {
        return [['negative-450'], ['negative-550']];
    }
    public function testCompleteConsistentMultilineAcceptanceProvidesReceipt(): void
    {
        $this->peer('multiline-accepted', static function (SmtpMailer $mailer): void {
            self::assertNotSame('', $mailer->send(new Message('recipient@example.test', 'Test', 'Body'))->messageId);
        });
    }
    private function peer(string $mode, callable $test): void
    {
        $process = proc_open([PHP_BINARY,dirname(__DIR__,2).'/Support/notification-smtp-peer.php',$mode], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        self::assertIsResource($process);
        try {
            $address = trim((string)fgets($pipes[1]));
            self::assertMatchesRegularExpression('/^127\.0\.0\.1:[0-9]+$/',$address);
            $test(new SmtpMailer(SmtpConfig::fromEnvironment(['SMTP_HOST'=>'127.0.0.1','SMTP_PORT'=>substr($address,strrpos($address,':')+1),'SMTP_FROM_EMAIL'=>'sender@example.test','SMTP_ENCRYPTION'=>'none']),2));
        } finally {
            foreach ($pipes as $pipe) fclose($pipe);
            proc_terminate($process);
            proc_close($process);
        }
    }
}

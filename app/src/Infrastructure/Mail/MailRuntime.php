<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Jobs\JobQueue;
use Architex\Platform\Jobs\PayloadCipher;
use PDO;
use Throwable;

final readonly class MailRuntime
{
    private function __construct(
        public Mailer $mailer,
        public RegistrationNotifier $registrationNotifier,
        public PasswordResetNotifier $passwordResetNotifier,
        public ?PayloadCipher $cipher,
        public string $appUrl,
        private bool $ready,
    ) {}

    /** @param array<string,mixed> $environment */
    public static function fromEnvironment(PDO $pdo, array $environment): self
    {
        $unavailable = new UnavailableIdentityNotifier();
        try {
            $smtp = SmtpConfig::fromEnvironment($environment);
        } catch (Throwable) {
            $smtp = SmtpConfig::fromEnvironment([]);
        }
        $mailer = new SmtpMailer($smtp);
        $appUrl = is_string($environment['APP_URL'] ?? null) ? rtrim(trim($environment['APP_URL']), '/') : '';
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        $appUrlValid = filter_var($appUrl, FILTER_VALIDATE_URL) !== false && is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
        if (!$appUrlValid) {
            $appUrl = '';
        }
        $cipher = null;
        try {
            $key = $environment['APP_KEY'] ?? '';
            if (is_string($key) && $key !== '') {
                $cipher = PayloadCipher::fromBase64Key($key);
            }
        } catch (Throwable) {
            $cipher = null;
        }
        if (!$mailer->isConfigured() || !$appUrlValid || $cipher === null) {
            return new self($mailer, $unavailable, $unavailable, $cipher, $appUrl, false);
        }
        $queue = new JobQueue($pdo, $cipher);
        return new self(
            $mailer,
            new QueuedIdentityNotifier($queue, 'send_verification_email'),
            new QueuedIdentityNotifier($queue, 'send_password_reset_email'),
            $cipher,
            $appUrl,
            true,
        );
    }

    public function isReady(): bool
    {
        return $this->ready;
    }
}

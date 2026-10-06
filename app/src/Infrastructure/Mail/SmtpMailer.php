<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

use RuntimeException;

final readonly class SmtpMailer implements Mailer
{
    public function __construct(private SmtpConfig $config, private int $timeoutSeconds = 10) {}

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function send(Message $message): DeliveryReceipt
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('SMTP delivery is not configured.');
        }
        $transport = $this->config->encryption === 'tls' ? 'tls' : 'tcp';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $socket = @stream_socket_client(
            sprintf('%s://%s:%d', $transport, $this->config->host, $this->config->port),
            $errorNumber,
            $errorMessage,
            $this->timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed.');
        }
        stream_set_timeout($socket, $this->timeoutSeconds);
        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO ' . $this->hostname(), [250]);
            if ($this->config->encryption === 'starttls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new RuntimeException('SMTP TLS negotiation failed.');
                }
                $this->command($socket, 'EHLO ' . $this->hostname(), [250]);
            }
            if ($this->config->username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($this->config->username), [334]);
                $this->command($socket, base64_encode($this->config->password), [235]);
            }
            $this->command($socket, 'MAIL FROM:<' . $this->config->fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $message->to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            $messageId = bin2hex(random_bytes(16)) . '@' . $this->hostname();
            $content = $this->format($message, $messageId);
            try {
                $this->write($socket, $content . "\r\n.");
                $this->expect($socket, [250]);
            } catch (RuntimeException $error) {
                // Explicit SMTP rejection is conclusive; connection loss is not.
                if ($error->getMessage() === 'SMTP server rejected the operation.') throw $error;
                throw new DeliveryOutcomeUnknown('SMTP acceptance could not be confirmed.');
            }
            // Acceptance of DATA is the receipt; a failed QUIT must not resend it.
            try { $this->command($socket, 'QUIT', [221]); } catch (RuntimeException) {}
            return new DeliveryReceipt($messageId, gmdate('c'));
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket @param list<int> $codes */
    private function command($socket, string $command, array $codes): void
    {
        $this->write($socket, $command);
        $this->expect($socket, $codes);
    }

    /** @param resource $socket */
    private function write($socket, string $line): void
    {
        $data = $line . "\r\n";
        $offset = 0;
        while ($offset < strlen($data)) {
            $bytes = @fwrite($socket, substr($data, $offset));
            if (!is_int($bytes) || $bytes < 1) {
                throw new RuntimeException('SMTP write failed.');
            }
            $offset += $bytes;
        }
    }

    /** @param resource $socket @param list<int> $codes */
    private function expect($socket, array $codes): void
    {
        $code = null;
        $lines = 0;
        do {
            if (++$lines > 100) {
                throw new RuntimeException('SMTP response failed.');
            }
            $line = @fgets($socket, 2048);
            // A partial read/EOF is not an acknowledgement. Every continuation
            // must carry the same valid reply code and end in CRLF.
            if (!is_string($line) || preg_match('/\A([2-5][0-5][0-9])([ -])[^\r\n]*\r\n\z/', $line, $parts) !== 1) {
                throw new RuntimeException('SMTP response failed.');
            }
            $lineCode = (int)$parts[1];
            if ($code !== null && $lineCode !== $code) {
                throw new RuntimeException('SMTP response failed.');
            }
            $code = $lineCode;
        } while ($parts[2] === '-');
        if (!in_array($code, $codes, true)) {
            if ($code >= 400) {
                throw new RuntimeException('SMTP server rejected the operation.');
            }
            throw new RuntimeException('SMTP response failed.');
        }
    }

    private function format(Message $message, string $messageId): string
    {
        $fromName = addcslashes($this->config->fromName, '"\\');
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . $messageId . '>',
            'From: "' . $fromName . '" <' . $this->config->fromEmail . '>',
            'To: <' . $message->to . '>',
            'Subject: ' . $message->subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
        ];
        $normalisedBody = str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $message->textBody));
        $body = quoted_printable_encode($normalisedBody);
        $body = preg_replace('/(?m)^\./', '..', $body) ?? $body;
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function hostname(): string
    {
        $host = gethostname();
        return is_string($host) && preg_match('/\A[A-Za-z0-9.-]+\z/', $host) === 1 ? $host : 'localhost';
    }
}

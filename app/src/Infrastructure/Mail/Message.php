<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Mail;

use InvalidArgumentException;

final readonly class Message
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $textBody,
        public ?string $htmlBody = null,
    ) {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The recipient email address is invalid.');
        }
        if ($subject === '' || strlen($subject) > 200 || preg_match('/[\r\n]/', $subject) === 1) {
            throw new InvalidArgumentException('The message subject is invalid.');
        }
        if ($textBody === '' || strlen($textBody) > 1_000_000) {
            throw new InvalidArgumentException('The message body is required.');
        }
    }
}

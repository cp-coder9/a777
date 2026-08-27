<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

final readonly class PendingRegistration
{
    public function __construct(
        public string $userId,
        public string $organisationId,
        public string $verificationToken,
        public string $status = 'pending_email_verification',
    ) {}
}

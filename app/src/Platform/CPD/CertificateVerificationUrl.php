<?php

declare(strict_types=1);

namespace Architex\Platform\CPD;

final class CertificateVerificationUrl
{
    public static function build(string $baseUrl, string $verificationCode): string
    {
        return rtrim($baseUrl, '/') . '/api/v1/cpd/certificates/verify/' . rawurlencode($verificationCode);
    }
}

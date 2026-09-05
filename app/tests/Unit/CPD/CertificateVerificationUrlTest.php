<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\CPD;

use Architex\Platform\CPD\CertificateVerificationUrl;
use PHPUnit\Framework\TestCase;

final class CertificateVerificationUrlTest extends TestCase
{
    public function testTargetsPublicRouteWithConfiguredInstallationPath(): void
    {
        self::assertTrue(class_exists(CertificateVerificationUrl::class), 'A shared public-route URL builder is required.');
        $code=str_repeat('A',32);
        self::assertSame('https://example.test/platform/api/v1/cpd/certificates/verify/'.$code,
            CertificateVerificationUrl::build('https://example.test/platform/', $code));
    }

    public function testMissingOriginReturnsUsableSameOriginRoute(): void
    {
        self::assertTrue(class_exists(CertificateVerificationUrl::class), 'A shared public-route URL builder is required.');
        $code=str_repeat('B',32);
        self::assertSame('/api/v1/cpd/certificates/verify/'.$code,
            CertificateVerificationUrl::build('', $code));
    }
}

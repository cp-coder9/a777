<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class ApiClientTest extends TestCase
{
    public function testMalformedSuccessfulResponseFailsWithAControlledApiError(): void
    {
        $client = file_get_contents(dirname(__DIR__, 2).'/public/assets/js/api-client.js');
        self::assertIsString($client);
        self::assertStringContainsString("code: 'invalid_api_response'", $client);
        self::assertStringContainsString("message: 'The server returned an invalid API response.'", $client);
        self::assertStringContainsString("if (!payload || !Object.prototype.hasOwnProperty.call(payload, 'data'))", $client);
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Http;

use PHPUnit\Framework\TestCase;

final class ApacheRulesTest extends TestCase
{
    public function testOnlyExistingFilesBypassTheFrontController(): void
    {
        $rules = (string) file_get_contents(dirname(__DIR__, 2) . '/public/.htaccess');

        self::assertMatchesRegularExpression('/RewriteCond\s+%\{REQUEST_FILENAME\}\s+-f\RRewriteRule\s+\^\s+-\s+\[L\]/', $rules);
        self::assertDoesNotMatchRegularExpression('/RewriteCond\s+%\{REQUEST_FILENAME\}\s+-d\b/', $rules);
    }
}

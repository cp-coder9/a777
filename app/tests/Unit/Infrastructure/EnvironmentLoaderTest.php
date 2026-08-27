<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use Architex\Infrastructure\Config\EnvironmentLoader;
use PHPUnit\Framework\TestCase;

final class EnvironmentLoaderTest extends TestCase
{
    public function testPrivateRuntimeFileSuppliesMissingValuesWithoutOverridingHostEnvironment(): void
    {
        $root = dirname(__DIR__, 3) . '/storage/tmp/environment-' . bin2hex(random_bytes(8));
        mkdir($root . '/config', 0700, true);
        file_put_contents($root . '/config/runtime.php', "<?php return ['ARCHITEX_TEST_FILE_VALUE'=>'from-file','ARCHITEX_TEST_HOST_VALUE'=>'from-file'];\n");
        putenv('ARCHITEX_TEST_FILE_VALUE');
        putenv('ARCHITEX_TEST_HOST_VALUE=from-host');
        try {
            EnvironmentLoader::apply($root);
            self::assertSame('from-file', getenv('ARCHITEX_TEST_FILE_VALUE'));
            self::assertSame('from-host', getenv('ARCHITEX_TEST_HOST_VALUE'));
        } finally {
            putenv('ARCHITEX_TEST_FILE_VALUE'); putenv('ARCHITEX_TEST_HOST_VALUE');
            unlink($root . '/config/runtime.php'); rmdir($root . '/config'); rmdir($root);
        }
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class V1ParityIntegrationTest extends TestCase
{
    public function testModuleOwnedWorkspacesLoadBeforeTheProductionShell(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $app = (string) file_get_contents($public . '/app.php');
        $shellOffset = strpos($app, 'src="/assets/js/shell.js"');
        self::assertNotFalse($shellOffset);
        foreach (['meetings-workspace', 'practice-workspace', 'workspace-scope'] as $module) {
            $script = 'src="/assets/js/' . $module . '.js"';
            self::assertSame(1, substr_count($app, $script), $module . ' must load once');
            self::assertLessThan($shellOffset, strpos($app, $script), $module . ' must load before its shell adapter');
            self::assertStringContainsString('href="/assets/css/' . $module . '.css"', $app);
        }
    }
}

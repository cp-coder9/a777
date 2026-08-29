<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class CloseoutWorkspaceTest extends TestCase
{
    public function testFmBridgeUsesTheMariaDbCloseoutWorkspace(): void
    {
        $shell=file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');self::assertIsString($shell);
        foreach(['id="closeoutWorkspace"','/closeout-workspace','/practical-completions','/handover-packs','/occupation-readiness','/final-account-readiness','/defects-liability-periods','No internal record grants municipal occupation approval','No closeout action moves money','humanConfirmed']as$text)self::assertStringContainsString($text,$shell);
        self::assertStringContainsString("TOOLS.fm_bridge.status = 'live'",$shell);
        self::assertStringContainsString("state.tool==='fm_bridge'",$shell);
    }
}

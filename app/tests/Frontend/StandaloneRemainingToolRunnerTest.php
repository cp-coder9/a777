<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class StandaloneRemainingToolRunnerTest extends TestCase
{
    public function testEveryPackTileUsesTheStandaloneRunEndpointAndGeneratedSchemaFields(): void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        foreach(['standaloneGenericFields','standaloneGenericInput','Run and save standalone record','inputSchema','positive_number','non_negative_number','money','percentage','lines']as$text)self::assertStringContainsString($text,$shell);
        foreach(['standaloneNonAssignable','tool.description','standaloneCanAssignRun','tool.canAssign']as$text)self::assertStringContainsString($text,$shell);
        self::assertStringNotContainsString('Project context currently required',$shell);
        self::assertStringNotContainsString('Open project tool',$shell);
    }
}

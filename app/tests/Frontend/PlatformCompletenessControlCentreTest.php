<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class PlatformCompletenessControlCentreTest extends TestCase
{
    public function testV1ShellContainsResponsiveGovernedControlCentre():void
    {
        $app=(string)file_get_contents(dirname(__DIR__,2).'/public/app.php');$shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');$css=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');$front=(string)file_get_contents(dirname(__DIR__,2).'/public/index.php');
        self::assertStringContainsString('Platform Completeness',$app);
        foreach(['Platform Completeness','/admin/platform-completeness','Master pack register','95 persisted items','Immutable sign-offs','AI may execute: no','Shared FileManager asset','platform-completeness/workstreams/${id}/signoffs']as$token)self::assertStringContainsString($token,$shell);
        foreach(['.pc-workspace','.pc-summary','.pc-workstreams','.pc-section','@media(max-width:760px)']as$token)self::assertStringContainsString($token,$css);
        self::assertStringContainsString('admin/platform-completeness',$front);
    }
}

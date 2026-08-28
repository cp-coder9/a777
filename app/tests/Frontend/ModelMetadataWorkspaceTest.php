<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class ModelMetadataWorkspaceTest extends TestCase
{
    public function testCanonicalWorkspaceUsesPersistedProviderHonestModelFlow():void
    {
        $shell=file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');self::assertIsString($shell);
        foreach(['id="modelMetadataWorkspace"','BIM / IFC Extraction','Model source register','Structured metadata revision','Validation & verification','Professional review','Issue controlled metadata','/model-sources','/model-metadata-revisions/','nativeParsingAvailable','Native IFC/Revit interpretation unavailable','humanConfirmed:v.get(\'humanConfirmed\')===\'on\'','TOOLS.bim_ifc.status = \'live\'']as$expected)self::assertStringContainsString($expected,$shell);
        self::assertStringNotContainsString('Native IFC parser active',$shell);
        self::assertStringContainsString('latestDecision=source.decisions.at(-1)',$shell);
        self::assertStringContainsString('review.elements.humanConfirmed.checked=true',$shell);
    }
}

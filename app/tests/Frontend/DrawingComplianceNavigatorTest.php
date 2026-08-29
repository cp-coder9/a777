<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class DrawingComplianceNavigatorTest extends TestCase
{
    public function testNavigatorUsesOneAuthenticatedResponsiveWorkspaceForStandaloneAndProjectModes():void
    {
        $public=dirname(__DIR__,2).'/public';$shell=(string)file_get_contents($public.'/assets/js/shell.js');$css=(string)file_get_contents($public.'/assets/css/architex-v1.css');$index=(string)file_get_contents($public.'/index.php');$app=(string)file_get_contents($public.'/app.php');
        foreach(['drawingComplianceWorkspace','hydrateDrawingCompliance','/drawing-compliance/sources','/drawing-compliance/checklist','/drawing-compliance/reviews','illustrative_poc_until_verified','responsibleRole','selectedProjectId','pass','watch','fail','needs_input','not_applicable','SPLUMA / land-use screen','Required drawing package','Internal cross-reference integrity','Missing inputs','cannot grant or deny municipal approval']as$needle)self::assertStringContainsString($needle,$shell);
        foreach(['drawing-compliance-workspace','drawing-compliance-grid','drawing-compliance-statuses','drawing-compliance-badges .status','@media(max-width:760px)']as$needle)self::assertStringContainsString($needle,$css);
        self::assertStringContainsString('sa-council-drawing-compliance-navigator',$index);
        self::assertStringContainsString('TOOLS.drawing_compliance_navigator',$app);
        self::assertStringContainsString('<meta name="description"',$app);
        self::assertStringNotContainsString('approved by municipality',$shell);
        self::assertStringNotContainsString('guaranteed compliant',$shell);
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class FormsWorkspaceTest extends TestCase
{
    public function testCanonicalFormsToolUsesVersionedGovernedWorkspace():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');$styles=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');
        foreach(['id="integratedFormSystem"','Template Library','Form Editor','My Drafts','Evidence Exports','Audit history','No PDF or e-signature provider is configured','/form-templates','/form-drafts','/submit-review','/approve','/issue','humanConfirmed:true','TOOLS.forms.status = \'live\'']as$expected)self::assertStringContainsString($expected,$shell);
        self::assertStringContainsString('.forms-workspace',$styles);self::assertStringContainsString('@media(max-width:760px)',$styles);
    }
}

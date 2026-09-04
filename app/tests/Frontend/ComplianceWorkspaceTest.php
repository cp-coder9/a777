<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class ComplianceWorkspaceTest extends TestCase
{
    public function testCanonicalXaToolRunsAllFourGovernedComplianceWorkflows():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');$styles=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');
        foreach(['id="complianceWorkspace"','Fenestration XA','R-value thermal assemblies','SANS forms & checklists','Drawing metadata pre-check','/compliance-rule-versions','/verify','fenestration_xa_calculator','r_value_thermal_calculator','sans_forms_checklist_builder','drawing_compliance_precheck','sourceStatus','issueEligible','Professional signoff required','No municipal approval is represented','/toolbox/runs','TOOLS.xa.status = \'live\'']as$expected)self::assertStringContainsString($expected,$shell);
        self::assertStringContainsString('if(!root.isConnected)return;', $shell);
        self::assertStringContainsString("const list=root.querySelector('#complianceSourceList');if(list)", $shell);
        self::assertStringContainsString('.compliance-workspace',$styles);self::assertStringContainsString('@media(max-width:760px)',$styles);
    }
}

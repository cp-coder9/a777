<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class CPDAssessmentWorkspaceTest extends TestCase
{
    public function testCpdIsATopLevelV1WorkspaceWithGovernedRoleViews(): void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        $app=(string)file_get_contents(dirname(__DIR__,2).'/public/app.php');
        foreach(['CPD Learning Hub','Professional learner','Content studio','Human review queue','Accreditation evidence','Course catalogue','My CPD record','Commercial controls','CPD analytics','Certificate verification','cpd-assessment-workspace']as$text)self::assertStringContainsString($text,$shell.$app);
        foreach(['/cpd/workspace','/cpd/profile','/cpd/content','/cpd/assessments','/cpd/accreditation','/cpd/courses','/cpd/entitlements','/cpd/attempts','/cpd/certificates']as$route)self::assertStringContainsString($route,$shell);
        self::assertStringContainsString("openGlobal('cpd')",$app);
        self::assertStringContainsString("id:'cpd'",$app);
        self::assertStringContainsString("if(id==='cpd')",$app);
        self::assertStringContainsString('Accreditation is not automated',$shell);
        self::assertStringContainsString('Provider integration required',$shell);
        self::assertStringNotContainsString('Firebase',$shell);
    }
}

<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class TrustComplianceWorkspaceTest extends TestCase
{
    public function testTrustWorkspaceContainsRealComplianceAndPopiaControls():void
    {
        $shell=file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');self::assertIsString($shell);
        foreach(['trustComplianceWorkspace','/trust-compliance-workspace','/trust/compliance-items','/trust/processing-activities','/trust/data-subject-requests','/trust/breaches','/verification-review-queue','trustCloseBreach','Coverage gap','SACAP','ECSA','SACQSP','SACLAP','SACPCMP','External verification requires a real provider']as$text)self::assertStringContainsString($text,$shell);
    }
}

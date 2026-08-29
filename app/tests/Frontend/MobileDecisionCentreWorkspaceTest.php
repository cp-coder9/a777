<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class MobileDecisionCentreWorkspaceTest extends TestCase
{
    public function testV1GlobalInboxUsesLiveUnifiedDecisionModel():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        foreach(['Mobile Decision Centre','What needs your attention today','/mobile-decision-inbox','operating-snapshot','Review and confirm','AI may draft','Architex does not hold or move funds','cpd_reminder','release_gate']as$token)self::assertStringContainsString($token,$shell);
        self::assertStringContainsString('Exact build timestamp (ISO 8601)',$shell);
        self::assertStringNotContainsString('type="datetime-local" name="buildTimestamp"',$shell);
        self::assertStringNotContainsString('4 overdue',$shell);
    }
}

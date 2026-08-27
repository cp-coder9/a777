<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class AppointmentWorkspaceTest extends TestCase
{
    public function testCanonicalFeeWorkspaceExposesGovernedAppointmentAndKickoffFlow():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        $styles=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');
        self::assertStringContainsString('id="appointmentCreateForm"',$shell);
        self::assertStringContainsString("api.post('/appointments'",$shell);
        self::assertStringContainsString('/professional-confirmation',$shell);
        self::assertStringContainsString("/issue`",$shell);
        self::assertStringContainsString('humanApprovalConfirmed',$shell);
        self::assertStringContainsString('professionalRegistrationNumber',$shell);
        self::assertStringContainsString('Seven kickoff gates',$shell);
        self::assertStringContainsString('Appointment Letter Draft',$shell);
        self::assertStringContainsString('kickoffReadiness',$shell);
        self::assertStringContainsString('/kickoff-checklist',$shell);
        self::assertStringContainsString('/appointments/${encodeURIComponent(latestId)}/revisions',$shell);
        self::assertStringContainsString('.appointment-layout .trust-row small{overflow-wrap:anywhere;word-break:break-word}',$styles);
    }
}

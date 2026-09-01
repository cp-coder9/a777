<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Governance;

use Architex\Platform\Governance\ControlledRegisterException;
use Architex\Platform\Governance\ControlledRegisterPolicy;
use PHPUnit\Framework\TestCase;

final class ControlledRegisterPolicyTest extends TestCase
{
    public function testDefinesEveryMissingProjectRegisterBehindOneInterface(): void
    {
        $policy = new ControlledRegisterPolicy();

        self::assertSame(
            ['dispute_resolution', 'insurance_register', 'nhbrc_enrolment', 'contractor_compliance'],
            array_keys($policy->definitions()),
        );
        self::assertSame('notice_draft', $policy->initialStatus('dispute_resolution'));
        self::assertContains('active', $policy->allowedTransitions('insurance_register', 'pending_evidence'));
        self::assertContains('enrolled', $policy->allowedTransitions('nhbrc_enrolment', 'submitted'));
        self::assertContains('compliant', $policy->allowedTransitions('contractor_compliance', 'under_review'));
    }

    public function testRejectsUnknownRegistersAndInvalidTransitions(): void
    {
        $policy = new ControlledRegisterPolicy();
        $this->expectException(ControlledRegisterException::class);
        $this->expectExceptionMessage('controlled_register_transition_invalid');

        $policy->assertTransition('dispute_resolution', 'notice_draft', 'resolved');
    }
}

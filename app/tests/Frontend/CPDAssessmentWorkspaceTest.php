<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class CPDAssessmentWorkspaceTest extends TestCase
{
    public function testClientEligibilityPolicy(): void
    {
        $script=dirname(__DIR__).'/Frontend/cpd-eligibility.test.js';
        exec('node '.escapeshellarg($script).' 2>&1',$output,$status);

        self::assertSame(0,$status,implode("\n",$output));
    }

    public function testCpdIsATopLevelV1WorkspaceWithGovernedRoleViews(): void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        $eligibility=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/cpd-eligibility.js');
        $app=(string)file_get_contents(dirname(__DIR__,2).'/public/app.php');
        foreach(['CPD Learning Hub','Professional learner','Content studio','Human review queue','Accreditation evidence','Course catalogue','My CPD record','Commercial controls','CPD analytics','Certificate verification','cpd-assessment-workspace']as$text)self::assertStringContainsString($text,$shell.$app);
        foreach(['/cpd/workspace','/cpd/profile','/cpd/content','/cpd/assessments','/cpd/accreditation','/cpd/courses','/cpd/entitlements','/cpd/attempts','/cpd/certificates','/cpd/commercial-settings','/cpd/referrals']as$route)self::assertStringContainsString($route,$shell);
        self::assertStringContainsString('Download PDF',$shell);
        self::assertStringContainsString('configuredProviders',$shell);
        self::assertStringContainsString('Generate with Nemotron',$shell);
        self::assertStringContainsString("document.title='CPD Learning Hub — Architex OS'",$shell);
        self::assertStringContainsString('canReviewAssessments',$shell);
        self::assertStringContainsString('canDecideAccreditation',$shell);
        self::assertStringContainsString('canAssignRoles',$shell);
        self::assertStringContainsString('profile.elements.popiaConsent.checked=Boolean(cpdState.profile.popiaConsentAt)',$shell);
        self::assertStringContainsString("question.questionType==='multiple_choice'?'checkbox':'radio'",$shell);
        self::assertStringContainsString('selectedOptionIds:values.getAll(`q_${question.id}`)',$shell);
        self::assertStringContainsString("course.priceCents===0?'Enrol free':'Request EFT access'",$shell);
        self::assertStringContainsString('cpdCourseEligibility',$shell);
        self::assertStringContainsString('cpdAttemptSubmissionAccess',$shell);
        self::assertStringContainsString('cpd-eligibility.js',$app);
        self::assertStringContainsString('Accredited to another professional body',$eligibility);
        self::assertStringContainsString('Your registered body must match before you can enrol.',$eligibility);
        self::assertStringContainsString('Your registered body must match before you can submit this attempt.',$eligibility);
        self::assertStringContainsString('Learner-owned attempt',$eligibility);
        self::assertStringContainsString("cpdApi.post('/cpd/entitlements',{courseId})",$shell);
        self::assertStringContainsString("openGlobal('cpd')",$app);
        self::assertStringContainsString("id:'cpd'",$app);
        self::assertStringContainsString("if(id==='cpd')",$shell.$app);
        self::assertStringContainsString('Accreditation is not automated',$shell);
        self::assertStringContainsString('Provider integration required',$shell);
        self::assertStringNotContainsString('Firebase',$shell);
    }
}

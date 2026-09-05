<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\CPD;

use Architex\Platform\CPD\CPDAssessmentException;
use Architex\Platform\CPD\CPDAssessmentService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class CPDAssessmentServiceTest extends MariaDbTestCase
{
    public function testAccreditedPaidAssessmentProducesAResearchBackedImmutableRecord(): void
    {
        [$organisation, $admin, $learner] = $this->tenant();
        $service = new CPDAssessmentService($this->pdo);

        self::assertCount(8, $service->professionalBodyRules());
        $profile = $service->saveProfile($organisation, $learner, 'member', [
            'fullName'=>'Learner Architect', 'profession'=>'Architect', 'professionalBody'=>'SACAP',
            'registrationNumber'=>'SACAP-123', 'registrationCategory'=>'PrArch',
            'cycleStart'=>'2026-01-01', 'cycleEnd'=>'2030-12-31', 'participationRole'=>'learner',
            'popiaConsent'=>true,
        ]);
        self::assertSame('SACAP', $profile['professionalBody']);

        $content = $service->createContent($organisation, $admin, 'owner', [
            'title'=>'Responsible AI in professional practice', 'contentType'=>'webinar',
            'providerName'=>'Architex', 'durationMinutes'=>60, 'permissionStatus'=>'owned',
            'learningOutcomes'=>['Apply responsible AI boundaries', 'Preserve professional sign-off'],
            'sourceText'=>'A sufficiently detailed owned transcript for a governed assessment.',
            'targetBodies'=>['SACAP'], 'humanConfirmed'=>true,
        ]);
        $assessment = $service->createAssessmentDraft($organisation, $admin, 'owner', $content['id'], [
            'title'=>'Responsible AI assessment', 'passPercent'=>70, 'attemptLimit'=>3,
            'questions'=>[
                ['prompt'=>'Who retains professional responsibility?', 'type'=>'single_choice', 'points'=>1, 'options'=>[['text'=>'The registered professional','correct'=>true],['text'=>'The AI system','correct'=>false]]],
                ['prompt'=>'A professional may delegate final responsibility to an AI system.', 'type'=>'true_false', 'points'=>1, 'options'=>[['text'=>'True','correct'=>false],['text'=>'False','correct'=>true]]],
                ['prompt'=>'Explain the required human sign-off.', 'type'=>'text_review', 'points'=>1],
            ],
            'generatedWithAi'=>false,
        ]);
        self::assertFalse($assessment['generatedWithAi']);
        self::assertSame('human_review_required', $assessment['status']);
        self::assertSame('true_false', $assessment['questions'][1]['questionType']);

        $assessment = $service->reviewAssessment($organisation, $admin, 'owner', $assessment['id'], [
            'decision'=>'approved_for_accreditation', 'reason'=>'Human reviewer checked every question.', 'humanConfirmed'=>true,
        ]);
        $application = $service->createAccreditationApplication($organisation, $admin, 'owner', $assessment['id'], [
            'professionalBody'=>'SACAP', 'connectorMode'=>'document_export', 'requestedCategory'=>'category_1',
            'requestedCredits'=>'0.1', 'providerName'=>'CPD Central', 'humanConfirmed'=>true,
        ]);
        $application = $service->decideAccreditation($organisation, $admin, 'owner', $application['id'], [
            'decision'=>'accredited', 'approvedCategory'=>'category_1', 'approvedCredits'=>'0.1',
            'reference'=>'CPD-2026-001', 'validFrom'=>'2026-08-01', 'validUntil'=>'2027-07-31',
            'reason'=>'Accreditation evidence reviewed.', 'humanConfirmed'=>true,
        ]);
        $course = $service->publishCourse($organisation, $admin, 'owner', $application['id'], [
            'priceCents'=>5000, 'currency'=>'ZAR', 'commercialModel'=>'webinar_addon', 'humanConfirmed'=>true,
        ]);
        self::assertSame('live', $course['status']);
        self::assertSame('0.1', $course['approvedCredits']);
        $settings=$service->configureCommercialSettings($organisation,$admin,'owner',['platformFeePercent'=>10,'minimumPlatformFeeCents'=>100,'payoutEnabled'=>true,'configuredProviders'=>['manual_eft'],'humanConfirmed'=>true]);
        self::assertSame(1000,$settings['platformFeeBasisPoints']);

        $entitlement = $service->createManualEntitlement($organisation, $learner, 'member', $course['id'], [
            'paymentMethod'=>'manual_eft', 'paymentReference'=>'EFT-001', 'humanConfirmed'=>true,
        ]);
        self::assertSame('review_required', $entitlement['status']);
        $entitlement = $service->reviewManualEntitlement($organisation, $admin, 'owner', $entitlement['id'], [
            'decision'=>'grant', 'reason'=>'Bank evidence matched.', 'humanConfirmed'=>true,
        ]);
        self::assertSame('active', $entitlement['status']);
        self::assertSame(500,(int)$this->pdo->query('SELECT platform_fee_cents FROM cpd_purchase_ledger')->fetchColumn());
        self::assertSame(4500,(int)$this->pdo->query('SELECT payout_cents FROM cpd_payouts')->fetchColumn());

        $freeLearner=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'free-learner-cpd@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$freeLearner]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'member','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$freeLearner]);
        $service->saveProfile($organisation, $freeLearner, 'member', [
            'fullName'=>'Free Learner Architect', 'profession'=>'Architect', 'professionalBody'=>'SACAP',
            'registrationNumber'=>'SACAP-FREE-1', 'registrationCategory'=>'Professional Architect',
            'cycleStart'=>'2026-01-01', 'cycleEnd'=>'2030-12-31', 'participationRole'=>'learner',
            'popiaConsent'=>true,
        ]);
        $this->pdo->prepare('UPDATE cpd_courses SET price_cents=0 WHERE id=UNHEX(:id)')->execute(['id'=>$course['id']]);
        self::assertTrue(method_exists($service,'createFreeEntitlement'),'The CPD service must expose zero-cost enrollment.');
        $freeEntitlement=$service->createFreeEntitlement($organisation,$freeLearner,'member',$course['id']);
        self::assertSame('active',$freeEntitlement['status']);
        self::assertSame('free',$freeEntitlement['paymentMethod']);
        self::assertSame(0,$freeEntitlement['amountCents']);
        self::assertSame($freeEntitlement['id'],$service->createFreeEntitlement($organisation,$freeLearner,'member',$course['id'])['id']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM cpd_purchase_ledger WHERE gross_cents=0 AND platform_fee_cents=0 AND content_owner_net_cents=0")->fetchColumn());

        $workspace=$service->workspace($organisation,$learner,'member');
        self::assertArrayNotHasKey('isCorrect',$workspace['assessments'][0]['questions'][0]['options'][0]);

        $attempt = $service->startAttempt($organisation, $learner, 'member', $course['id']);
        $result = $service->submitAttempt($organisation, $learner, 'member', $attempt['id'], [
            'answers'=>[
                ['questionId'=>$assessment['questions'][0]['id'], 'selectedOptionIds'=>[$assessment['questions'][0]['options'][0]['id']]],
                ['questionId'=>$assessment['questions'][1]['id'], 'selectedOptionIds'=>[$assessment['questions'][1]['options'][1]['id']]],
                ['questionId'=>$assessment['questions'][2]['id'], 'text'=>'A registered professional checks and signs the output.'],
            ],
            'humanConfirmed'=>true,
        ]);
        self::assertSame('manual_review_required', $result['status']);
        self::assertArrayNotHasKey('reviewAnswers',$service->workspace($organisation,$learner,'member')['attempts'][0]);
        self::assertCount(0,$service->workspace($organisation,$freeLearner,'member')['attempts']);
        $service->saveProfile($organisation,$freeLearner,'member',[
            'fullName'=>'Independent Reviewer','profession'=>'Architect','professionalBody'=>'SACAP',
            'registrationNumber'=>'REVIEWER-1','registrationCategory'=>'PrArch','cycleStart'=>'2026-01-01','cycleEnd'=>'2030-12-31','participationRole'=>'learner','popiaConsent'=>true,
        ]);
        $service->assignParticipationRole($organisation,$admin,'owner',$freeLearner,['participationRole'=>'cpd_reviewer','reason'=>'Verified assessment reviewer.','humanConfirmed'=>true]);
        $reviewQueue=$service->workspace($organisation,$freeLearner,'member')['attempts'];
        self::assertCount(1,$reviewQueue);
        self::assertSame($result['id'],$reviewQueue[0]['id']);
        self::assertCount(3,$reviewQueue[0]['reviewAnswers']);
        self::assertNotEmpty($reviewQueue[0]['reviewAnswers'][2]['answerText']);
        $this->pdo->prepare("UPDATE cpd_courses SET status='suspended' WHERE id=UNHEX(:id)")->execute(['id'=>$course['id']]);
        try {
            $service->reviewAttempt($organisation,$admin,'owner',$result['id'],['decision'=>'pass','reason'=>'Check atomic eligibility failure.','humanConfirmed'=>true]);
            self::fail('An ineligible certificate must roll back the passing review.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_course_not_live',$exception->reason);
        }
        self::assertSame('manual_review_required',$this->pdo->query('SELECT status FROM cpd_attempts')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM cpd_certificates')->fetchColumn());
        $this->pdo->prepare("UPDATE cpd_courses SET status='live' WHERE id=UNHEX(:id)")->execute(['id'=>$course['id']]);
        $result = $service->reviewAttempt($organisation, $admin, 'owner', $result['id'], [
            'decision'=>'pass', 'reason'=>'Text response demonstrates the outcome.', 'humanConfirmed'=>true,
        ]);
        self::assertTrue($result['passed']);
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM cpd_certificates')->fetchColumn(), 'A reviewed pass must issue its certificate automatically.');
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM cpd_professional_records')->fetchColumn());

        $certificate = $service->issueCertificate($organisation, $learner, 'member', $result['id'], 'https://example.test');
        self::assertSame('CPD-2026-001', $certificate['accreditationReference']);
        self::assertSame('0.1', $certificate['creditsAwarded']);
        $verified=$service->verifyCertificate($certificate['verificationCode']);
        self::assertSame($certificate['verificationCode'], $verified['verificationCode']);
        self::assertTrue($verified['valid']);
        self::assertArrayNotHasKey('learnerUserId',$verified);
        self::assertArrayNotHasKey('organisationId',$verified);
        self::assertArrayNotHasKey('registrationNumber',$verified);
        $this->pdo->exec("UPDATE cpd_professional_body_rules SET unit_label='changed after issue' WHERE body_code='SACAP'");
        $pdf=$service->certificatePdf($organisation,$learner,'member',$certificate['id']);
        self::assertStringContainsString(mb_convert_encoding('CPD award: 0.1 credits','UTF-16BE','UTF-8'),$pdf['content']);
        self::assertStringNotContainsString(mb_convert_encoding('changed after issue','UTF-16BE','UTF-8'),$pdf['content']);
        self::assertStringStartsWith('%PDF-1.4',$pdf['content']);
        self::assertStringContainsString(mb_convert_encoding('Course: Responsible AI in professional practice','UTF-16BE','UTF-8'),$pdf['content']);
        self::assertStringContainsString(mb_convert_encoding('Assessment status: passed','UTF-16BE','UTF-8'),$pdf['content']);
        self::assertStringContainsString('/Subtype /Link',$pdf['content']);
        self::assertSame('application/pdf',$pdf['mimeType']);
        $service->captureReferral($organisation,$learner,['source'=>'cpd_central','campaign'=>'launch','eventType'=>'completed','courseId'=>$course['id'],'consented'=>true]);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM cpd_referral_events')->fetchColumn());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM cpd_professional_records')->fetchColumn());
        self::assertGreaterThanOrEqual(10, (int)$this->pdo->query('SELECT COUNT(*) FROM cpd_audit_events')->fetchColumn());

        $auto=$service->createAssessmentDraft($organisation,$admin,'owner',$content['id'],[
            'title'=>'Automatically scored assessment','passPercent'=>70,'attemptLimit'=>3,
            'questions'=>[['prompt'=>'Who signs off?','type'=>'single_choice','points'=>1,'options'=>[['text'=>'Professional','correct'=>true],['text'=>'Model','correct'=>false]]]],'generatedWithAi'=>false,
        ]);
        $service->reviewAssessment($organisation,$admin,'owner',$auto['id'],['decision'=>'approved_for_accreditation','reason'=>'Checked source and answers.','humanConfirmed'=>true]);
        $autoApplication=$service->createAccreditationApplication($organisation,$admin,'owner',$auto['id'],['professionalBody'=>'SACAP','connectorMode'=>'document_export','requestedCategory'=>'category_1','requestedCredits'=>'0.1','providerName'=>'CPD Central','humanConfirmed'=>true]);
        $service->decideAccreditation($organisation,$admin,'owner',$autoApplication['id'],['decision'=>'accredited','approvedCategory'=>'category_1','approvedCredits'=>'0.1','reference'=>'CPD-AUTO-002','validFrom'=>date('Y-m-d',strtotime('-1 day')),'validUntil'=>date('Y-m-d',strtotime('+1 year')),'reason'=>'Evidence verified.','humanConfirmed'=>true]);
        $autoCourse=$service->publishCourse($organisation,$admin,'owner',$autoApplication['id'],['priceCents'=>0,'currency'=>'ZAR','commercialModel'=>'free_funnel','humanConfirmed'=>true]);
        $service->createFreeEntitlement($organisation,$learner,'member',$autoCourse['id']);
        $autoAttempt=$service->startAttempt($organisation,$learner,'member',$autoCourse['id']);
        $autoResult=$service->submitAttempt($organisation,$learner,'member',$autoAttempt['id'],['answers'=>[['questionId'=>$auto['questions'][0]['id'],'selectedOptionIds'=>[$auto['questions'][0]['options'][0]['id']]]],'humanConfirmed'=>true]);
        self::assertSame('passed',$autoResult['status']);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM cpd_certificates')->fetchColumn());
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM cpd_professional_records')->fetchColumn());
        $service->issueCertificate($organisation,$learner,'member',$autoAttempt['id'],'');
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM cpd_certificates')->fetchColumn());

        $analytics=$service->workspace($organisation,$admin,'owner')['analytics'];
        self::assertSame(2,$analytics['certificateIssueCount']);
        self::assertCount(2,$analytics['assessmentAnalytics']);
        $automatic=array_values(array_filter($analytics['assessmentAnalytics'],fn(array $item):bool=>$item['assessmentId']===$auto['id']))[0];
        self::assertSame(1,$automatic['attemptCount']);
        self::assertSame(1,$automatic['certificateIssueCount']);
        self::assertCount(1,$automatic['questionPerformance']);
        self::assertSame('insufficient_data',$automatic['questionPerformance'][0]['qualityIndicator']);
        self::assertContains('Architex',array_column($analytics['providerAnalytics'],'providerName'));
    }

    public function testPublishingAndExternalPaymentFailClosed(): void
    {
        [$organisation, $admin, $learner] = $this->tenant();
        $service = new CPDAssessmentService($this->pdo);
        try {
            $service->recordProviderPayment($organisation, 'payfast', ['eventId'=>'evt-1'], null);
            self::fail('An unconfigured provider must not create an entitlement.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_payment_integration_required', $exception->reason);
        }
        try {
            $service->startAttempt($organisation, $learner, 'member', str_repeat('a', 32));
            self::fail('A missing course must not start an attempt.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_course_not_found', $exception->reason);
        }
        self::assertNotSame($admin, $learner);
    }

    public function testProfessionalRoleBodyMappingIsGovernedAndOtherRequiresManualConfirmation(): void
    {
        [$organisation, $admin, $learner] = $this->tenant();
        $service = new CPDAssessmentService($this->pdo);

        $mappings = $service->professionalRoleMappings();
        self::assertCount(13, $mappings);
        self::assertSame('SACAP', $mappings[0]['professionalBody']);
        self::assertSame('architectural_professional', $mappings[0]['role']);
        self::assertTrue($mappings[12]['manualBodyConfirmationRequired']);

        $profile = $service->saveProfile($organisation, $learner, 'member', [
            'fullName' => 'Mapped Architect', 'profession' => 'Architect', 'professionalBody' => 'SACAP',
            'registrationNumber' => 'SACAP-MAP-1', 'registrationCategory' => 'Professional Architect',
            'cycleStart' => '2026-01-01', 'cycleEnd' => '2030-12-31', 'participationRole' => 'learner',
            'popiaConsent' => true,
        ]);
        self::assertSame('architectural_professional', $profile['profession']);
        self::assertSame('SACAP', $profile['professionalBody']);

        try {
            $service->saveProfile($organisation, $learner, 'member', [
                'fullName' => 'Mapped Architect', 'profession' => 'architectural_professional', 'professionalBody' => 'ECSA',
                'registrationNumber' => 'SACAP-MAP-1', 'registrationCategory' => 'Professional Architect',
                'cycleStart' => '2026-01-01', 'cycleEnd' => '2030-12-31', 'participationRole' => 'learner',
                'popiaConsent' => true,
            ]);
            self::fail('A mapped role must not be saved under a different professional body.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_professional_role_body_mismatch', $exception->reason);
        }

        try {
            $service->saveProfile($organisation, $admin, 'owner', [
                'fullName' => 'Other Professional', 'profession' => 'other_built_environment_professional', 'professionalBody' => 'SACPVP',
                'registrationNumber' => 'OTHER-1', 'registrationCategory' => 'Manual category',
                'cycleStart' => '2026-01-01', 'cycleEnd' => '2030-12-31', 'participationRole' => 'learner',
                'popiaConsent' => true,
            ]);
            self::fail('Other professional roles require an explicit manual body confirmation.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_professional_role_manual_confirmation_required', $exception->reason);
        }

        $other = $service->saveProfile($organisation, $admin, 'owner', [
            'fullName' => 'Other Professional', 'profession' => 'other_built_environment_professional', 'professionalBody' => 'SACPVP',
            'registrationNumber' => 'OTHER-1', 'registrationCategory' => 'Manual category',
            'cycleStart' => '2026-01-01', 'cycleEnd' => '2030-12-31', 'participationRole' => 'learner',
            'manualBodyConfirmation' => true, 'popiaConsent' => true,
        ]);
        self::assertSame('other_built_environment_professional', $other['profession']);
        self::assertSame('SACPVP', $other['professionalBody']);
    }

    public function testFreeEntitlementRequiresLearnerProfileToMatchCourseBody(): void
    {
        [$organisation, $admin, $learner] = $this->tenant();
        $service = new CPDAssessmentService($this->pdo);
        $service->saveProfile($organisation, $learner, 'member', [
            'fullName' => 'Architect Learner', 'profession' => 'Architect', 'professionalBody' => 'SACAP',
            'registrationNumber' => 'SACAP-ELIGIBILITY-1', 'registrationCategory' => 'Professional Architect',
            'cycleStart' => '2026-01-01', 'cycleEnd' => '2030-12-31', 'participationRole' => 'learner',
            'popiaConsent' => true,
        ]);
        $course = $this->accreditedFreeCourse($service, $organisation, $admin, 'ECSA');

        try {
            $service->createFreeEntitlement($organisation, $learner, 'member', $course['id']);
            self::fail('A learner must not receive an entitlement for a course accredited to another professional body.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_course_professional_body_mismatch', $exception->reason);
        }
    }

    public function testManualEntitlementReviewRechecksLearnerProfileBody(): void
    {
        [$organisation, $admin, $learner] = $this->tenant();
        $service = new CPDAssessmentService($this->pdo);
        $this->saveECSAProfile($service, $organisation, $learner);
        $course = $this->accreditedFreeCourse($service, $organisation, $admin, 'ECSA');
        $entitlement = $service->createManualEntitlement($organisation, $learner, 'member', $course['id'], [
            'paymentMethod' => 'manual_eft', 'paymentReference' => 'EFT-BODY-RECHECK-1', 'humanConfirmed' => true,
        ]);
        $this->saveSACAPProfile($service, $organisation, $learner);

        try {
            $service->reviewManualEntitlement($organisation, $admin, 'owner', $entitlement['id'], [
                'decision' => 'grant', 'reason' => 'Payment evidence matched.', 'humanConfirmed' => true,
            ]);
            self::fail('Manual payment review must recheck the learner profile body before activation.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_course_professional_body_mismatch', $exception->reason);
        }
    }

    public function testAttemptStartRechecksLearnerProfileBodyAfterEntitlement(): void
    {
        [$organisation, $admin, $learner] = $this->tenant();
        $service = new CPDAssessmentService($this->pdo);
        $this->saveECSAProfile($service, $organisation, $learner);
        $course = $this->accreditedFreeCourse($service, $organisation, $admin, 'ECSA');
        $service->createFreeEntitlement($organisation, $learner, 'member', $course['id']);
        $this->saveSACAPProfile($service, $organisation, $learner);

        try {
            $service->startAttempt($organisation, $learner, 'member', $course['id']);
            self::fail('Attempt start must recheck a profile body that changed after entitlement.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_course_professional_body_mismatch', $exception->reason);
        }
    }

    public function testCertificateIssueRollsBackWhenLearnerProfileBodyChanges(): void
    {
        [$organisation, $admin, $learner] = $this->tenant();
        $service = new CPDAssessmentService($this->pdo);
        $this->saveECSAProfile($service, $organisation, $learner);
        $course = $this->accreditedFreeCourse($service, $organisation, $admin, 'ECSA');
        $service->createFreeEntitlement($organisation, $learner, 'member', $course['id']);
        $attempt = $service->startAttempt($organisation, $learner, 'member', $course['id']);
        $this->saveSACAPProfile($service, $organisation, $learner);

        try {
            $service->submitAttempt($organisation, $learner, 'member', $attempt['id'], [
                'answers' => [['questionId' => $this->assessmentQuestionId($course['id']), 'selectedOptionIds' => [$this->assessmentCorrectOptionId($course['id'])]]],
                'humanConfirmed' => true,
            ]);
            self::fail('Certificate issuance must reject a mismatched learner profile and roll back the passing attempt.');
        } catch (CPDAssessmentException $exception) {
            self::assertSame('cpd_course_professional_body_mismatch', $exception->reason);
        }
        self::assertSame('in_progress', $this->pdo->query('SELECT status FROM cpd_attempts')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cpd_certificates')->fetchColumn());
    }

    /** @return array<string,mixed> */
    private function accreditedFreeCourse(CPDAssessmentService $service, string $organisation, string $admin, string $professionalBody): array
    {
        $content = $service->createContent($organisation, $admin, 'owner', [
            'title' => 'Body-specific CPD eligibility course', 'contentType' => 'course', 'providerName' => 'Architex',
            'durationMinutes' => 60, 'permissionStatus' => 'owned',
            'learningOutcomes' => ['Retain professional-body-specific accreditation evidence.'],
            'sourceText' => 'Rights-cleared source material for an accredited CPD eligibility test course.',
            'targetBodies' => [$professionalBody], 'humanConfirmed' => true,
        ]);
        $assessment = $service->createAssessmentDraft($organisation, $admin, 'owner', $content['id'], [
            'title' => 'Eligibility assessment', 'passPercent' => 70, 'attemptLimit' => 1,
            'questions' => [[
                'prompt' => 'Who confirms professional-body accreditation?', 'type' => 'single_choice', 'points' => 1,
                'options' => [['text' => 'The accrediting authority', 'correct' => true], ['text' => 'The learner', 'correct' => false]],
            ]], 'generatedWithAi' => false,
        ]);
        $service->reviewAssessment($organisation, $admin, 'owner', $assessment['id'], [
            'decision' => 'approved_for_accreditation', 'reason' => 'Human review complete.', 'humanConfirmed' => true,
        ]);
        $application = $service->createAccreditationApplication($organisation, $admin, 'owner', $assessment['id'], [
            'professionalBody' => $professionalBody, 'connectorMode' => 'document_export', 'providerName' => 'Accreditation Partner',
            'requestedCategory' => 'engineering_category_1_developmental_activity', 'requestedCredits' => '0.1', 'humanConfirmed' => true,
        ]);
        $application = $service->decideAccreditation($organisation, $admin, 'owner', $application['id'], [
            'decision' => 'accredited', 'approvedCategory' => 'engineering_category_1_developmental_activity', 'approvedCredits' => '0.1',
            'reference' => 'ECSA-ELIGIBILITY-1', 'validFrom' => '2026-01-01', 'validUntil' => '2027-12-31',
            'reason' => 'Accreditation evidence recorded.', 'humanConfirmed' => true,
        ]);
        return $service->publishCourse($organisation, $admin, 'owner', $application['id'], [
            'priceCents' => 0, 'currency' => 'ZAR', 'commercialModel' => 'free_funnel', 'humanConfirmed' => true,
        ]);
    }

    private function saveECSAProfile(CPDAssessmentService $service, string $organisation, string $learner): void
    {
        $service->saveProfile($organisation, $learner, 'member', [
            'fullName' => 'Engineer Learner', 'profession' => 'structural_engineer', 'professionalBody' => 'ECSA',
            'registrationNumber' => 'ECSA-ELIGIBILITY-1', 'registrationCategory' => 'Professional Engineer',
            'cycleStart' => '2026-01-01', 'cycleEnd' => '2030-12-31', 'participationRole' => 'learner',
            'popiaConsent' => true,
        ]);
    }

    private function saveSACAPProfile(CPDAssessmentService $service, string $organisation, string $learner): void
    {
        $service->saveProfile($organisation, $learner, 'member', [
            'fullName' => 'Architect Learner', 'profession' => 'Architect', 'professionalBody' => 'SACAP',
            'registrationNumber' => 'SACAP-ELIGIBILITY-1', 'registrationCategory' => 'Professional Architect',
            'cycleStart' => '2026-01-01', 'cycleEnd' => '2030-12-31', 'participationRole' => 'learner',
            'popiaConsent' => true,
        ]);
    }

    private function assessmentQuestionId(string $courseId): string
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(q.id)) FROM cpd_courses c JOIN cpd_questions q ON q.assessment_id=c.assessment_id WHERE c.id=UNHEX(:course) ORDER BY q.sequence_no LIMIT 1');
        $statement->execute(['course' => $courseId]);
        return (string) $statement->fetchColumn();
    }

    private function assessmentCorrectOptionId(string $courseId): string
    {
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(o.id)) FROM cpd_courses c JOIN cpd_questions q ON q.assessment_id=c.assessment_id JOIN cpd_question_options o ON o.question_id=q.id AND o.is_correct=TRUE WHERE c.id=UNHEX(:course) ORDER BY q.sequence_no,o.sequence_no LIMIT 1');
        $statement->execute(['course' => $courseId]);
        return (string) $statement->fetchColumn();
    }

    /** @return array{string,string,string} */
    private function tenant(): array
    {
        $organisation=Identifier::generate(); $admin=Identifier::generate(); $learner=Identifier::generate();
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'CPD Org',:slug,'active')")->execute(['id'=>$organisation,'slug'=>'cpd-'.substr($organisation,0,8)]);
        foreach ([[$admin,'admin-cpd@example.test','owner'],[$learner,'learner-cpd@example.test','member']] as [$user,$email,$role]) {
            $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$email]);
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user,'role'=>$role]);
        }
        return [$organisation,$admin,$learner];
    }
}

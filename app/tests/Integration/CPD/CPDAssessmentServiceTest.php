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
                ['prompt'=>'Explain the required human sign-off.', 'type'=>'text_review', 'points'=>1],
            ],
            'generatedWithAi'=>true,
        ]);
        self::assertTrue($assessment['generatedWithAi']);
        self::assertSame('human_review_required', $assessment['status']);

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

        $workspace=$service->workspace($organisation,$learner,'member');
        self::assertArrayNotHasKey('isCorrect',$workspace['assessments'][0]['questions'][0]['options'][0]);

        $attempt = $service->startAttempt($organisation, $learner, 'member', $course['id']);
        $result = $service->submitAttempt($organisation, $learner, 'member', $attempt['id'], [
            'answers'=>[
                ['questionId'=>$assessment['questions'][0]['id'], 'selectedOptionIds'=>[$assessment['questions'][0]['options'][0]['id']]],
                ['questionId'=>$assessment['questions'][1]['id'], 'text'=>'A registered professional checks and signs the output.'],
            ],
            'humanConfirmed'=>true,
        ]);
        self::assertSame('manual_review_required', $result['status']);
        $result = $service->reviewAttempt($organisation, $admin, 'owner', $result['id'], [
            'decision'=>'pass', 'reason'=>'Text response demonstrates the outcome.', 'humanConfirmed'=>true,
        ]);
        self::assertTrue($result['passed']);

        $certificate = $service->issueCertificate($organisation, $learner, 'member', $result['id'], 'https://example.test');
        self::assertSame('CPD-2026-001', $certificate['accreditationReference']);
        self::assertSame('0.1', $certificate['creditsAwarded']);
        self::assertSame($certificate['id'], $service->verifyCertificate($certificate['verificationCode'])['id']);
        $pdf=$service->certificatePdf($organisation,$learner,'member',$certificate['id']);
        self::assertStringStartsWith('%PDF-1.4',$pdf['content']);
        self::assertSame('application/pdf',$pdf['mimeType']);
        $service->captureReferral($organisation,$learner,['source'=>'cpd_central','campaign'=>'launch','eventType'=>'completed','courseId'=>$course['id'],'consented'=>true]);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM cpd_referral_events')->fetchColumn());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM cpd_professional_records')->fetchColumn());
        self::assertGreaterThanOrEqual(10, (int)$this->pdo->query('SELECT COUNT(*) FROM cpd_audit_events')->fetchColumn());
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

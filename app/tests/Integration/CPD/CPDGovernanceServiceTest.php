<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\CPD;

use Architex\Infrastructure\AI\WingmanModel;
use Architex\Platform\CPD\CPDAssessmentException;
use Architex\Platform\CPD\CPDAssessmentService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class CPDGovernanceServiceTest extends MariaDbTestCase
{
    public function testParticipationRolesDriveSeparateServerSideCapabilities(): void
    {
        [$organisation, $users] = $this->tenantWithRoles();
        $service = new CPDAssessmentService($this->pdo);

        foreach (['learner','content_creator','cpd_reviewer','accreditation_partner'] as $participationRole) {
            $this->saveLearnerProfile($service, $organisation, $users[$participationRole], $participationRole);
        }
        $service->saveProfile($organisation, $users['platform_admin'], 'owner', $this->profile('platform_admin'));

        foreach (['content_creator','cpd_reviewer','accreditation_partner'] as $participationRole) {
            $assigned = $service->assignParticipationRole(
                $organisation,
                $users['platform_admin'],
                'owner',
                $users[$participationRole],
                ['participationRole'=>$participationRole,'reason'=>'Verified CPD responsibility.','humanConfirmed'=>true],
            );
            self::assertSame($participationRole, $assigned['participationRole']);
        }

        $expected = [
            'learner' => ['canLearn'=>true,'canCreateContent'=>false,'canReviewAssessments'=>false,'canDecideAccreditation'=>false,'canManageCommercial'=>false],
            'content_creator' => ['canLearn'=>true,'canCreateContent'=>true,'canReviewAssessments'=>false,'canDecideAccreditation'=>false,'canManageCommercial'=>false],
            'cpd_reviewer' => ['canLearn'=>true,'canCreateContent'=>false,'canReviewAssessments'=>true,'canDecideAccreditation'=>false,'canManageCommercial'=>false],
            'accreditation_partner' => ['canLearn'=>true,'canCreateContent'=>false,'canReviewAssessments'=>false,'canDecideAccreditation'=>true,'canManageCommercial'=>false],
            'platform_admin' => ['canLearn'=>true,'canCreateContent'=>true,'canReviewAssessments'=>true,'canDecideAccreditation'=>true,'canManageCommercial'=>true],
        ];
        foreach ($expected as $participationRole => $capabilities) {
            $workspace = $service->workspace($organisation, $users[$participationRole], $participationRole === 'platform_admin' ? 'owner' : 'member');
            self::assertSame($participationRole, $workspace['participationRole']);
            foreach ($capabilities as $capability => $allowed) self::assertSame($allowed, $workspace['capabilities'][$capability], $participationRole . ':' . $capability);
        }

        $this->expectReason('cpd_content_create_forbidden', fn() => $service->createContent(
            $organisation,
            $users['learner'],
            'member',
            $this->content(),
        ));
        $content = $service->createContent($organisation, $users['content_creator'], 'member', $this->content());
        $assessment = $service->createAssessmentDraft($organisation, $users['content_creator'], 'member', $content['id'], $this->assessment());
        $this->expectReason('cpd_assessment_review_forbidden', fn() => $service->reviewAssessment(
            $organisation,
            $users['content_creator'],
            'member',
            $assessment['id'],
            ['decision'=>'approved_for_accreditation','reason'=>'Not a reviewer.','humanConfirmed'=>true],
        ));
        $reviewed = $service->reviewAssessment($organisation, $users['cpd_reviewer'], 'member', $assessment['id'], [
            'decision'=>'approved_for_accreditation','reason'=>'Every item checked against the source.','humanConfirmed'=>true,
        ]);
        self::assertSame('approved_for_accreditation', $reviewed['status']);

        $application = $service->createAccreditationApplication($organisation, $users['content_creator'], 'member', $assessment['id'], [
            'professionalBody'=>'SACAP','connectorMode'=>'document_export','requestedCategory'=>'category_1',
            'requestedCredits'=>'0.1','providerName'=>'CPD Partner','humanConfirmed'=>true,
        ]);
        $this->expectReason('cpd_accreditation_decision_forbidden', fn() => $service->decideAccreditation(
            $organisation,
            $users['cpd_reviewer'],
            'member',
            $application['id'],
            $this->accreditationDecision(),
        ));
        $approved = $service->decideAccreditation($organisation, $users['accreditation_partner'], 'member', $application['id'], $this->accreditationDecision());
        self::assertSame('accredited', $approved['status']);
    }

    public function testNemotronDraftIsValidatedVersionedAndKeepsAnswerKeysOutOfLearnerWorkspace(): void
    {
        [$organisation, $users] = $this->tenantWithRoles();
        $model = new class implements WingmanModel {
            public function complete(array $messages, array $tools = []): array
            {
                return [
                    'content'=>json_encode([
                        'title'=>'Responsible AI knowledge check',
                        'passPercent'=>75,
                        'timeLimitMinutes'=>25,
                        'riskFlags'=>['Confirm statutory wording with the source owner.'],
                        'questions'=>[
                            [
                                'type'=>'single_choice','prompt'=>'Who retains professional responsibility?','points'=>1,
                                'options'=>[['text'=>'Registered professional','correct'=>true],['text'=>'AI provider','correct'=>false]],
                                'explanation'=>'Professional judgment remains human-owned.','learningOutcome'=>'Preserve professional accountability',
                                'sourceReference'=>'Transcript section 2','difficulty'=>'medium','modelAnswer'=>'Registered professional',
                            ],
                            [
                                'type'=>'text_review','prompt'=>'Explain the required review gate.','points'=>2,'options'=>[],
                                'explanation'=>'A human checks the exact output.','learningOutcome'=>'Apply review gates',
                                'sourceReference'=>'Transcript section 3','difficulty'=>'hard','modelAnswer'=>'Human review before formal action.',
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                    'toolCalls'=>[],'requestId'=>'nvidia-request-22','model'=>'nvidia/nemotron-3.5-lightning-30b-a3b',
                    'finishReason'=>'stop','usage'=>['input'=>321,'output'=>144],
                ];
            }
        };
        $service = new CPDAssessmentService($this->pdo, $model);
        $this->saveLearnerProfile($service, $organisation, $users['learner'], 'learner');
        $service->saveProfile($organisation, $users['platform_admin'], 'owner', $this->profile('platform_admin'));
        $this->saveLearnerProfile($service, $organisation, $users['content_creator'], 'content_creator');
        $service->assignParticipationRole($organisation, $users['platform_admin'], 'owner', $users['content_creator'], [
            'participationRole'=>'content_creator','reason'=>'Approved lecturer.','humanConfirmed'=>true,
        ]);
        $content = $service->createContent($organisation, $users['content_creator'], 'member', $this->content());

        $draft = $service->generateAssessmentDraft($organisation, $users['content_creator'], 'member', $content['id'], [
            'questionCount'=>2,'attemptLimit'=>3,'humanConfirmed'=>true,
        ]);
        self::assertTrue($draft['generatedWithAi']);
        self::assertSame('nvidia/nemotron-3.5-lightning-30b-a3b', $draft['generationModel']);
        self::assertSame('nvidia-request-22', $draft['generationRequestId']);
        self::assertSame(25, $draft['timeLimitMinutes']);
        self::assertSame('Transcript section 2', $draft['questions'][0]['sourceReference']);
        self::assertSame('Professional judgment remains human-owned.', $draft['questions'][0]['rationale']);
        self::assertSame(1, $draft['version']);

        $revision = $service->createAssessmentDraft($organisation, $users['content_creator'], 'member', $content['id'], $this->assessment());
        self::assertSame(2, $revision['version']);
        self::assertSame($draft['id'], $revision['supersedesAssessmentId']);

        $learnerWorkspace = $service->workspace($organisation, $users['learner'], 'member');
        $question = $learnerWorkspace['assessments'][0]['questions'][0];
        self::assertArrayNotHasKey('modelAnswer', $question);
        self::assertArrayNotHasKey('rationale', $question);
        self::assertArrayNotHasKey('isCorrect', $question['options'][0]);
    }

    public function testAiGenerationFailsClosedWithoutAConfiguredModel(): void
    {
        [$organisation, $users] = $this->tenantWithRoles();
        $service = new CPDAssessmentService($this->pdo);
        $service->saveProfile($organisation, $users['platform_admin'], 'owner', $this->profile('platform_admin'));
        $content = $service->createContent($organisation, $users['platform_admin'], 'owner', $this->content());
        $this->expectReason('cpd_ai_generation_integration_required', fn() => $service->generateAssessmentDraft(
            $organisation,
            $users['platform_admin'],
            'owner',
            $content['id'],
            ['questionCount'=>5,'attemptLimit'=>3,'humanConfirmed'=>true],
        ));
    }

    /** @return array{string,array<string,string>} */
    private function tenantWithRoles(): array
    {
        $organisation=Identifier::generate();
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'CPD Governance Org',:slug,'active')")->execute(['id'=>$organisation,'slug'=>'cpd-governance-'.substr($organisation,0,8)]);
        $users=[];
        foreach (['learner','content_creator','cpd_reviewer','accreditation_partner','platform_admin'] as $participationRole) {
            $user=Identifier::generate();$users[$participationRole]=$user;$membershipRole=$participationRole==='platform_admin'?'owner':'member';
            $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>$participationRole.'-'.$user.'@example.test']);
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$user,'role'=>$membershipRole]);
        }
        return [$organisation,$users];
    }

    private function saveLearnerProfile(CPDAssessmentService $service,string $organisation,string $user,string $_desired): void
    {
        $service->saveProfile($organisation,$user,'member',$this->profile('learner'));
    }

    /** @return array<string,mixed> */
    private function profile(string $participationRole): array
    {
        return ['fullName'=>'CPD Participant','profession'=>'Architect','professionalBody'=>'SACAP','registrationNumber'=>'SACAP-22','registrationCategory'=>'PrArch','cycleStart'=>'2026-01-01','cycleEnd'=>'2030-12-31','participationRole'=>$participationRole,'popiaConsent'=>true];
    }

    /** @return array<string,mixed> */
    private function content(): array
    {
        return ['title'=>'Responsible AI practice','contentType'=>'webinar','providerName'=>'Architex','durationMinutes'=>60,'permissionStatus'=>'owned','learningOutcomes'=>['Preserve professional accountability','Apply review gates'],'sourceText'=>'Section 2 states that registered professionals retain responsibility. Section 3 requires human review before formal action.','targetBodies'=>['SACAP'],'humanConfirmed'=>true];
    }

    /** @return array<string,mixed> */
    private function assessment(): array
    {
        return ['title'=>'Human edited knowledge check','passPercent'=>70,'attemptLimit'=>3,'timeLimitMinutes'=>20,'questions'=>[['prompt'=>'Who signs off?','type'=>'single_choice','points'=>1,'explanation'=>'The professional signs off.','learningOutcome'=>'Preserve professional accountability','sourceReference'=>'Section 2','difficulty'=>'easy','options'=>[['text'=>'Professional','correct'=>true],['text'=>'Model','correct'=>false]]]],'generatedWithAi'=>false];
    }

    /** @return array<string,mixed> */
    private function accreditationDecision(): array
    {
        return ['decision'=>'accredited','approvedCategory'=>'category_1','approvedCredits'=>'0.1','reference'=>'CPD-ROLE-22','validFrom'=>'2026-01-01','validUntil'=>'2027-12-31','reason'=>'Accreditation partner checked the evidence.','humanConfirmed'=>true];
    }

    private function expectReason(string $reason, callable $callback): void
    {
        try {$callback();self::fail('Expected '.$reason);} catch (CPDAssessmentException $exception) {self::assertSame($reason,$exception->reason);}
    }
}

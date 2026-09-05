<?php

declare(strict_types=1);

namespace Architex\Platform\CPD;

use Architex\Infrastructure\AI\NvidiaNemotronException;
use Architex\Infrastructure\AI\WingmanModel;
use Architex\Platform\Identity\Identifier;
use DateTimeImmutable;
use JsonException;
use PDO;
use Throwable;

final class CPDAssessmentService
{
    private const BODIES = ['SACAP','ECSA','SACQSP','SACPCMP','SACPLAN','SACLAP','SAGC','SACPVP'];
    private const GOVERNORS = ['owner','admin'];

    public function __construct(private readonly PDO $pdo, private readonly ?WingmanModel $assessmentModel = null) {}

    /** @return list<array<string,mixed>> */
    public function professionalBodyRules(): array
    {
        $rows = $this->pdo->query('SELECT * FROM cpd_professional_body_rules ORDER BY body_code')->fetchAll();
        return array_map(fn(array $row): array => $this->row($row, ['category_rules']), $rows);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function saveProfile(string $organisationId, string $userId, string $role, array $input): array
    {
        $this->membership($organisationId, $userId, $role);
        if (($input['popiaConsent'] ?? false) !== true) throw new CPDAssessmentException('cpd_profile_consent_required');
        $fullName=$this->text($input['fullName']??null,220,'cpd_profile_name_required');
        $profession=$this->text($input['profession']??null,160,'cpd_profile_profession_required');
        $body=$this->choice($input['professionalBody']??null,self::BODIES,'cpd_professional_body_invalid');
        $number=$this->text($input['registrationNumber']??null,180,'cpd_registration_number_required');
        $category=$this->text($input['registrationCategory']??null,120,'cpd_registration_category_required');
        $start=$this->date($input['cycleStart']??null,'cpd_cycle_start_invalid');
        $end=$this->date($input['cycleEnd']??null,'cpd_cycle_end_invalid');
        if ($end < $start) throw new CPDAssessmentException('cpd_cycle_dates_invalid');
        $participation=$this->choice($input['participationRole']??'learner',['learner','content_creator','cpd_reviewer','accreditation_partner','platform_admin'],'cpd_participation_role_invalid');
        $existing=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,participation_role FROM cpd_professional_profiles WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)');
        $existing->execute(['o'=>$organisationId,'u'=>$userId]);$existingRow=$existing->fetch();$id=is_array($existingRow)?$existingRow['id']:Identifier::generate();
        if(!in_array($role,self::GOVERNORS,true)&&$participation!=='learner'&&(!is_array($existingRow)||$existingRow['participation_role']!==$participation))throw new CPDAssessmentException('cpd_profile_role_forbidden');
        $this->pdo->prepare("INSERT INTO cpd_professional_profiles(id,organisation_id,user_id,full_name,profession,professional_body,registration_number,registration_category,cycle_start,cycle_end,participation_role,popia_consent_at,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:name,:profession,:body,:number,:category,:start,:end,:participation,UTC_TIMESTAMP(6),'active') ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),profession=VALUES(profession),professional_body=VALUES(professional_body),registration_number=VALUES(registration_number),registration_category=VALUES(registration_category),cycle_start=VALUES(cycle_start),cycle_end=VALUES(cycle_end),participation_role=VALUES(participation_role),popia_consent_at=VALUES(popia_consent_at),status='active'")->execute(['id'=>$id,'o'=>$organisationId,'u'=>$userId,'name'=>$fullName,'profession'=>$profession,'body'=>$body,'number'=>$number,'category'=>$category,'start'=>$start,'end'=>$end,'participation'=>$participation]);
        $this->audit($organisationId,$userId,'cpd.profile_saved','professional_profile',$id,['professionalBody'=>$body,'participationRole'=>$participation]);
        return $this->profile($organisationId,$userId);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function assignParticipationRole(string $organisationId,string $actorUserId,string $actorRole,string $targetUserId,array $input):array
    {
        $this->requireCapability($organisationId,$actorUserId,$actorRole,'roles.assign','cpd_role_assignment_forbidden');
        $this->confirmed($input);$targetUserId=$this->id($targetUserId,'cpd_profile_not_found');
        $participation=$this->choice($input['participationRole']??null,['learner','content_creator','cpd_reviewer','accreditation_partner','platform_admin'],'cpd_participation_role_invalid');
        $reason=$this->text($input['reason']??null,2000,'cpd_role_assignment_reason_required');
        $profile=$this->profileRow($organisationId,$targetUserId);
        $this->pdo->prepare('UPDATE cpd_professional_profiles SET participation_role=:participation WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)')->execute(['participation'=>$participation,'o'=>$organisationId,'u'=>$targetUserId]);
        $this->audit($organisationId,$actorUserId,'cpd.participation_role_assigned','professional_profile',$profile['id'],['targetUserId'=>$targetUserId,'from'=>$profile['participation_role'],'to'=>$participation,'reason'=>$reason]);
        return $this->profile($organisationId,$targetUserId);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createContent(string $organisationId,string $userId,string $role,array $input):array
    {
        $this->requireCapability($organisationId,$userId,$role,'content.create','cpd_content_create_forbidden');$this->confirmed($input);
        $title=$this->text($input['title']??null,220,'cpd_content_title_required');
        $type=$this->choice($input['contentType']??null,['webinar','article','course','platform_training','other'],'cpd_content_type_invalid');
        $provider=$this->text($input['providerName']??null,220,'cpd_provider_name_required');
        $duration=$this->integer($input['durationMinutes']??null,1,525600,'cpd_duration_invalid');
        $permission=$this->choice($input['permissionStatus']??null,['owned','licensed','permission_required','rejected'],'cpd_permission_status_invalid');
        $outcomes=$this->strings($input['learningOutcomes']??null,30,500,'cpd_learning_outcomes_invalid');
        $source=$this->text($input['sourceText']??null,5000000,'cpd_source_text_required');
        $bodies=$this->bodyList($input['targetBodies']??null);
        if(!in_array($permission,['owned','licensed'],true))throw new CPDAssessmentException('cpd_content_permission_required');
        $id=Identifier::generate();
        $this->pdo->prepare("INSERT INTO cpd_content_items(id,organisation_id,title,content_type,provider_name,duration_minutes,permission_status,learning_outcomes,source_text,target_bodies,status,created_by,reviewed_by,review_reason,reviewed_at) VALUES(UNHEX(:id),UNHEX(:o),:title,:type,:provider,:duration,:permission,:outcomes,:source,:bodies,'approved',UNHEX(:creator),UNHEX(:reviewer),'Creator confirmed rights and source content.',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'o'=>$organisationId,'title'=>$title,'type'=>$type,'provider'=>$provider,'duration'=>$duration,'permission'=>$permission,'outcomes'=>$this->json($outcomes),'source'=>$source,'bodies'=>$this->json($bodies),'creator'=>$userId,'reviewer'=>$userId]);
        $this->audit($organisationId,$userId,'cpd.content_created','content_item',$id,['permissionStatus'=>$permission,'targetBodies'=>$bodies]);
        return $this->content($organisationId,$id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createAssessmentDraft(string $organisationId,string $userId,string $role,string $contentId,array $input):array
    {
        $this->requireCapability($organisationId,$userId,$role,'assessment.draft','cpd_assessment_create_forbidden');$content=$this->contentRow($organisationId,$contentId);
        if($content['status']!=='approved')throw new CPDAssessmentException('cpd_content_not_approved');
        $title=$this->text($input['title']??null,220,'cpd_assessment_title_required');
        $pass=$this->decimal($input['passPercent']??null,1,100,'cpd_pass_percent_invalid');
        $limit=$this->integer($input['attemptLimit']??null,1,10,'cpd_attempt_limit_invalid');
        $timeLimit=$this->optionalInteger($input['timeLimitMinutes']??null,1,1440,'cpd_time_limit_invalid');
        $questions=$input['questions']??null;if(!is_array($questions)||$questions===[]||count($questions)>200)throw new CPDAssessmentException('cpd_questions_required');
        return $this->storeAssessmentDraft($organisationId,$userId,$contentId,$title,$pass,$limit,$timeLimit,$questions,false,[],null,null,null);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function generateAssessmentDraft(string $organisationId,string $userId,string $role,string $contentId,array $input):array
    {
        $this->requireCapability($organisationId,$userId,$role,'assessment.draft','cpd_assessment_create_forbidden');$this->confirmed($input);
        if($this->assessmentModel===null)throw new CPDAssessmentException('cpd_ai_generation_integration_required');
        $content=$this->contentRow($organisationId,$contentId);if($content['status']!=='approved')throw new CPDAssessmentException('cpd_content_not_approved');
        $count=$this->integer($input['questionCount']??null,1,50,'cpd_question_count_invalid');$limit=$this->integer($input['attemptLimit']??null,1,10,'cpd_attempt_limit_invalid');
        $source=mb_substr((string)$content['source_text'],0,16000);$outcomes=$this->decode($content['learning_outcomes']);
        $system='You draft CPD assessments from rights-cleared source text. Return only one JSON object. Never accredit content or invent source facts. Every question must include type, prompt, points, options, explanation, learningOutcome, sourceReference, difficulty and modelAnswer. Choice options must include text and correct boolean. Text-review questions use an empty options array.';
        $prompt=$this->json(['task'=>'Draft a human-review-required CPD assessment','questionCount'=>$count,'allowedTypes'=>['single_choice','multiple_choice','scenario','text_review'],'contentTitle'=>$content['title'],'learningOutcomes'=>$outcomes,'sourceText'=>$source,'requiredOutput'=>['title'=>'string','passPercent'=>'integer 1-100','timeLimitMinutes'=>'integer 1-1440 or null','riskFlags'=>['string'],'questions'=>[['type'=>'allowed type','prompt'=>'string','points'=>'number','options'=>[['text'=>'string','correct'=>'boolean']],'explanation'=>'string','learningOutcome'=>'string','sourceReference'=>'string','difficulty'=>'easy|medium|hard','modelAnswer'=>'string']]]]);
        try{$completion=$this->assessmentModel->complete([['role'=>'system','content'=>$system],['role'=>'user','content'=>$prompt]]);}catch(NvidiaNemotronException){throw new CPDAssessmentException('cpd_ai_generation_integration_required');}
        $generated=$this->decodeModelJson($completion['content']??null);$questions=$generated['questions']??null;if(!is_array($questions)||count($questions)!==$count)throw new CPDAssessmentException('cpd_ai_generation_invalid');
        $title=$this->text($generated['title']??null,220,'cpd_ai_generation_invalid');$pass=$this->decimal($generated['passPercent']??null,1,100,'cpd_ai_generation_invalid');$timeLimit=$this->optionalInteger($generated['timeLimitMinutes']??null,1,1440,'cpd_ai_generation_invalid');
        $riskFlags=$generated['riskFlags']??[];if(!is_array($riskFlags))throw new CPDAssessmentException('cpd_ai_generation_invalid');$riskFlags=$riskFlags===[]?[]:$this->strings($riskFlags,30,500,'cpd_ai_generation_invalid');
        $model=$this->text($completion['model']??null,180,'cpd_ai_generation_invalid');$requestId=is_string($completion['requestId']??null)&&trim($completion['requestId'])!==''?mb_substr(trim($completion['requestId']),0,180):null;$usage=is_array($completion['usage']??null)?$completion['usage']:[];
        return $this->storeAssessmentDraft($organisationId,$userId,$contentId,$title,$pass,$limit,$timeLimit,$questions,true,$riskFlags,$model,$requestId,$usage);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function reviewAssessment(string$o,string$u,string$role,string$id,array$input):array
    {
        $this->requireCapability($o,$u,$role,'assessment.review','cpd_assessment_review_forbidden');$this->confirmed($input);$assessment=$this->assessmentRow($o,$id);
        if($assessment['status']!=='human_review_required')throw new CPDAssessmentException('cpd_assessment_review_state_invalid');
        $decision=$this->choice($input['decision']??null,['approved_for_accreditation','changes_required'],'cpd_assessment_decision_invalid');
        $reason=$this->text($input['reason']??null,2000,'cpd_review_reason_required');
        $this->pdo->prepare('UPDATE cpd_assessments SET status=:decision,reviewed_by=UNHEX(:u),review_reason=:reason,reviewed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)')->execute(['decision'=>$decision,'u'=>$u,'reason'=>$reason,'id'=>$id,'o'=>$o]);
        $this->audit($o,$u,'cpd.assessment_reviewed','assessment',$id,['decision'=>$decision,'reason'=>$reason]);return$this->assessment($o,$id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createAccreditationApplication(string$o,string$u,string$role,string$assessmentId,array$input):array
    {
        $this->requireCapability($o,$u,$role,'accreditation.submit','cpd_accreditation_submit_forbidden');$this->confirmed($input);$assessment=$this->assessmentRow($o,$assessmentId);
        if($assessment['status']!=='approved_for_accreditation')throw new CPDAssessmentException('cpd_assessment_not_reviewed');
        $body=$this->choice($input['professionalBody']??null,self::BODIES,'cpd_professional_body_invalid');
        $mode=$this->choice($input['connectorMode']??null,['api','portal','document_export','email','manual'],'cpd_connector_mode_invalid');
        if($mode==='api')throw new CPDAssessmentException('cpd_accreditation_integration_required');
        $category=$this->text($input['requestedCategory']??null,160,'cpd_category_required');$credits=$this->decimal($input['requestedCredits']??null,0,100000,'cpd_credits_invalid');
        $provider=$this->text($input['providerName']??null,220,'cpd_provider_name_required');$rule=$this->bodyRule($body);$id=Identifier::generate();
        $this->pdo->prepare("INSERT INTO cpd_accreditation_applications(id,organisation_id,assessment_id,professional_body,connector_mode,provider_name,requested_category,requested_credits,status,rule_snapshot,submitted_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:assessment),:body,:mode,:provider,:category,:credits,'submitted',:snapshot,UNHEX(:u))")->execute(['id'=>$id,'o'=>$o,'assessment'=>$assessmentId,'body'=>$body,'mode'=>$mode,'provider'=>$provider,'category'=>$category,'credits'=>$credits,'snapshot'=>$this->json($rule),'u'=>$u]);
        $this->audit($o,$u,'cpd.accreditation_submitted','accreditation_application',$id,['professionalBody'=>$body,'connectorMode'=>$mode]);return$this->accreditation($o,$id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function decideAccreditation(string$o,string$u,string$role,string$id,array$input):array
    {
        $this->requireCapability($o,$u,$role,'accreditation.decide','cpd_accreditation_decision_forbidden');$this->confirmed($input);$row=$this->accreditationRow($o,$id);
        if(!in_array($row['status'],['submitted','under_review'],true))throw new CPDAssessmentException('cpd_accreditation_state_invalid');
        $decision=$this->choice($input['decision']??null,['accredited','changes_required','rejected'],'cpd_accreditation_decision_invalid');$reason=$this->text($input['reason']??null,2000,'cpd_review_reason_required');
        $approvedCategory=$decision==='accredited'?$this->text($input['approvedCategory']??null,160,'cpd_approved_category_required'):null;
        $approvedCredits=$decision==='accredited'?$this->decimal($input['approvedCredits']??null,0,100000,'cpd_approved_credits_invalid'):null;
        $reference=$decision==='accredited'?$this->text($input['reference']??null,180,'cpd_accreditation_reference_required'):null;
        $from=$decision==='accredited'?$this->date($input['validFrom']??null,'cpd_valid_from_invalid'):null;$until=$decision==='accredited'?$this->date($input['validUntil']??null,'cpd_valid_until_invalid'):null;
        if($from!==null&&$until!==null&&$until<$from)throw new CPDAssessmentException('cpd_accreditation_dates_invalid');
        $this->pdo->prepare('UPDATE cpd_accreditation_applications SET status=:status,approved_category=:category,approved_credits=:credits,accreditation_reference=:reference,valid_from=:validFrom,valid_until=:validUntil,decided_by=UNHEX(:u),decision_reason=:reason,decided_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)')->execute(['status'=>$decision,'category'=>$approvedCategory,'credits'=>$approvedCredits,'reference'=>$reference,'validFrom'=>$from,'validUntil'=>$until,'u'=>$u,'reason'=>$reason,'id'=>$id,'o'=>$o]);
        $this->audit($o,$u,'cpd.accreditation_decided','accreditation_application',$id,['decision'=>$decision,'reference'=>$reference]);return$this->accreditation($o,$id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function publishCourse(string$o,string$u,string$role,string$applicationId,array$input):array
    {
        $this->requireCapability($o,$u,$role,'course.publish','cpd_course_publish_forbidden');$this->confirmed($input);$application=$this->accreditationRow($o,$applicationId);
        if($application['status']!=='accredited'||$application['approved_credits']===null||$application['accreditation_reference']===null)throw new CPDAssessmentException('cpd_accreditation_required');
        $existing=$this->pdo->prepare('SELECT 1 FROM cpd_courses WHERE accreditation_application_id=UNHEX(:id)');$existing->execute(['id'=>$applicationId]);if($existing->fetchColumn()!==false)throw new CPDAssessmentException('cpd_course_already_published');
        $price=$this->integer($input['priceCents']??null,0,PHP_INT_MAX,'cpd_price_invalid');$currency=$this->choice($input['currency']??'ZAR',['ZAR'],'cpd_currency_invalid');
        $model=$this->choice($input['commercialModel']??null,['free_funnel','partner_bundle','webinar_addon','article_assessment','dedicated_course'],'cpd_commercial_model_invalid');
        $assessment=$this->assessmentRow($o,bin2hex($application['assessment_id']));$content=$this->contentRow($o,bin2hex($assessment['content_item_id']));$id=Identifier::generate();
        $this->pdo->prepare("INSERT INTO cpd_courses(id,organisation_id,accreditation_application_id,assessment_id,title,provider_name,professional_body,approved_category,approved_credits,accreditation_reference,valid_from,valid_until,price_cents,currency,commercial_model,status,published_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:application),UNHEX(:assessment),:title,:provider,:body,:category,:credits,:reference,:validFrom,:validUntil,:price,:currency,:model,'live',UNHEX(:u))")->execute(['id'=>$id,'o'=>$o,'application'=>$applicationId,'assessment'=>bin2hex($application['assessment_id']),'title'=>$content['title'],'provider'=>$application['provider_name'],'body'=>$application['professional_body'],'category'=>$application['approved_category'],'credits'=>$application['approved_credits'],'reference'=>$application['accreditation_reference'],'validFrom'=>$application['valid_from'],'validUntil'=>$application['valid_until'],'price'=>$price,'currency'=>$currency,'model'=>$model,'u'=>$u]);
        $this->audit($o,$u,'cpd.course_published','course',$id,['accreditationReference'=>$application['accreditation_reference'],'priceCents'=>$price]);return$this->course($o,$id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createManualEntitlement(string$o,string$u,string$role,string$courseId,array$input):array
    {
        $this->membership($o,$u,$role);$this->confirmed($input);$course=$this->courseRow($o,$courseId);$this->assertCourseLive($course);
        $method=$this->choice($input['paymentMethod']??null,['manual_eft'],'cpd_manual_payment_method_invalid');$reference=$this->text($input['paymentReference']??null,220,'cpd_payment_reference_required');$id=Identifier::generate();
        try{$this->pdo->prepare("INSERT INTO cpd_entitlements(id,organisation_id,course_id,learner_user_id,payment_method,payment_reference,amount_cents,currency,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:course),UNHEX(:u),:method,:reference,:amount,:currency,'review_required')")->execute(['id'=>$id,'o'=>$o,'course'=>$courseId,'u'=>$u,'method'=>$method,'reference'=>$reference,'amount'=>$course['price_cents'],'currency'=>$course['currency']]);}catch(Throwable){throw new CPDAssessmentException('cpd_entitlement_already_exists');}
        $this->audit($o,$u,'cpd.entitlement_requested','entitlement',$id,['paymentMethod'=>$method,'paymentReference'=>$reference]);return$this->entitlement($o,$id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createFreeEntitlement(string$o,string$u,string$role,string$courseId):array
    {
        $this->membership($o,$u,$role);
        return $this->transaction(function()use($o,$u,$courseId):array {
            $lock=$this->pdo->prepare('SELECT id FROM cpd_courses WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id) FOR UPDATE');
            $lock->execute(['o'=>$o,'id'=>$courseId]);
            $course=$this->courseRow($o,$courseId);
            $this->assertCourseLive($course);
            if((int)$course['price_cents']!==0)throw new CPDAssessmentException('cpd_payment_required');
            $existing=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,payment_method,status FROM cpd_entitlements WHERE organisation_id=UNHEX(:o) AND course_id=UNHEX(:course) AND learner_user_id=UNHEX(:u)');
            $existing->execute(['o'=>$o,'course'=>$courseId,'u'=>$u]);
            $row=$existing->fetch();
            if(is_array($row)){
                if($row['payment_method']==='free'&&$row['status']==='active')return $this->entitlement($o,$row['id']);
                throw new CPDAssessmentException('cpd_entitlement_already_exists');
            }
            $id=Identifier::generate();
            $this->pdo->prepare("INSERT INTO cpd_entitlements(id,organisation_id,course_id,learner_user_id,payment_method,amount_cents,currency,status,activated_at) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:course),UNHEX(:u),'free',0,:currency,'active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'o'=>$o,'course'=>$courseId,'u'=>$u,'currency'=>$course['currency']]);
            $this->recordPurchase($o,$id,[]);
            $this->audit($o,$u,'cpd.entitlement_activated','entitlement',$id,['paymentMethod'=>'free','amountCents'=>0]);
            return $this->entitlement($o,$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function reviewManualEntitlement(string$o,string$u,string$role,string$id,array$input):array
    {
        $this->requireCapability($o,$u,$role,'commercial.manage','cpd_commercial_manage_forbidden');$this->confirmed($input);$row=$this->entitlementRow($o,$id);if($row['status']!=='review_required'||$row['payment_method']!=='manual_eft')throw new CPDAssessmentException('cpd_entitlement_review_state_invalid');
        $decision=$this->choice($input['decision']??null,['grant','decline'],'cpd_entitlement_decision_invalid');$reason=$this->text($input['reason']??null,2000,'cpd_review_reason_required');$status=$decision==='grant'?'active':'declined';
        return$this->transaction(function()use($o,$u,$id,$status,$decision,$reason,$row):array{$this->pdo->prepare("UPDATE cpd_entitlements SET status=:status,reviewed_by=UNHEX(:u),review_reason=:reason,activated_at=CASE WHEN :active_status='active' THEN UTC_TIMESTAMP(6) ELSE NULL END WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)")->execute(['status'=>$status,'u'=>$u,'reason'=>$reason,'active_status'=>$status,'id'=>$id,'o'=>$o]);if($status==='active')$this->recordPurchase($o,$id,$row);$this->audit($o,$u,'cpd.entitlement_reviewed','entitlement',$id,['decision'=>$decision,'reason'=>$reason]);return$this->entitlement($o,$id);});
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function configureCommercialSettings(string$o,string$u,string$role,array$input):array
    {
        $this->requireCapability($o,$u,$role,'commercial.manage','cpd_commercial_manage_forbidden');$this->confirmed($input);$percent=$this->decimal($input['platformFeePercent']??null,0,100,'cpd_platform_fee_invalid');$basis=(int)round((float)$percent*100);$minimum=$this->integer($input['minimumPlatformFeeCents']??null,0,PHP_INT_MAX,'cpd_minimum_fee_invalid');$payout=($input['payoutEnabled']??null);if(!is_bool($payout))throw new CPDAssessmentException('cpd_payout_setting_invalid');$providers=$input['configuredProviders']??null;if(!is_array($providers))throw new CPDAssessmentException('cpd_provider_settings_invalid');$allowed=[];foreach($providers as$provider){$provider=$this->choice($provider,['manual_eft','payfast','yoco'],'cpd_provider_settings_invalid');if($provider!=='manual_eft')throw new CPDAssessmentException('cpd_payment_integration_required');$allowed[]=$provider;}$allowed=array_values(array_unique($allowed));
        $this->pdo->prepare('INSERT INTO cpd_commercial_settings(organisation_id,platform_fee_basis_points,minimum_platform_fee_cents,payout_enabled,configured_providers,updated_by) VALUES(UNHEX(:o),:basis,:minimum,:payout,:providers,UNHEX(:u)) ON DUPLICATE KEY UPDATE platform_fee_basis_points=VALUES(platform_fee_basis_points),minimum_platform_fee_cents=VALUES(minimum_platform_fee_cents),payout_enabled=VALUES(payout_enabled),configured_providers=VALUES(configured_providers),updated_by=VALUES(updated_by)')->execute(['o'=>$o,'basis'=>$basis,'minimum'=>$minimum,'payout'=>$payout?1:0,'providers'=>$this->json($allowed),'u'=>$u]);$this->audit($o,$u,'cpd.commercial_settings_updated','commercial_settings',$o,['platformFeeBasisPoints'=>$basis,'minimumPlatformFeeCents'=>$minimum,'payoutEnabled'=>$payout,'configuredProviders'=>$allowed]);return$this->commercialSettings($o);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function captureReferral(string$o,string$u,array$input):array
    {
        $this->membershipRole($o,$u);$source=$this->text($input['source']??null,120,'cpd_referral_source_required');$campaign=is_string($input['campaign']??null)&&trim($input['campaign'])!==''?$this->text($input['campaign'],160,'cpd_referral_campaign_invalid'):null;$event=$this->choice($input['eventType']??null,['landing','catalogue_view','course_view','checkout_started','enrolled','completed'],'cpd_referral_event_invalid');$course=isset($input['courseId'])?$this->id($input['courseId'],'cpd_course_not_found'):null;if($course!==null)$this->courseRow($o,$course);$consented=($input['consented']??false)===true;$id=Identifier::generate();$this->pdo->prepare('INSERT INTO cpd_referral_events(id,organisation_id,user_id,referral_source,campaign,event_type,course_id,consented) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:source,:campaign,:event,UNHEX(:course),:consented)')->execute(['id'=>$id,'o'=>$o,'u'=>$u,'source'=>$source,'campaign'=>$campaign,'event'=>$event,'course'=>$course,'consented'=>$consented?1:0]);$this->audit($o,$u,'cpd.referral_captured','referral_event',$id,['source'=>$source,'campaign'=>$campaign,'eventType'=>$event,'consented'=>$consented]);return['id'=>$id,'source'=>$source,'campaign'=>$campaign,'eventType'=>$event,'consented'=>$consented];
    }

    /** @return array<string,mixed> */
    public function startAttempt(string$o,string$u,string$role,string$courseId):array
    {
        $this->membership($o,$u,$role);$course=$this->courseRow($o,$courseId);$this->assertCourseLive($course);
        $ent=$this->pdo->prepare("SELECT *,LOWER(HEX(id)) id FROM cpd_entitlements WHERE organisation_id=UNHEX(:o) AND course_id=UNHEX(:course) AND learner_user_id=UNHEX(:u) AND status='active'");$ent->execute(['o'=>$o,'course'=>$courseId,'u'=>$u]);$entitlement=$ent->fetch();if(!is_array($entitlement))throw new CPDAssessmentException('cpd_entitlement_required');
        $assessment=$this->assessmentRow($o,bin2hex($course['assessment_id']));$open=$this->pdo->prepare("SELECT a.*,LOWER(HEX(a.id)) id,t.expires_at,t.last_activity_at FROM cpd_attempts a LEFT JOIN cpd_attempt_timing t ON t.attempt_id=a.id WHERE a.course_id=UNHEX(:course) AND a.learner_user_id=UNHEX(:u) AND a.status='in_progress' ORDER BY a.started_at DESC LIMIT 1");$open->execute(['course'=>$courseId,'u'=>$u]);$existing=$open->fetch();if(is_array($existing)){if($existing['expires_at']===null||new DateTimeImmutable((string)$existing['expires_at'])>new DateTimeImmutable('now'))return$this->attempt($o,$existing['id']);$this->pdo->prepare("UPDATE cpd_attempts SET status='expired' WHERE id=UNHEX(:id)")->execute(['id'=>$existing['id']]);$this->pdo->prepare('UPDATE cpd_attempt_timing SET last_activity_at=UTC_TIMESTAMP(6) WHERE attempt_id=UNHEX(:id)')->execute(['id'=>$existing['id']]);$this->audit($o,$u,'cpd.attempt_expired','attempt',$existing['id'],['courseId'=>$courseId]);}
        $count=$this->pdo->prepare('SELECT COUNT(*) FROM cpd_attempts WHERE course_id=UNHEX(:course) AND learner_user_id=UNHEX(:u)');$count->execute(['course'=>$courseId,'u'=>$u]);$number=(int)$count->fetchColumn()+1;if($number>(int)$assessment['attempt_limit'])throw new CPDAssessmentException('cpd_attempt_limit_reached');
        $expiresAt=$assessment['time_limit_minutes']===null?null:(new DateTimeImmutable('now'))->modify('+'.(int)$assessment['time_limit_minutes'].' minutes')->format('Y-m-d H:i:s.u');
        $id=Identifier::generate();$this->pdo->prepare("INSERT INTO cpd_attempts(id,organisation_id,course_id,assessment_id,learner_user_id,entitlement_id,attempt_number,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:course),UNHEX(:assessment),UNHEX(:u),UNHEX(:entitlement),:number,'in_progress')")->execute(['id'=>$id,'o'=>$o,'course'=>$courseId,'assessment'=>bin2hex($course['assessment_id']),'u'=>$u,'entitlement'=>$entitlement['id'],'number'=>$number]);$this->pdo->prepare('INSERT INTO cpd_attempt_timing(attempt_id,organisation_id,expires_at) VALUES(UNHEX(:id),UNHEX(:o),:expires)')->execute(['id'=>$id,'o'=>$o,'expires'=>$expiresAt]);
        $this->audit($o,$u,'cpd.attempt_started','attempt',$id,['courseId'=>$courseId,'attemptNumber'=>$number]);return$this->attempt($o,$id);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function submitAttempt(string$o,string$u,string$role,string$id,array$input):array
    {
        $this->membership($o,$u,$role);$this->confirmed($input);$attempt=$this->attemptRow($o,$id);if(bin2hex($attempt['learner_user_id'])!==$u)throw new CPDAssessmentException('cpd_attempt_forbidden');if($attempt['status']!=='in_progress')throw new CPDAssessmentException('cpd_attempt_immutable');if($attempt['expires_at']!==null&&new DateTimeImmutable((string)$attempt['expires_at'])<=new DateTimeImmutable('now')){$this->pdo->prepare("UPDATE cpd_attempts SET status='expired' WHERE id=UNHEX(:id)")->execute(['id'=>$id]);$this->pdo->prepare('UPDATE cpd_attempt_timing SET last_activity_at=UTC_TIMESTAMP(6) WHERE attempt_id=UNHEX(:id)')->execute(['id'=>$id]);$this->audit($o,$u,'cpd.attempt_expired','attempt',$id,['submittedAfterExpiry'=>true]);throw new CPDAssessmentException('cpd_attempt_expired');}
        $answers=$input['answers']??null;if(!is_array($answers))throw new CPDAssessmentException('cpd_answers_required');$assessmentId=bin2hex($attempt['assessment_id']);$questions=$this->questionRows($assessmentId);if(count($answers)!==count($questions))throw new CPDAssessmentException('cpd_answers_incomplete');
        $byId=[];foreach($answers as$answer){if(!is_array($answer))throw new CPDAssessmentException('cpd_answer_invalid');$questionId=$this->id($answer['questionId']??null,'cpd_question_invalid');$byId[$questionId]=$answer;}
        $earned=0.0;$total=0.0;$manual=false;
        return$this->transaction(function()use($o,$u,$id,$attempt,$questions,$byId,&$earned,&$total,&$manual):array{
            foreach($questions as$question){$qid=$question['id'];if(!isset($byId[$qid]))throw new CPDAssessmentException('cpd_answers_incomplete');$answer=$byId[$qid];$points=(float)$question['points'];$total+=$points;$selected=$this->idList($answer['selectedOptionIds']??[],'cpd_selected_options_invalid');$textAnswer=is_string($answer['text']??null)?trim($answer['text']):null;$awarded=null;$correct=null;
                if((bool)$question['requires_manual_review']){$manual=true;if($textAnswer===null||$textAnswer==='')throw new CPDAssessmentException('cpd_text_answer_required');}
                else{$expected=$this->correctOptionIds($qid);sort($selected);sort($expected);$correct=$selected===$expected;$awarded=$correct?$points:0.0;$earned+=$awarded;}
                $this->pdo->prepare('INSERT INTO cpd_attempt_answers(id,attempt_id,question_id,selected_option_ids,answer_text,awarded_points,is_correct) VALUES(UNHEX(:id),UNHEX(:attempt),UNHEX(:question),:selected,:text,:points,:correct)')->execute(['id'=>Identifier::generate(),'attempt'=>$id,'question'=>$qid,'selected'=>$this->json($selected),'text'=>$textAnswer,'points'=>$awarded,'correct'=>$correct===null?null:($correct?1:0)]);
            }
            $assessment=$this->assessmentRow($o,bin2hex($attempt['assessment_id']));$auto=$total>0?round($earned/$total*100,2):0.0;$passed=!$manual&&$auto>=(float)$assessment['pass_percent'];$status=$manual?'manual_review_required':($passed?'passed':'failed');
            $this->pdo->prepare('UPDATE cpd_attempts SET status=:status,auto_score=:score,final_score=:final,passed=:passed,manual_review_required=:manual,submitted_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)')->execute(['status'=>$status,'score'=>$auto,'final'=>$manual?null:$auto,'passed'=>$manual?null:($passed?1:0),'manual'=>$manual?1:0,'id'=>$id,'o'=>$o]);$this->pdo->prepare('UPDATE cpd_attempt_timing SET last_activity_at=UTC_TIMESTAMP(6) WHERE attempt_id=UNHEX(:id)')->execute(['id'=>$id]);
            $this->audit($o,$u,'cpd.attempt_submitted','attempt',$id,['status'=>$status,'autoScore'=>$auto,'manualReviewRequired'=>$manual]);
            if($passed)$this->issuePassingCertificate($o,$u,$id,'',$u);
            return$this->attempt($o,$id);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function reviewAttempt(string$o,string$u,string$role,string$id,array$input):array
    {
        $this->requireCapability($o,$u,$role,'attempt.review','cpd_attempt_review_forbidden');$this->confirmed($input);
        return $this->transaction(function()use($o,$u,$id,$input):array {
        $this->lockAttempt($o,$id);
        $attempt=$this->attemptRow($o,$id);if($attempt['status']!=='manual_review_required')throw new CPDAssessmentException('cpd_attempt_review_state_invalid');
        $decision=$this->choice($input['decision']??null,['pass','fail'],'cpd_attempt_decision_invalid');$reason=$this->text($input['reason']??null,2000,'cpd_review_reason_required');$passed=$decision==='pass';$score=$passed?100:((float)$attempt['auto_score']);$status=$passed?'passed':'failed';
        $this->pdo->prepare('UPDATE cpd_attempts SET status=:status,final_score=:score,passed=:passed,reviewed_by=UNHEX(:u),review_reason=:reason,reviewed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)')->execute(['status'=>$status,'score'=>$score,'passed'=>$passed?1:0,'u'=>$u,'reason'=>$reason,'id'=>$id,'o'=>$o]);
        $this->audit($o,$u,'cpd.attempt_reviewed','attempt',$id,['decision'=>$decision,'reason'=>$reason]);
        if($passed)$this->issuePassingCertificate($o,bin2hex($attempt['learner_user_id']),$id,'',$u);
        return$this->attempt($o,$id);
        });
    }

    /** @return array<string,mixed> */
    public function issueCertificate(string$o,string$u,string$role,string$attemptId,string$verificationBaseUrl):array
    {
        $this->membership($o,$u,$role);
        return $this->transaction(fn():array=>$this->issuePassingCertificate($o,$u,$attemptId,$verificationBaseUrl,$u));
    }

    private function lockAttempt(string$o,string$id):void
    {
        $lock=$this->pdo->prepare('SELECT id FROM cpd_attempts WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id) FOR UPDATE');
        $lock->execute(['o'=>$o,'id'=>$id]);
    }

    /** Called only inside the caller-owned transaction. @return array<string,mixed> */
    private function issuePassingCertificate(string$o,string$u,string$attemptId,string$verificationBaseUrl,string$actor):array
    {
        $this->lockAttempt($o,$attemptId);
        $attempt=$this->attemptRow($o,$attemptId);if(bin2hex($attempt['learner_user_id'])!==$u)throw new CPDAssessmentException('cpd_certificate_forbidden');if($attempt['status']!=='passed'||(int)$attempt['passed']!==1)throw new CPDAssessmentException('cpd_passing_attempt_required');
        $existing=$this->pdo->prepare('SELECT LOWER(HEX(id)) FROM cpd_certificates WHERE attempt_id=UNHEX(:attempt)');$existing->execute(['attempt'=>$attemptId]);$certificateId=$existing->fetchColumn();if(is_string($certificateId))return$this->certificate($o,$certificateId,$verificationBaseUrl);
        $course=$this->courseRow($o,bin2hex($attempt['course_id']));$this->assertCourseLive($course);$profile=$this->profileRow($o,$u);$application=$this->accreditationRow($o,bin2hex($course['accreditation_application_id']));$rule=$this->decode($application['rule_snapshot']);$id=Identifier::generate();$code=strtoupper(bin2hex(random_bytes(16)));
            $this->pdo->prepare('INSERT INTO cpd_certificates(id,organisation_id,attempt_id,learner_user_id,course_id,verification_code,learner_name,professional_body,registration_number,approved_category,credits_awarded,accreditation_reference,provider_name) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:attempt),UNHEX(:u),UNHEX(:course),:code,:name,:body,:number,:category,:credits,:reference,:provider)')->execute(['id'=>$id,'o'=>$o,'attempt'=>$attemptId,'u'=>$u,'course'=>$course['id'],'code'=>$code,'name'=>$profile['full_name'],'body'=>$profile['professional_body'],'number'=>$profile['registration_number'],'category'=>$course['approved_category'],'credits'=>$course['approved_credits'],'reference'=>$course['accreditation_reference'],'provider'=>$course['provider_name']]);
            $this->pdo->prepare('INSERT INTO cpd_professional_records(id,organisation_id,learner_user_id,certificate_id,attempt_id,professional_body,registration_number,activity_title,completed_on,approved_category,credits_awarded,accreditation_reference,rule_snapshot) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),UNHEX(:certificate),UNHEX(:attempt),:body,:number,:title,UTC_DATE(),:category,:credits,:reference,:snapshot)')->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u,'certificate'=>$id,'attempt'=>$attemptId,'body'=>$profile['professional_body'],'number'=>$profile['registration_number'],'title'=>$course['title'],'category'=>$course['approved_category'],'credits'=>$course['approved_credits'],'reference'=>$course['accreditation_reference'],'snapshot'=>$this->json($rule)]);
            $this->audit($o,$actor,'cpd.certificate_issued','certificate',$id,['attemptId'=>$attemptId,'learnerUserId'=>$u,'accreditationReference'=>$course['accreditation_reference']]);return$this->certificate($o,$id,$verificationBaseUrl);
    }

    /** @return array{content:string,mimeType:string,filename:string,sha256:string} */
    public function certificatePdf(string$o,string$u,string$role,string$certificateId,string$verificationBaseUrl=''):array
    {
        $this->membership($o,$u,$role);
        $s=$this->pdo->prepare('SELECT c.*,LOWER(HEX(c.id)) id,LOWER(HEX(c.learner_user_id)) learner_user_id,r.activity_title,r.rule_snapshot FROM cpd_certificates c JOIN cpd_professional_records r ON r.certificate_id=c.id AND r.organisation_id=c.organisation_id WHERE c.organisation_id=UNHEX(:o) AND c.id=UNHEX(:id)');
        $s->execute(['o'=>$o,'id'=>$certificateId]);
        $certificate=$s->fetch();
        if(!is_array($certificate))throw new CPDAssessmentException('cpd_certificate_not_found');
        if($certificate['learner_user_id']!==$u&&!in_array($role,self::GOVERNORS,true))throw new CPDAssessmentException('cpd_certificate_forbidden');
        $lines=['Course: '.$certificate['activity_title'],'Learner: '.$certificate['learner_name'],'Professional body: '.$certificate['professional_body'],'Registration: '.$certificate['registration_number'],'Category: '.$certificate['approved_category'],CPDCertificatePdf::awardField($this->decimalString((string)$certificate['credits_awarded']),$this->decode($certificate['rule_snapshot'])),'Accreditation: '.$certificate['accreditation_reference'],'Provider: '.$certificate['provider_name'],'Assessment status: passed','Verification code: '.$certificate['verification_code'],'Issued: '.$certificate['issued_at'],'Verification status: '.($certificate['revoked_at']===null?'valid':'revoked')];
        $content=CPDCertificatePdf::render($lines,CertificateVerificationUrl::build($verificationBaseUrl,$certificate['verification_code']));
        return['content'=>$content,'mimeType'=>'application/pdf','filename'=>'architex-cpd-certificate-'.$certificate['verification_code'].'.pdf','sha256'=>hash('sha256',$content)];
    }

    /** @return array<string,mixed> */
    public function verifyCertificate(string$verificationCode):array
    {
        if(preg_match('/\A[A-F0-9]{32}\z/',$verificationCode)!==1)throw new CPDAssessmentException('cpd_certificate_not_found');
        $s=$this->pdo->prepare('SELECT verification_code,learner_name,professional_body,approved_category,credits_awarded,accreditation_reference,provider_name,issued_at,revoked_at FROM cpd_certificates WHERE verification_code=:code');
        $s->execute(['code'=>$verificationCode]);
        $row=$s->fetch();
        if(!is_array($row))throw new CPDAssessmentException('cpd_certificate_not_found');
        // Public lookup is award evidence, not an authenticated certificate record.
        // Keep an explicit allowlist even if future query changes add private fields.
        $public=$this->row(array_intersect_key($row,array_flip([
            'verification_code','learner_name','professional_body','approved_category',
            'credits_awarded','accreditation_reference','provider_name','issued_at',
        ])));
        $public['valid']=$row['revoked_at']===null;
        return $public;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function recordProviderPayment(string$o,string$provider,array$payload,?string$configuredSecret):array
    {
        if($configuredSecret===null||$configuredSecret==='')throw new CPDAssessmentException('cpd_payment_integration_required');
        throw new CPDAssessmentException('cpd_payment_signature_invalid');
    }

    /** @return array<string,mixed> */
    public function workspace(string$o,string$u,string$role):array
    {
        $this->membership($o,$u,$role);$participation=$this->participationRole($o,$u,$role);$capabilities=$this->capabilities($participation);$governor=$capabilities['canManageCommercial'];
        $courses=$this->all('SELECT *,LOWER(HEX(id)) id FROM cpd_courses WHERE organisation_id=UNHEX(:o) ORDER BY created_at DESC',['o'=>$o]);
        $records=$this->all('SELECT r.*,LOWER(HEX(r.id)) id,LOWER(HEX(r.certificate_id)) certificate_id,c.verification_code FROM cpd_professional_records r JOIN cpd_certificates c ON c.id=r.certificate_id WHERE r.organisation_id=UNHEX(:o) AND r.learner_user_id=UNHEX(:u) ORDER BY r.completed_on DESC',['o'=>$o,'u'=>$u],['rule_snapshot']);
        $contents=$this->all('SELECT *,LOWER(HEX(id)) id FROM cpd_content_items WHERE organisation_id=UNHEX(:o) ORDER BY created_at DESC',['o'=>$o],['learning_outcomes','target_bodies']);
        $assessmentRows=$this->all('SELECT a.*,LOWER(HEX(a.id)) id,LOWER(HEX(g.supersedes_assessment_id)) supersedes_assessment_id,g.generation_model,g.generation_request_id,g.generation_usage,g.risk_flags,g.question_bank_hash FROM cpd_assessments a LEFT JOIN cpd_assessment_governance g ON g.assessment_id=a.id WHERE a.organisation_id=UNHEX(:o) ORDER BY a.created_at DESC',['o'=>$o]);$assessments=[];foreach($assessmentRows as$assessment){$assessment['questions']=($capabilities['canReviewAssessments']?$this->questions($assessment['id']):$this->workspaceQuestions($assessment['id']));$assessments[]=$assessment;}
        $accreditations=$this->all('SELECT *,LOWER(HEX(id)) id FROM cpd_accreditation_applications WHERE organisation_id=UNHEX(:o) ORDER BY created_at DESC',['o'=>$o],['rule_snapshot']);
        $entitlements=$governor?$this->all('SELECT *,LOWER(HEX(id)) id FROM cpd_entitlements WHERE organisation_id=UNHEX(:o) ORDER BY requested_at DESC',['o'=>$o]):$this->all('SELECT *,LOWER(HEX(id)) id FROM cpd_entitlements WHERE organisation_id=UNHEX(:o) AND learner_user_id=UNHEX(:u) ORDER BY requested_at DESC',['o'=>$o,'u'=>$u]);
        $attempts=$governor?$this->all('SELECT a.*,LOWER(HEX(a.id)) id,t.expires_at,t.last_activity_at FROM cpd_attempts a LEFT JOIN cpd_attempt_timing t ON t.attempt_id=a.id WHERE a.organisation_id=UNHEX(:o) ORDER BY a.started_at DESC',['o'=>$o]):$this->all('SELECT a.*,LOWER(HEX(a.id)) id,t.expires_at,t.last_activity_at FROM cpd_attempts a LEFT JOIN cpd_attempt_timing t ON t.attempt_id=a.id WHERE a.organisation_id=UNHEX(:o) AND a.learner_user_id=UNHEX(:u) ORDER BY a.started_at DESC',['o'=>$o,'u'=>$u]);
        if($capabilities['canReviewAttempts']&&!$governor){
            $attempts=$this->all("SELECT a.*,LOWER(HEX(a.id)) id,t.expires_at,t.last_activity_at FROM cpd_attempts a LEFT JOIN cpd_attempt_timing t ON t.attempt_id=a.id WHERE a.organisation_id=UNHEX(:o) AND (a.learner_user_id=UNHEX(:u) OR a.status='manual_review_required') ORDER BY a.started_at DESC",['o'=>$o,'u'=>$u]);
        }
        if($capabilities['canReviewAttempts'])foreach($attempts as &$reviewAttempt){
            if($reviewAttempt['status']!=='manual_review_required')continue;
            $reviewAttempt['reviewAnswers']=$this->all('SELECT LOWER(HEX(ans.question_id)) question_id,q.prompt,ans.answer_text,ans.selected_option_ids,g.model_answer,q.rationale FROM cpd_attempt_answers ans JOIN cpd_attempts a ON a.id=ans.attempt_id JOIN cpd_questions q ON q.id=ans.question_id LEFT JOIN cpd_question_governance g ON g.question_id=q.id WHERE a.organisation_id=UNHEX(:o) AND a.id=UNHEX(:id) ORDER BY q.sequence_no',['o'=>$o,'id'=>$reviewAttempt['id']],['selected_option_ids']);
        }
        unset($reviewAttempt);
        $analytics=$this->pdo->prepare('SELECT COUNT(*) total_attempts,COUNT(DISTINCT learner_user_id) total_learners,SUM(passed=1) passed_attempts,ROUND(AVG(final_score),2) average_score_percent FROM cpd_attempts WHERE organisation_id=UNHEX(:o)');$analytics->execute(['o'=>$o]);$metric=$analytics->fetch()?:[];$total=(int)($metric['total_attempts']??0);$passed=(int)($metric['passed_attempts']??0);
        $participants=$capabilities['canAssignRoles']?$this->all("SELECT p.*,LOWER(HEX(p.id)) id,LOWER(HEX(p.user_id)) user_id,u.email FROM cpd_professional_profiles p JOIN users u ON u.id=p.user_id WHERE p.organisation_id=UNHEX(:o) ORDER BY p.full_name",['o'=>$o]):[];
        return['professionalBodies'=>$this->professionalBodyRules(),'profile'=>$this->optionalProfile($o,$u),'participationRole'=>$participation,'participants'=>$participants,'courses'=>$courses,'contentItems'=>$contents,'assessments'=>$assessments,'accreditationApplications'=>$accreditations,'entitlements'=>$entitlements,'attempts'=>$attempts,'records'=>$records,'commercialSettings'=>$this->commercialSettings($o),'analytics'=>['totalAttempts'=>$total,'totalLearners'=>(int)($metric['total_learners']??0),'passedAttempts'=>$passed,'passRatePercent'=>$total===0?0:round($passed/$total*100,2),'averageScorePercent'=>$metric['average_score_percent']===null?null:(float)$metric['average_score_percent']],'capabilities'=>$capabilities,'boundaries'=>['accreditationAutomated'=>false,'estimatedCreditsAreApproved'=>false,'providerPaymentConfigured'=>false,'certificateRequiresAccreditedPassingAttempt'=>true,'aiDraftRequiresHumanReview'=>true]];
    }

    /** @param array<string,mixed> $_entitlement */
    private function recordPurchase(string$o,string$entitlementId,array$_entitlement):void
    {
        $details=$this->pdo->prepare('SELECT e.amount_cents,e.currency,e.course_id,e.learner_user_id,content.created_by content_owner_user_id FROM cpd_entitlements e JOIN cpd_courses c ON c.id=e.course_id JOIN cpd_assessments a ON a.id=c.assessment_id JOIN cpd_content_items content ON content.id=a.content_item_id WHERE e.organisation_id=UNHEX(:o) AND e.id=UNHEX(:id)');$details->execute(['o'=>$o,'id'=>$entitlementId]);$row=$details->fetch();if(!is_array($row))throw new CPDAssessmentException('cpd_entitlement_not_found');$settings=$this->commercialSettingsRow($o);$gross=(int)$row['amount_cents'];$basis=(int)$settings['platform_fee_basis_points'];$fee=$gross===0?0:max((int)$settings['minimum_platform_fee_cents'],(int)round($gross*$basis/10000));$fee=min($gross,$fee);$net=$gross-$fee;$purchase=Identifier::generate();$this->pdo->prepare("INSERT INTO cpd_purchase_ledger(id,organisation_id,entitlement_id,course_id,learner_user_id,content_owner_user_id,gross_cents,platform_fee_cents,content_owner_net_cents,platform_fee_basis_points,currency,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:entitlement),UNHEX(:course),UNHEX(:learner),UNHEX(:owner),:gross,:fee,:net,:basis,:currency,'recorded')")->execute(['id'=>$purchase,'o'=>$o,'entitlement'=>$entitlementId,'course'=>bin2hex($row['course_id']),'learner'=>bin2hex($row['learner_user_id']),'owner'=>bin2hex($row['content_owner_user_id']),'gross'=>$gross,'fee'=>$fee,'net'=>$net,'basis'=>$basis,'currency'=>$row['currency']]);if((int)$settings['payout_enabled']===1&&$net>0)$this->pdo->prepare("INSERT INTO cpd_payouts(id,organisation_id,purchase_ledger_id,content_owner_user_id,payout_cents,currency,status,moves_money) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:purchase),UNHEX(:owner),:amount,:currency,'pending',FALSE)")->execute(['id'=>Identifier::generate(),'o'=>$o,'purchase'=>$purchase,'owner'=>bin2hex($row['content_owner_user_id']),'amount'=>$net,'currency'=>$row['currency']]);
    }

    /** @return array<string,mixed> */ private function commercialSettings(string$o):array{$row=$this->commercialSettingsRow($o);return['platformFeeBasisPoints'=>(int)$row['platform_fee_basis_points'],'minimumPlatformFeeCents'=>(int)$row['minimum_platform_fee_cents'],'payoutEnabled'=>(bool)$row['payout_enabled'],'configuredProviders'=>$this->decode($row['configured_providers']),'updatedAt'=>$row['updated_at']??null];}
    /** @return array<string,mixed> */ private function commercialSettingsRow(string$o):array{$s=$this->pdo->prepare('SELECT * FROM cpd_commercial_settings WHERE organisation_id=UNHEX(:o)');$s->execute(['o'=>$o]);$r=$s->fetch();return is_array($r)?$r:['organisation_id'=>hex2bin($o),'platform_fee_basis_points'=>1000,'minimum_platform_fee_cents'=>100,'payout_enabled'=>0,'configured_providers'=>'["manual_eft"]','updated_by'=>null,'updated_at'=>null];}

    /**
     * @param list<array<string,mixed>> $questions
     * @param list<string> $riskFlags
     * @param array<string,mixed>|null $generationUsage
     * @return array<string,mixed>
     */
    private function storeAssessmentDraft(string$o,string$u,string$contentId,string$title,string$pass,int$limit,?int$timeLimit,array$questions,bool$generated,array$riskFlags,?string$model,?string$requestId,?array$generationUsage):array
    {
        $id=Identifier::generate();$hash=hash('sha256',$this->json($questions));
        return$this->transaction(function()use($o,$u,$contentId,$title,$pass,$limit,$timeLimit,$questions,$generated,$riskFlags,$model,$requestId,$generationUsage,$id,$hash):array{
            $latest=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,version FROM cpd_assessments WHERE organisation_id=UNHEX(:o) AND content_item_id=UNHEX(:content) ORDER BY version DESC LIMIT 1 FOR UPDATE');$latest->execute(['o'=>$o,'content'=>$contentId]);$previous=$latest->fetch();$version=is_array($previous)?(int)$previous['version']+1:1;$supersedes=is_array($previous)?$previous['id']:null;
            $this->pdo->prepare("INSERT INTO cpd_assessments(id,organisation_id,content_item_id,title,version,pass_percent,attempt_limit,time_limit_minutes,generated_with_ai,status,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:content),:title,:version,:pass,:attempts,:timeLimit,:ai,'human_review_required',UNHEX(:u))")->execute(['id'=>$id,'o'=>$o,'content'=>$contentId,'title'=>$title,'version'=>$version,'pass'=>$pass,'attempts'=>$limit,'timeLimit'=>$timeLimit,'ai'=>$generated?1:0,'u'=>$u]);
            $this->pdo->prepare('INSERT INTO cpd_assessment_governance(assessment_id,organisation_id,supersedes_assessment_id,generation_model,generation_request_id,generation_usage,risk_flags,question_bank_hash) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:supersedes),:model,:requestId,:usage,:riskFlags,:hash)')->execute(['id'=>$id,'o'=>$o,'supersedes'=>$supersedes,'model'=>$model,'requestId'=>$requestId,'usage'=>$generationUsage===null?null:$this->json($generationUsage),'riskFlags'=>$this->json($riskFlags),'hash'=>$hash]);
            foreach(array_values($questions)as$index=>$question){if(!is_array($question))throw new CPDAssessmentException('cpd_question_invalid');$this->insertQuestion($id,$index+1,$question);}
            $this->audit($o,$u,'cpd.assessment_drafted','assessment',$id,['generatedWithAi'=>$generated,'questionCount'=>count($questions),'version'=>$version,'supersedesAssessmentId'=>$supersedes,'generationModel'=>$model,'generationRequestId'=>$requestId]);
            return$this->assessment($o,$id);
        });
    }

    /** @param array<string,mixed> $question */
    private function insertQuestion(string$assessmentId,int$sequence,array$question):void
    {
        $prompt=$this->text($question['prompt']??null,10000,'cpd_question_prompt_required');$type=$this->choice($question['type']??null,['single_choice','multiple_choice','scenario','text_review'],'cpd_question_type_invalid');$points=$this->decimal($question['points']??null,0.01,10000,'cpd_question_points_invalid');$manual=$type==='text_review';$id=Identifier::generate();
        $rationale=$this->optionalText($question['explanation']??$question['rationale']??null,10000,'cpd_question_explanation_invalid');$learningOutcome=$this->optionalText($question['learningOutcome']??null,500,'cpd_question_learning_outcome_invalid');$sourceReference=$this->optionalText($question['sourceReference']??null,500,'cpd_question_source_reference_invalid');$difficulty=$this->choice($question['difficulty']??'medium',['easy','medium','hard'],'cpd_question_difficulty_invalid');$modelAnswer=$this->optionalText($question['modelAnswer']??null,10000,'cpd_question_model_answer_invalid');
        $this->pdo->prepare('INSERT INTO cpd_questions(id,assessment_id,sequence_no,question_type,prompt,points,rationale,requires_manual_review) VALUES(UNHEX(:id),UNHEX(:assessment),:sequence,:type,:prompt,:points,:rationale,:manual)')->execute(['id'=>$id,'assessment'=>$assessmentId,'sequence'=>$sequence,'type'=>$type,'prompt'=>$prompt,'points'=>$points,'rationale'=>$rationale,'manual'=>$manual?1:0]);$this->pdo->prepare('INSERT INTO cpd_question_governance(question_id,learning_outcome,source_reference,difficulty,model_answer) VALUES(UNHEX(:id),:outcome,:source,:difficulty,:modelAnswer)')->execute(['id'=>$id,'outcome'=>$learningOutcome,'source'=>$sourceReference,'difficulty'=>$difficulty,'modelAnswer'=>$modelAnswer]);
        $options=$question['options']??[];if($manual){if($options!==[]&&!is_array($options))throw new CPDAssessmentException('cpd_question_options_invalid');return;}if(!is_array($options)||count($options)<2)throw new CPDAssessmentException('cpd_question_options_required');$correct=0;
        foreach(array_values($options)as$index=>$option){if(!is_array($option))throw new CPDAssessmentException('cpd_question_options_invalid');$text=$this->text($option['text']??null,1000,'cpd_option_text_required');$isCorrect=($option['correct']??false)===true;if($isCorrect)$correct++;$this->pdo->prepare('INSERT INTO cpd_question_options(id,question_id,sequence_no,option_text,is_correct) VALUES(UNHEX(:id),UNHEX(:question),:sequence,:text,:correct)')->execute(['id'=>Identifier::generate(),'question'=>$id,'sequence'=>$index+1,'text'=>$text,'correct'=>$isCorrect?1:0]);}
        if($correct===0||($type==='single_choice'&&$correct!==1))throw new CPDAssessmentException('cpd_correct_options_invalid');
    }

    /** @return array<string,mixed> */ private function profile(string$o,string$u):array{return$this->row($this->profileRow($o,$u));}
    /** @return array<string,mixed>|null */ private function optionalProfile(string$o,string$u):?array{try{return$this->profile($o,$u);}catch(CPDAssessmentException){return null;}}
    /** @return array<string,mixed> */ private function profileRow(string$o,string$u):array{$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM cpd_professional_profiles WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)');$s->execute(['o'=>$o,'u'=>$u]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_profile_required');return$r;}
    /** @return array<string,mixed> */ private function content(string$o,string$id):array{return$this->row($this->contentRow($o,$id),['learning_outcomes','target_bodies']);}
    /** @return array<string,mixed> */ private function contentRow(string$o,string$id):array{$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM cpd_content_items WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_content_not_found');return$r;}
    /** @return array<string,mixed> */ private function assessment(string$o,string$id):array{$row=$this->row($this->assessmentRow($o,$id));$row['questions']=$this->questions($id);return$row;}
    /** @return array<string,mixed> */ private function assessmentRow(string$o,string$id):array{$this->id($id,'cpd_assessment_not_found');$s=$this->pdo->prepare('SELECT a.*,LOWER(HEX(a.id)) id,LOWER(HEX(g.supersedes_assessment_id)) supersedes_assessment_id,g.generation_model,g.generation_request_id,g.generation_usage,g.risk_flags,g.question_bank_hash FROM cpd_assessments a LEFT JOIN cpd_assessment_governance g ON g.assessment_id=a.id WHERE a.organisation_id=UNHEX(:o) AND a.id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_assessment_not_found');return$r;}
    /** @return list<array<string,mixed>> */ private function questions(string$assessmentId):array{$out=[];foreach($this->questionRows($assessmentId)as$row){$question=$this->row($row);$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM cpd_question_options WHERE question_id=UNHEX(:id) ORDER BY sequence_no');$s->execute(['id'=>$question['id']]);$question['options']=array_map(fn($x)=>$this->row($x),$s->fetchAll());$out[]=$question;}return$out;}
    /** @return list<array<string,mixed>> */ private function workspaceQuestions(string$assessmentId):array{$questions=$this->questions($assessmentId);foreach($questions as&$question){unset($question['modelAnswer'],$question['rationale']);foreach($question['options']as&$option)unset($option['isCorrect']);}unset($option,$question);return$questions;}
    /** @return list<array<string,mixed>> */ private function questionRows(string$assessmentId):array{$s=$this->pdo->prepare('SELECT q.*,LOWER(HEX(q.id)) id,g.learning_outcome,g.source_reference,g.difficulty,g.model_answer FROM cpd_questions q LEFT JOIN cpd_question_governance g ON g.question_id=q.id WHERE q.assessment_id=UNHEX(:id) ORDER BY q.sequence_no');$s->execute(['id'=>$assessmentId]);return$s->fetchAll();}
    /** @return list<string> */ private function correctOptionIds(string$questionId):array{$s=$this->pdo->prepare('SELECT LOWER(HEX(id)) FROM cpd_question_options WHERE question_id=UNHEX(:id) AND is_correct=TRUE ORDER BY sequence_no');$s->execute(['id'=>$questionId]);return$s->fetchAll(PDO::FETCH_COLUMN);}
    /** @return array<string,mixed> */ private function accreditation(string$o,string$id):array{return$this->row($this->accreditationRow($o,$id),['rule_snapshot']);}
    /** @return array<string,mixed> */ private function accreditationRow(string$o,string$id):array{$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM cpd_accreditation_applications WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_accreditation_not_found');return$r;}
    /** @return array<string,mixed> */ private function course(string$o,string$id):array{return$this->row($this->courseRow($o,$id));}
    /** @return array<string,mixed> */ private function courseRow(string$o,string$id):array{$this->id($id,'cpd_course_not_found');$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM cpd_courses WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_course_not_found');return$r;}
    /** @return array<string,mixed> */ private function entitlement(string$o,string$id):array{return$this->row($this->entitlementRow($o,$id));}
    /** @return array<string,mixed> */ private function entitlementRow(string$o,string$id):array{$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM cpd_entitlements WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_entitlement_not_found');return$r;}
    /** @return array<string,mixed> */ private function attempt(string$o,string$id):array{return$this->row($this->attemptRow($o,$id));}
    /** @return array<string,mixed> */ private function attemptRow(string$o,string$id):array{$s=$this->pdo->prepare('SELECT a.*,LOWER(HEX(a.id)) id,t.expires_at,t.last_activity_at FROM cpd_attempts a LEFT JOIN cpd_attempt_timing t ON t.attempt_id=a.id WHERE a.organisation_id=UNHEX(:o) AND a.id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_attempt_not_found');return$r;}
    /** @return array<string,mixed> */ private function certificate(string$o,string$id,string$base):array{$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM cpd_certificates WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_certificate_not_found');return$this->certificateRow($r,$base);}
    /** @param array<string,mixed> $r @return array<string,mixed> */ private function certificateRow(array$r,string$base):array{$row=$this->row($r);$row['valid']=$r['revoked_at']===null;$row['verificationUrl']=CertificateVerificationUrl::build($base,$r['verification_code']);return$row;}
    /** @return array<string,mixed> */ private function bodyRule(string$body):array{$s=$this->pdo->prepare('SELECT * FROM cpd_professional_body_rules WHERE body_code=:body');$s->execute(['body'=>$body]);$r=$s->fetch();if(!is_array($r))throw new CPDAssessmentException('cpd_professional_body_rules_unavailable');return$this->row($r,['category_rules']);}
    /** @param array<string,mixed> $course */ private function assertCourseLive(array$course):void{$today=(new DateTimeImmutable('today'))->format('Y-m-d');if($course['status']!=='live'||$course['valid_from']>$today||$course['valid_until']<$today)throw new CPDAssessmentException('cpd_course_not_live');}
    private function membership(string$o,string$u,string$role):void{$s=$this->pdo->prepare("SELECT role FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active'");$s->execute(['o'=>$o,'u'=>$u]);$actual=$s->fetchColumn();if(!is_string($actual)||$actual!==$role)throw new CPDAssessmentException('cpd_membership_required');}
    private function membershipRole(string$o,string$u):string{$s=$this->pdo->prepare("SELECT role FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active'");$s->execute(['o'=>$o,'u'=>$u]);$actual=$s->fetchColumn();if(!is_string($actual))throw new CPDAssessmentException('cpd_membership_required');return$actual;}
    private function requireCapability(string$o,string$u,string$role,string$capability,string$error):void{$this->membership($o,$u,$role);$participation=$this->participationRole($o,$u,$role);$map=['content.create'=>'canCreateContent','assessment.draft'=>'canDraftAssessments','assessment.review'=>'canReviewAssessments','attempt.review'=>'canReviewAttempts','accreditation.submit'=>'canSubmitAccreditation','accreditation.decide'=>'canDecideAccreditation','course.publish'=>'canPublishCourses','commercial.manage'=>'canManageCommercial','roles.assign'=>'canAssignRoles'];$key=$map[$capability]??null;if($key===null||!($this->capabilities($participation)[$key]??false))throw new CPDAssessmentException($error);}
    private function participationRole(string$o,string$u,string$membershipRole):string{if(in_array($membershipRole,self::GOVERNORS,true))return'platform_admin';$profile=$this->optionalProfile($o,$u);return is_array($profile)&&is_string($profile['participationRole']??null)?$profile['participationRole']:'learner';}
    /** @return array<string,bool> */ private function capabilities(string$participation):array{$admin=$participation==='platform_admin';$creator=$participation==='content_creator';$reviewer=$participation==='cpd_reviewer';$partner=$participation==='accreditation_partner';return['canLearn'=>true,'canCreateContent'=>$creator||$admin,'canDraftAssessments'=>$creator||$admin,'canReviewAssessments'=>$reviewer||$admin,'canReviewAttempts'=>$reviewer||$admin,'canSubmitAccreditation'=>$creator||$reviewer||$partner||$admin,'canDecideAccreditation'=>$partner||$admin,'canPublishCourses'=>$partner||$admin,'canManageCommercial'=>$admin,'canAssignRoles'=>$admin,'canReview'=>$reviewer||$partner||$admin];}
    /** @param array<string,mixed> $context */ private function audit(string$o,string$u,string$action,string$type,string$id,array$context):void{$this->pdo->prepare('INSERT INTO cpd_audit_events(id,organisation_id,actor_user_id,action,object_type,object_id,context) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:action,:type,UNHEX(:object),:context)')->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u,'action'=>$action,'type'=>$type,'object'=>$id,'context'=>$this->json($context)]);}
    /** @param array<string,mixed> $row @param list<string> $jsonFields @return array<string,mixed> */ private function row(array$row,array$jsonFields=[]):array{$jsonFields=array_values(array_unique([...$jsonFields,'generation_usage','risk_flags']));$out=[];foreach($row as$key=>$value){if(in_array($key,$jsonFields,true))$value=$this->decode($value);$name=preg_replace_callback('/_([a-z])/',static fn($m)=>strtoupper($m[1]),(string)$key);if(in_array($key,['generated_with_ai','requires_manual_review','is_correct','payout_enabled','signature_verified','manual_review_required','passed','consented'],true)&&$value!==null)$value=(bool)$value;if(in_array($key,['version','duration_minutes','attempt_limit','time_limit_minutes','sequence_no','attempt_number','price_cents','amount_cents'],true)&&$value!==null)$value=(int)$value;if(in_array($key,['requested_credits','approved_credits','credits_awarded'],true)&&$value!==null)$value=$this->decimalString((string)$value);if(is_string($value)&&strlen($value)===16&&($key==='id'||str_ends_with((string)$key,'_id')||str_ends_with((string)$key,'_by')))$value=bin2hex($value);$out[$name]=$value;}return$out;}
    /** @param array<string,mixed> $params @param list<string> $jsonFields @return list<array<string,mixed>> */ private function all(string$sql,array$params,array$jsonFields=[]):array{$s=$this->pdo->prepare($sql);$s->execute($params);return array_map(fn($r)=>$this->row($r,$jsonFields),$s->fetchAll());}
    /** @template T @param callable():T $callback @return T */ private function transaction(callable$callback):mixed{$this->pdo->beginTransaction();try{$result=$callback();$this->pdo->commit();return$result;}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
    /** @param array<string,mixed> $input */ private function confirmed(array$input):void{if(($input['humanConfirmed']??false)!==true)throw new CPDAssessmentException('cpd_human_confirmation_required');}
    private function text(mixed$value,int$max,string$error):string{if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new CPDAssessmentException($error);return trim($value);}
    private function optionalText(mixed$value,int$max,string$error):?string{if($value===null||$value==='')return null;return$this->text($value,$max,$error);}
    /** @param list<string> $choices */ private function choice(mixed$value,array$choices,string$error):string{if(!is_string($value)||!in_array($value,$choices,true))throw new CPDAssessmentException($error);return$value;}
    private function integer(mixed$value,int$min,int$max,string$error):int{if(!is_int($value)&&!(is_string($value)&&preg_match('/\A\d+\z/',$value)===1))throw new CPDAssessmentException($error);$number=(int)$value;if($number<$min||$number>$max)throw new CPDAssessmentException($error);return$number;}
    private function optionalInteger(mixed$value,int$min,int$max,string$error):?int{if($value===null||$value==='')return null;return$this->integer($value,$min,$max,$error);}
    private function decimal(mixed$value,float$min,float$max,string$error):string{if(!is_int($value)&&!is_float($value)&&!(is_string($value)&&is_numeric($value)))throw new CPDAssessmentException($error);$number=(float)$value;if(!is_finite($number)||$number<$min||$number>$max)throw new CPDAssessmentException($error);return number_format($number,2,'.','');}
    private function decimalString(string$value):string{$value=rtrim(rtrim($value,'0'),'.');return$value===''?'0':$value;}
    private function date(mixed$value,string$error):string{if(!is_string($value)||DateTimeImmutable::createFromFormat('!Y-m-d',$value)?->format('Y-m-d')!==$value)throw new CPDAssessmentException($error);return$value;}
    private function id(mixed$value,string$error):string{if(!is_string($value)||preg_match('/\A[a-f0-9]{32}\z/i',$value)!==1)throw new CPDAssessmentException($error);return strtolower($value);}
    /** @return list<string> */ private function bodyList(mixed$value):array{if(!is_array($value)||$value===[])throw new CPDAssessmentException('cpd_target_bodies_required');$out=[];foreach($value as$body)$out[]=$this->choice($body,self::BODIES,'cpd_professional_body_invalid');return array_values(array_unique($out));}
    /** @return list<string> */ private function strings(mixed$value,int$maxItems,int$maxLength,string$error):array{if(!is_array($value)||$value===[]||count($value)>$maxItems)throw new CPDAssessmentException($error);$out=[];foreach($value as$item){if(!is_string($item)||trim($item)===''||mb_strlen(trim($item))>$maxLength)throw new CPDAssessmentException($error);$out[]=trim($item);}return array_values(array_unique($out));}
    /** @return list<string> */ private function idList(mixed$value,string$error):array{if(!is_array($value))throw new CPDAssessmentException($error);$out=[];foreach($value as$id)$out[]=$this->id($id,$error);return array_values(array_unique($out));}
    private function json(mixed$value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    /** @return array<string,mixed> */ private function decodeModelJson(mixed$value):array{if(!is_string($value)||trim($value)==='')throw new CPDAssessmentException('cpd_ai_generation_invalid');$value=trim($value);if(preg_match('/\A```(?:json)?\s*(.*?)\s*```\z/is',$value,$match)===1)$value=$match[1];try{$decoded=json_decode($value,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){throw new CPDAssessmentException('cpd_ai_generation_invalid');}if(!is_array($decoded)||array_is_list($decoded))throw new CPDAssessmentException('cpd_ai_generation_invalid');return$decoded;}
    /** @return array<mixed> */ private function decode(mixed$value):array{if(!is_string($value))return[];try{$decoded=json_decode($value,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){return[];}return is_array($decoded)?$decoded:[];}
}

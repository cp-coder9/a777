<?php

declare(strict_types=1);

namespace Architex\Platform\Brief;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use PDO;
use Throwable;

final readonly class GuidedBriefService
{
    private const INTENTS=['renovate_or_add','plans_approved','updated_plans_for_sale','construction_price','not_sure'];
    private const URGENCY=['normal','soon','urgent'];
    private const BUDGET=['defined','range','needs_guidance','unknown'];
    private const SUPPORT=['plans','approvals','construction_pricing','full_delivery','advice_only'];
    private AuditService $audit;
    public function __construct(private PDO $pdo){$this->audit=new AuditService($pdo);}

    /** @return array<string,mixed> */
    public function create(string $organisationId,string $userId,string $title): array
    {
        $this->assertMember($organisationId,$userId);$title=trim($title);
        if($title===''||mb_strlen($title)>180)throw new BriefException('invalid_brief_title');
        return $this->transaction(function()use($organisationId,$userId,$title):array{
            $id=Identifier::generate();
            $this->pdo->prepare("INSERT INTO guided_briefs(id,organisation_id,created_by,title,answers) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:title,'{}')")->execute(['id'=>$id,'organisation_id'=>$organisationId,'user_id'=>$userId,'title'=>$title]);
            $this->audit->append($organisationId,$userId,'guided_brief.created','guided_brief',$id,['version'=>1]);
            return ['id'=>$id,'title'=>$title,'status'=>'draft','version'=>1,'currentStep'=>1,'answers'=>[]];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function save(string $organisationId,string $userId,string $briefId,int $expectedVersion,array $input): array
    {
        return $this->transaction(function()use($organisationId,$userId,$briefId,$expectedVersion,$input):array{
            $brief=$this->lock($organisationId,$userId,$briefId);
            if($brief['status']!=='draft')throw new BriefException('brief_locked');
            if((int)$brief['version']!==$expectedVersion)throw new BriefException('stale_brief_version');
            $answers=json_decode((string)$brief['answers'],true,32,JSON_THROW_ON_ERROR);if(!is_array($answers))$answers=[];
            foreach(['intent','goal','hasExistingPlans','workAlreadyExists','urgency','budgetBand','supportNeeds'] as $key)if(array_key_exists($key,$input))$answers[$key]=$input[$key];
            $this->validateAnswers($answers,false);
            $step=$input['currentStep']??$brief['current_step'];if(!is_int($step)||$step<1||$step>8)throw new BriefException('invalid_brief_step');
            $updated=$this->pdo->prepare("UPDATE guided_briefs SET answers=:answers,current_step=:step,version=version+1 WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND created_by=UNHEX(:user_id) AND version=:expected AND status='draft'");
            $updated->execute(['answers'=>json_encode($answers,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),'step'=>$step,'id'=>$briefId,'organisation_id'=>$organisationId,'user_id'=>$userId,'expected'=>$expectedVersion]);
            if($updated->rowCount()!==1)throw new BriefException('stale_brief_version');
            $this->audit->append($organisationId,$userId,'guided_brief.saved','guided_brief',$briefId,['version'=>$expectedVersion+1,'currentStep'=>$step]);
            return ['id'=>$briefId,'title'=>$brief['title'],'status'=>'draft','version'=>$expectedVersion+1,'currentStep'=>$step,'answers'=>$answers];
        });
    }

    /** @return array<string,mixed> */
    public function submit(string $organisationId,string $userId,string $briefId,int $expectedVersion): array
    {
        return $this->transaction(function()use($organisationId,$userId,$briefId,$expectedVersion):array{
            $brief=$this->lock($organisationId,$userId,$briefId);
            if($brief['status']!=='draft')throw new BriefException('brief_locked');
            if((int)$brief['version']!==$expectedVersion)throw new BriefException('stale_brief_version');
            $answers=json_decode((string)$brief['answers'],true,32,JSON_THROW_ON_ERROR);if(!is_array($answers))$answers=[];$this->validateAnswers($answers,true);
            $interpretation=$this->interpret($answers);$newVersion=$expectedVersion+1;
            $this->pdo->prepare("UPDATE guided_briefs SET status='submitted',version=:version,current_step=8,submitted_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND version=:expected")->execute(['version'=>$newVersion,'id'=>$briefId,'expected'=>$expectedVersion]);
            $this->pdo->prepare('INSERT INTO brief_interpretations(id,organisation_id,brief_id,brief_version,ruleset_version,interpretation,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:brief_id),:brief_version,\'guided-brief-rules-v1\',:interpretation,UNHEX(:user_id))')->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'brief_id'=>$briefId,'brief_version'=>$newVersion,'interpretation'=>json_encode($interpretation,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),'user_id'=>$userId]);
            $this->audit->append($organisationId,$userId,'guided_brief.submitted','guided_brief',$briefId,['version'=>$newVersion,'advisoryOnly'=>true]);
            return ['id'=>$briefId,'title'=>$brief['title'],'status'=>'submitted','version'=>$newVersion,'currentStep'=>8,'answers'=>$answers,'interpretation'=>$interpretation];
        });
    }

    /** @return array<string,mixed> */
    public function assignToProject(string $organisationId,string $userId,string $role,string $briefId,string $projectId,int $expectedVersion): array
    {
        return $this->transaction(function()use($organisationId,$userId,$role,$briefId,$projectId,$expectedVersion):array{
            $brief=$this->lock($organisationId,$userId,$briefId);
            if($brief['status']!=='submitted')throw new BriefException('brief_locked');
            if((int)$brief['version']!==$expectedVersion)throw new BriefException('stale_brief_version');
            $project=$this->pdo->prepare('SELECT COUNT(*) FROM projects WHERE id=UNHEX(:project_id) AND organisation_id=UNHEX(:organisation_id)');$project->execute(['project_id'=>$projectId,'organisation_id'=>$organisationId]);if((int)$project->fetchColumn()!==1)throw new BriefException('project_not_found');
            $answers=json_decode((string)$brief['answers'],true,32,JSON_THROW_ON_ERROR);$interpretation=$this->latestInterpretation($organisationId,$briefId);
            $recordId=(new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId,$userId,$projectId,$role),ProjectPhase::Onboarding,'guided_brief','project_brief',(string)$brief['title'],'approved',['briefId'=>$briefId,'briefVersion'=>$expectedVersion,'answers'=>$answers,'interpretation'=>$interpretation],['required'=>true,'approvedBy'=>$userId]);
            $newVersion=$expectedVersion+1;$this->pdo->prepare("UPDATE guided_briefs SET project_id=UNHEX(:project_id),status='converted',version=:version,converted_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND version=:expected")->execute(['project_id'=>$projectId,'version'=>$newVersion,'id'=>$briefId,'expected'=>$expectedVersion]);
            $this->audit->append($organisationId,$userId,'guided_brief.converted','guided_brief',$briefId,['projectId'=>$projectId,'projectRecordId'=>$recordId,'version'=>$newVersion]);
            return ['id'=>$briefId,'projectId'=>$projectId,'projectRecordId'=>$recordId,'status'=>'converted','version'=>$newVersion];
        });
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(string $organisationId,string $userId):array
    {
        $this->assertMember($organisationId,$userId);
        $statement=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) project_id,title,answers,current_step,status,version,submitted_at,converted_at,created_at,updated_at FROM guided_briefs WHERE organisation_id=UNHEX(:organisation_id) AND created_by=UNHEX(:user_id) ORDER BY updated_at DESC,id');
        $statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);$rows=[];
        foreach($statement as $row){$answers=json_decode((string)$row['answers'],true,32,JSON_THROW_ON_ERROR);$rows[]=['id'=>$row['id'],'projectId'=>is_string($row['project_id'])?$row['project_id']:null,'title'=>$row['title'],'answers'=>is_array($answers)?$answers:[],'currentStep'=>(int)$row['current_step'],'status'=>$row['status'],'version'=>(int)$row['version'],'submittedAt'=>$row['submitted_at'],'convertedAt'=>$row['converted_at'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at']];}
        return $rows;
    }

    /** @return array<string,mixed> */
    private function lock(string $organisationId,string $userId,string $briefId): array
    {
        $statement=$this->pdo->prepare('SELECT title,answers,current_step,status,version FROM guided_briefs WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND created_by=UNHEX(:user_id) FOR UPDATE');$statement->execute(['id'=>$briefId,'organisation_id'=>$organisationId,'user_id'=>$userId]);$row=$statement->fetch();if(!is_array($row))throw new BriefException('brief_not_found');return $row;
    }

    /** @param array<string,mixed> $answers */
    private function validateAnswers(array $answers,bool $complete): void
    {
        if(isset($answers['intent'])&&(!is_string($answers['intent'])||!in_array($answers['intent'],self::INTENTS,true)))throw new BriefException('invalid_brief_intent');
        if(isset($answers['goal'])&&(!is_string($answers['goal'])||mb_strlen(trim($answers['goal']))>4000))throw new BriefException('invalid_brief_goal');
        foreach(['hasExistingPlans','workAlreadyExists'] as $key)if(isset($answers[$key])&&!is_bool($answers[$key]))throw new BriefException('invalid_brief_answer');
        if(isset($answers['urgency'])&&(!is_string($answers['urgency'])||!in_array($answers['urgency'],self::URGENCY,true)))throw new BriefException('invalid_brief_urgency');
        if(isset($answers['budgetBand'])&&(!is_string($answers['budgetBand'])||!in_array($answers['budgetBand'],self::BUDGET,true)))throw new BriefException('invalid_budget_band');
        if(isset($answers['supportNeeds'])){if(!is_array($answers['supportNeeds'])||$answers['supportNeeds']===[])throw new BriefException('invalid_support_needs');foreach($answers['supportNeeds'] as $need)if(!is_string($need)||!in_array($need,self::SUPPORT,true))throw new BriefException('invalid_support_needs');}
        if($complete){foreach(['intent','goal','hasExistingPlans','workAlreadyExists','urgency','budgetBand','supportNeeds'] as $key)if(!array_key_exists($key,$answers))throw new BriefException('brief_incomplete');if(trim((string)$answers['goal'])==='')throw new BriefException('brief_incomplete');}
    }

    /** @param array<string,mixed> $answers @return array<string,mixed> */
    private function interpret(array $answers): array
    {
        $needs=['architect'];$support=$answers['supportNeeds'];
        if(in_array('construction_pricing',$support,true))$needs[]='quantity_surveyor';
        if(in_array('full_delivery',$support,true))$needs[]='contractor';
        if($answers['intent']==='plans_approved'||in_array('approvals',$support,true))$needs[]='town_planner_review_if_land_use_triggered';
        $approvals=[];if(in_array('plans',$support,true)||in_array('approvals',$support,true))$approvals[]='municipal_plan_approval_likely';if($answers['workAlreadyExists'])$approvals[]='existing_work_status_check';
        $risks=[];if($answers['workAlreadyExists'])$risks[]='existing_work_requires_assessment';if(!$answers['hasExistingPlans'])$risks[]='existing_plans_unavailable';if($answers['urgency']==='urgent')$risks[]='urgent_timeline_requires_validation';if(in_array($answers['budgetBand'],['unknown','needs_guidance'],true))$risks[]='budget_requires_definition';
        return ['clientFriendlySummary'=>trim((string)$answers['goal']),'possibleProjectRoute'=>'brief_review_then_verified_professional_invitation','likelyProfessionalRequirements'=>array_values(array_unique($needs)),'likelyApprovalRequirements'=>$approvals,'riskFlags'=>$risks,'suggestedNextAction'=>'Invite and independently verify an eligible built-environment professional.','advisoryOnly'=>true,'limitations'=>'Rule-based guidance only; appointed professionals must verify scope, approvals and statutory requirements.'];
    }

    /** @return array<string,mixed> */
    private function latestInterpretation(string $organisationId,string $briefId): array
    {
        $statement=$this->pdo->prepare('SELECT interpretation FROM brief_interpretations WHERE organisation_id=UNHEX(:organisation_id) AND brief_id=UNHEX(:brief_id) ORDER BY brief_version DESC LIMIT 1');$statement->execute(['organisation_id'=>$organisationId,'brief_id'=>$briefId]);$json=$statement->fetchColumn();if(!is_string($json))throw new BriefException('brief_interpretation_missing');$value=json_decode($json,true,32,JSON_THROW_ON_ERROR);return is_array($value)?$value:[];
    }
    private function assertMember(string $organisationId,string $userId):void{$statement=$this->pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='active'");$statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);if((int)$statement->fetchColumn()!==1)throw new BriefException('membership_not_found');}
    private function transaction(callable $operation):mixed{$this->pdo->beginTransaction();try{$result=$operation();$this->pdo->commit();return $result;}catch(Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $exception;}}
}

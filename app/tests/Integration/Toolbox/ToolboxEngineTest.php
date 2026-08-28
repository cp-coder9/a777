<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Toolbox;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Toolbox\ToolDefinition;
use Architex\Platform\Toolbox\ToolDefinitionRegistry;
use Architex\Platform\Toolbox\ToolException;
use Architex\Platform\Toolbox\ToolboxEngine;
use Architex\Tests\Support\MariaDbTestCase;

final class ToolboxEngineTest extends MariaDbTestCase
{
    public function testRunsConcreteDefinitionWithHistoryExportsAndIssuedSha256Snapshot(): void
    {
        [$organisationId,$userId,$projectId] = $this->seedTenant();
        $engine = $this->engine();
        $completed = $engine->run($organisationId,$userId,'owner','area_check',['length'=>8,'width'=>5],['mode'=>'none'],false);
        self::assertSame('completed',$completed['status']);
        self::assertSame(40.0,$completed['output']['area']);
        self::assertNull($completed['auditHash']);
        self::assertCount(3,$engine->exports($organisationId,$userId,$completed['id']));

        $issued = $engine->run($organisationId,$userId,'owner','area_check',['length'=>10,'width'=>4],['mode'=>'internal_project','projectId'=>$projectId,'projectName'=>'Toolbox project'],true,'Reviewed and issued by the responsible professional');
        self::assertSame('issued',$issued['status']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued['auditHash']);
        self::assertSame(2,$engine->runCount($organisationId,$userId,'area_check'));
        self::assertSame($issued['auditHash'],$engine->history($organisationId,$userId,10)[0]['auditHash']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE record_type='tool_run'")->fetchColumn());
    }

    public function testUnknownToolsRolesAssignmentsAndIssuedRevisionFlowFailClosed(): void
    {
        [$organisationId,$userId] = $this->seedTenant();
        $engine = $this->engine();
        foreach([
            fn()=>$engine->run($organisationId,$userId,'owner','missing',[],['mode'=>'none'],false),
            fn()=>$engine->run($organisationId,$userId,'client','area_check',['length'=>1,'width'=>1],['mode'=>'none'],false),
            fn()=>$engine->run($organisationId,$userId,'owner','area_check',['length'=>1,'width'=>1],['mode'=>'external_reference'],false),
        ] as $operation){try{$operation();self::fail('Toolbox request should fail closed.');}catch(ToolException){self::assertTrue(true);}}

        $issued=$engine->run($organisationId,$userId,'owner','area_check',['length'=>2,'width'=>3],['mode'=>'external_reference','projectName'=>'Outside project','externalReference'=>'EXT-7'],true,'Professional issue confirmation');
        $revision=$engine->revise($organisationId,$userId,'owner',$issued['id'],['length'=>3,'width'=>3],['mode'=>'external_reference','projectName'=>'Outside project','externalReference'=>'EXT-7'],'Corrected dimensions and re-issued');
        self::assertSame($issued['id'],$revision['supersedesRunId']);
        self::assertNotSame($issued['auditHash'],$revision['auditHash']);
        self::assertSame('superseded',$engine->get($organisationId,$userId,$issued['id'])['status']);
    }

    public function testEngineeringIssueRequiresExactHumanProfessionalConfirmation(): void
    {
        [$organisationId,$userId,$projectId] = $this->seedTenant();
        $engine = $this->engine();
        $baseInput = ['value'=>12];

        foreach ([
            $baseInput,
            $baseInput + ['professionalConfirmation'=>['confirmed'=>false,'role'=>'structural_engineer','responsibilityStatement'=>'Reviewed.']],
            $baseInput + ['professionalConfirmation'=>['confirmed'=>true,'role'=>'architect','responsibilityStatement'=>'Reviewed.']],
            $baseInput + ['professionalConfirmation'=>['confirmed'=>true,'role'=>'structural_engineer','responsibilityStatement'=>'']],
        ] as $input) {
            try {
                $engine->run($organisationId,$userId,'owner','engineering_check',$input,['mode'=>'internal_project','projectId'=>$projectId],true,'Responsible professional reviewed this exact evidence.');
                self::fail('Engineering issue must fail without exact explicit professional confirmation.');
            } catch (ToolException $exception) {
                self::assertContains($exception->reason,['professional_confirmation_required','professional_role_mismatch']);
            }
        }

        $input=$baseInput+['professionalConfirmation'=>['confirmed'=>true,'role'=>'structural_engineer','responsibilityStatement'=>'I reviewed the exact inputs, assumptions, formula and result limitations.']];
        try{$engine->run($organisationId,$userId,'owner','engineering_check',$input,['mode'=>'internal_project','projectId'=>$projectId],true,'Responsible professional reviewed this exact evidence.');self::fail('A self-claimed role must not authorize engineering issue.');}catch(ToolException$exception){self::assertSame('professional_authority_not_verified',$exception->reason);}
        $this->seedProfessionalAuthority($organisationId,$userId,'structural_engineer','professional_registration');
        $issued=$engine->run($organisationId,$userId,'owner','engineering_check',$input,['mode'=>'internal_project','projectId'=>$projectId],true,'Responsible professional reviewed this exact evidence.');
        self::assertSame('issued',$issued['status']);
        self::assertSame('structural_engineer',$issued['output']['professionalRoleRequired']);
        self::assertSame($input['professionalConfirmation'],$issued['input']['professionalConfirmation']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued['auditHash']);
    }

    private function engine(): ToolboxEngine
    {
        $registry=new ToolDefinitionRegistry();
        $registry->register(new ToolDefinition('area_check','Area check','1.2.0',['owner','admin'],'compliance','/toolbox/standalone/area-check',['area','geometry','measurement'],['length'=>'positive_number','width'=>'positive_number'],['area'=>'number'],true,false,true,['ProjectRecord','AuditTrail'],static function(array $input):array{
            $length=$input['length']??null;$width=$input['width']??null;if(!is_numeric($length)||!is_numeric($width)||(float)$length<=0||(float)$width<=0)throw new ToolException('invalid_tool_input');return['area'=>(float)$length*(float)$width,'unit'=>'m2','advisoryOnly'=>true];
        }));
        $registry->register(new ToolDefinition('engineering_check','Engineering check','1.0.0',['owner','admin','member'],'engineering','/toolbox/standalone/engineering-check',['engineering','calculation','evidence'],['value'=>'number'],['result'=>'number','professionalRoleRequired'=>'string'],true,false,true,['ProjectRecord','AuditTrail'],static fn(array $input):array=>['result'=>(float)($input['value']??0),'professionalRoleRequired'=>'structural_engineer','issueEligible'=>true,'advisoryOnly'=>true]));
        return new ToolboxEngine($this->pdo,$registry);
    }

    /** @return array{string,string,string} */
    private function seedTenant(): array
    {
        $organisationId=Identifier::generate();$userId=Identifier::generate();$projectId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'toolbox@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Toolbox Practice','toolbox-practice','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'TOOL-1','Toolbox project','Client','Tshwane','Erf 11','Residential','','architect',UNHEX(:user_id))")->execute(['id'=>$projectId,'organisation_id'=>$organisationId,'user_id'=>$userId]);
        return[$organisationId,$userId,$projectId];
    }

    private function seedProfessionalAuthority(string$organisationId,string$userId,string$profession,string$kind):void
    {
        $reviewerId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$reviewerId,'email'=>'reviewer-'.substr($reviewerId,0,8).'@example.test']);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'admin','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$reviewerId]);
        $this->pdo->prepare("INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'Verified calculator professional',:profession,'Test profile','[]')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId,'profession'=>$profession]);
        $this->pdo->prepare("INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,popia_consent_at,processing_purpose,retention_until,reviewed_by,reviewed_at) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:kind,'manually_reviewed',UTC_TIMESTAMP(6),'Tool issue authority test',DATE_ADD(UTC_DATE(),INTERVAL 1 YEAR),UNHEX(:reviewer_id),UTC_TIMESTAMP(6))")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId,'kind'=>$kind,'reviewer_id'=>$reviewerId]);
    }
}

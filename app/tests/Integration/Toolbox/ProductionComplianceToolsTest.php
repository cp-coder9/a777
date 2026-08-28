<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Toolbox;

use Architex\Platform\Compliance\ComplianceCalculatorService;
use Architex\Platform\Compliance\ComplianceRuleRegistry;
use Architex\Platform\Fees\FeeCalculatorService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Toolbox\ProductionToolRegistry;
use Architex\Platform\Toolbox\ToolException;
use Architex\Platform\Toolbox\ToolboxEngine;
use Architex\Tests\Support\MariaDbTestCase;

final class ProductionComplianceToolsTest extends MariaDbTestCase
{
    public function testRegistersFourToolsBlocksSeedIssueAndIssuesVerifiedEvidence(): void
    {
        [$organisationId,$ownerId,$projectId]=$this->seedTenant();$rules=new ComplianceRuleRegistry($this->pdo);$seed=$rules->ensureImplementationSeed($organisationId,$ownerId,'owner');$calculators=new ComplianceCalculatorService($rules);$registry=(new ProductionToolRegistry(new FeeCalculatorService($this->pdo),$calculators))->create();$engine=new ToolboxEngine($this->pdo,$registry);
        foreach([
            'fenestration_xa_calculator'=>'/toolbox/standalone/fenestration-xa-calculator',
            'r_value_thermal_calculator'=>'/toolbox/standalone/r-value-thermal-calculator',
            'sans_forms_checklist_builder'=>'/toolbox/standalone/sans-forms-checklist-builder',
            'drawing_compliance_precheck'=>'/toolbox/standalone/drawing-compliance-precheck',
        ]as$id=>$route)self::assertSame($route,$registry->required($id)->route);

        $input=$this->fenestrationInput($seed['id']);$completed=$engine->run($organisationId,$ownerId,'owner','fenestration_xa_calculator',$input,['mode'=>'none'],false);
        self::assertSame('completed',$completed['status']);self::assertFalse($completed['output']['issueEligible']);self::assertNull($completed['auditHash']);
        try{$engine->run($organisationId,$ownerId,'owner','fenestration_xa_calculator',$input,['mode'=>'internal_project','projectId'=>$projectId],true,'Responsible professional review.');self::fail('Review-required seed rules must never be formally issued.');}catch(ToolException$exception){self::assertSame('tool_output_not_issue_eligible',$exception->reason);}

        $verified=$rules->verify($organisationId,$ownerId,'owner',$rules->createVersion($organisationId,$ownerId,'owner',['ruleKey'=>'sans_10400_xa','version'=>'licensed-local-test-1','sourceReference'=>'Disposable-test reviewed source','effectiveDate'=>'2026-08-28','rules'=>$seed['rules']])['id'],true,'Disposable integration-test human verification.');
        $input['ruleVersionId']=$verified['id'];$issued=$engine->run($organisationId,$ownerId,'owner','fenestration_xa_calculator',$input,['mode'=>'internal_project','projectId'=>$projectId],true,'Responsible professional review completed.');
        self::assertSame('issued',$issued['status']);self::assertTrue($issued['output']['issueEligible']);self::assertSame($verified['id'],$issued['output']['sourceRuleVersionId']);self::assertSame($verified['payloadSha256'],$issued['output']['sourcePayloadSha256']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$issued['auditHash']);self::assertNotEmpty($issued['output']['actionCards']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='toolbox' AND record_type='tool_run'")->fetchColumn());self::assertSame(count($issued['output']['actionCards']),(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type='compliance_action'")->fetchColumn());self::assertSame(3,$engine->runCount($organisationId,$ownerId,'fenestration_xa_calculator'));
    }

    /** @return array<string,mixed> */
    private function fenestrationInput(string$ruleVersionId):array{return['ruleVersionId'=>$ruleVersionId,'project'=>['projectName'=>'Tool Project','reference'=>'TOOL-CMP','climateZone'=>4,'buildingType'=>'residential','storeys'=>1,'floorArea'=>100],'elements'=>[['id'=>'W01','storey'=>1,'room'=>'Living','orientation'=>'E','frameType'=>'thermal','glassArea'=>20,'wallArea'=>100,'uValue'=>3.0,'shgc'=>0.6,'shadingFactor'=>0,'description'=>'East window']]];}

    /** @return array{string,string,string} */
    private function seedTenant():array{$organisationId=Identifier::generate();$ownerId=Identifier::generate();$projectId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'production-compliance@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Production Compliance',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'production-compliance-'.substr($organisationId,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),'CMP-1','Tool Project','Client','Tshwane','Erf 1','Residential','','architect',UNHEX(:user))")->execute(['id'=>$projectId,'organisation'=>$organisationId,'user'=>$ownerId]);return[$organisationId,$ownerId,$projectId];}
}

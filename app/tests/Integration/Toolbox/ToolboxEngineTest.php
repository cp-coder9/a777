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

    private function engine(): ToolboxEngine
    {
        $registry=new ToolDefinitionRegistry();
        $registry->register(new ToolDefinition('area_check','Area check','1.2.0',['owner','admin'],'compliance','/toolbox/standalone/area-check',['area','geometry','measurement'],['length'=>'positive_number','width'=>'positive_number'],['area'=>'number'],true,false,true,['ProjectRecord','AuditTrail'],static function(array $input):array{
            $length=$input['length']??null;$width=$input['width']??null;if(!is_numeric($length)||!is_numeric($width)||(float)$length<=0||(float)$width<=0)throw new ToolException('invalid_tool_input');return['area'=>(float)$length*(float)$width,'unit'=>'m2','advisoryOnly'=>true];
        }));
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
}

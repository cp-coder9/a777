<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Toolbox;

use Architex\Platform\Fees\FeeCalculatorService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Toolbox\ProductionToolRegistry;
use Architex\Platform\Toolbox\StandaloneRecordToolService;
use Architex\Platform\Toolbox\ToolException;
use Architex\Platform\Toolbox\ToolboxEngine;
use Architex\Tests\Support\MariaDbTestCase;

final class StandaloneRemainingToolsTest extends MariaDbTestCase
{
    public function testRemainingPackToolsPersistVersionedRunsExportsAndFailClosedOnIssue(): void
    {
        [$organisationId,$userId]=$this->seedTenant();$registry=(new ProductionToolRegistry(new FeeCalculatorService($this->pdo),records:new StandaloneRecordToolService()))->create();$engine=new ToolboxEngine($this->pdo,$registry);
        self::assertCount(35,$registry->forRole('owner'));
        self::assertSame('/toolbox/standalone/rfi-generator',$registry->required('rfi_generator')->route);
        self::assertSame('/toolbox/standalone/workforce-timesheet',$registry->required('workforce_timesheet')->route);

        $rfi=$engine->run($organisationId,$userId,'owner','rfi_generator',['reference'=>'RFI-MDB-1','subject'=>'Waterproofing termination','question'=>'Confirm the termination detail.','requiredBy'=>'2026-09-04','details'=>'Human-entered conflict between A-412 and specification section 7.'],['mode'=>'none'],false);
        self::assertSame('completed',$rfi['status']);self::assertSame('rfi',$rfi['output']['recordType']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$rfi['output']['recordChecksum']);self::assertCount(3,$engine->exports($organisationId,$userId,$rfi['id']));

        $timesheet=$engine->run($organisationId,$userId,'owner','workforce_timesheet',['employee'=>'MariaDB Worker','periodStart'=>'2026-08-24','periodEnd'=>'2026-08-30','regularHours'=>40,'overtimeHours'=>5,'hourlyRate'=>250],['mode'=>'external_reference','projectName'=>'External site','externalReference'=>'EXT-WF-1'],false);
        self::assertSame(1_187_500,$timesheet['output']['totalCents']);self::assertSame('EXT-WF-1',$timesheet['assignment']['externalReference']);self::assertSame(2,$engine->runCount($organisationId,$userId,'workforce_timesheet')+$engine->runCount($organisationId,$userId,'rfi_generator'));

        try{$engine->run($organisationId,$userId,'owner','rfi_generator',['reference'=>'RFI-ISSUE','subject'=>'Must remain draft','question'=>'Question','requiredBy'=>'2026-09-04','details'=>'Human-entered details.'],['mode'=>'none'],true,'Attempt issue.');self::fail('Human-entered generic records must not be issue eligible.');}catch(ToolException$exception){self::assertSame('tool_output_not_issue_eligible',$exception->reason);}
    }

    /** @return array{string,string} */
    private function seedTenant():array
    {
        $organisationId=Identifier::generate();$userId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'standalone-remaining@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Standalone Remaining',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'standalone-remaining-'.substr($organisationId,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$userId]);return[$organisationId,$userId];
    }
}

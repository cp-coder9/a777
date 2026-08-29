<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Toolbox;

use Architex\Platform\Fees\FeeCalculatorService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Toolbox\ProductionToolRegistry;
use Architex\Platform\Toolbox\StandaloneUtilityService;
use Architex\Platform\Toolbox\ToolException;
use Architex\Platform\Toolbox\ToolboxEngine;
use Architex\Tests\Support\MariaDbTestCase;

final class StandalonePriorityToolsTest extends MariaDbTestCase
{
    public function testBoqAndSiteDiaryCreateDeterministicAssignableMariaDbRuns(): void
    {
        [$organisationId,$userId]=$this->seedTenant();
        $registry=(new ProductionToolRegistry(new FeeCalculatorService($this->pdo),standalone:new StandaloneUtilityService()))->create();
        $engine=new ToolboxEngine($this->pdo,$registry);
        self::assertSame('/toolbox/standalone/boq-takeoff',$registry->required('boq_takeoff')->route);
        self::assertSame('/toolbox/standalone/site-diary-entry',$registry->required('site_diary_entry')->route);

        $boq=$engine->run($organisationId,$userId,'owner','boq_takeoff',['title'=>'External works takeoff','currency'=>'ZAR','contingencyPercent'=>10,'items'=>[
            ['description'=>'Concrete','quantity'=>10,'unit'=>'m3','rate'=>100],
            ['description'=>'Formwork','quantity'=>4,'unit'=>'m2','rate'=>50],
        ]],['mode'=>'external_reference','projectName'=>'External works','externalReference'=>'EXT-BOQ-1'],false);
        self::assertSame(120000,$boq['output']['subtotalCents']);
        self::assertSame(12000,$boq['output']['contingencyCents']);
        self::assertSame(132000,$boq['output']['totalCents']);
        self::assertCount(2,$boq['output']['items']);
        self::assertCount(3,$engine->exports($organisationId,$userId,$boq['id']));

        $diary=$engine->run($organisationId,$userId,'owner','site_diary_entry',['date'=>'2026-08-29','location'=>'External site','weather'=>'Human-entered: clear','activities'=>['Set out grid A','Cast bases'],'labourCount'=>8,'plantCount'=>2,'deliveries'=>['Concrete 8m3'],'delays'=>[],'notes'=>'Supervisor record; facts require review.'],['mode'=>'none'],false);
        self::assertSame('2026-08-29',$diary['output']['date']);
        self::assertSame(2,$diary['output']['activityCount']);
        self::assertTrue($diary['output']['humanEntered']);
        self::assertFalse($diary['output']['issueEligible']);

        foreach([
            fn()=>$engine->run($organisationId,$userId,'owner','boq_takeoff',['title'=>'Invalid','items'=>[]],['mode'=>'none'],false),
            fn()=>$engine->run($organisationId,$userId,'owner','site_diary_entry',['date'=>'not-a-date'],['mode'=>'none'],false),
        ]as$operation){try{$operation();self::fail('Priority standalone tools must fail closed.');}catch(ToolException){self::assertTrue(true);}}
    }

    /** @return array{string,string} */
    private function seedTenant(): array
    {
        $organisationId=Identifier::generate();$userId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'standalone-priority@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Standalone Priority',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'standalone-priority-'.substr($organisationId,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$userId]);
        return[$organisationId,$userId];
    }
}

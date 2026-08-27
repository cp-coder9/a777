<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Fees;

use Architex\Platform\Fees\FeeCalculatorService;
use Architex\Platform\Fees\FeeException;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class FeeCalculatorServiceTest extends MariaDbTestCase
{
    public function testOnlyVerifiedVersionedDefinitionCalculatesTransparentDiscountAndTaxBases(): void
    {
        [$organisationId,$userId]=$this->seedTenant();$service=new FeeCalculatorService($this->pdo);
        $source=$service->createSource($organisationId,$userId,'owner',['profession'=>'architect','body'=>'Practice-approved schedule','title'=>'Internal estimating schedule','effectiveDate'=>'2026-08-28','sourceUrl'=>null,'metadata'=>['basis'=>'Practice schedule, not a statutory tariff.']]);
        $definition=$service->createDefinition($organisationId,$userId,'owner',['profession'=>'architect','name'=>'Architecture estimate','version'=>'1.0.0','formulaType'=>'sliding_scale','sourceVersionId'=>$source['id'],'configuration'=>[
            'minimumFee'=>35000,'brackets'=>[['upTo'=>500000,'rate'=>.095],['upTo'=>2500000,'rate'=>.075],['upTo'=>null,'rate'=>.055]],'vatRate'=>.15,
            'complexities'=>['normal'=>1,'high'=>1.25],'categories'=>['standard'=>1,'alteration'=>1.2],'stages'=>[['id'=>'s1','name'=>'Inception','weight'=>.2],['id'=>'s2','name'=>'Design and delivery','weight'=>.8]],
        ]]);
        try{$service->calculate($organisationId,$userId,$definition['id'],[]);self::fail('Draft definitions cannot calculate.');}catch(FeeException $exception){self::assertSame('fee_definition_not_verified',$exception->reason);}
        $service->verifySource($organisationId,$userId,'owner',$source['id']);$service->verifyDefinition($organisationId,$userId,'owner',$definition['id']);
        $result=$service->calculate($organisationId,$userId,$definition['id'],[
            'projectValue'=>1000000,'complexityId'=>'high','categorySplits'=>['standard'=>.5,'alteration'=>.5],
            'selectedStages'=>['s1'=>['applicable'=>true,'reduction'=>0],'s2'=>['applicable'=>true,'reduction'=>.25]],
            'hourlyLines'=>[],'unitLines'=>[],'disbursements'=>[['label'=>'Travel','amount'=>1000]],'statutoryFees'=>[['label'=>'Municipal fee','amount'=>5000]],
            'discount'=>['percentage'=>.10,'reason'=>'Commercial adjustment'],'vatApplicable'=>true,
        ]);
        self::assertSame(116875.0,$result['guidelineProfessionalFee']);self::assertSame(93500.0,$result['stageAdjustedFee']);self::assertSame(9350.0,$result['discountAmount']);self::assertSame(84150.0,$result['professionalFeeAfterDiscount']);self::assertSame(12772.5,$result['vatAmount']);self::assertSame(102922.5,$result['totalInclVat']);self::assertSame($source['id'],$result['source']['id']);self::assertSame('professional_decision_support',$result['classification']);
    }

    public function testRejectsUnbalancedSplitsAndRequiresReasonsForOverridesAndDiscounts(): void
    {
        [$organisationId,$userId]=$this->seedTenant();$service=new FeeCalculatorService($this->pdo);$source=$service->createSource($organisationId,$userId,'owner',['profession'=>'engineer','body'=>'Practice','title'=>'Time schedule','effectiveDate'=>'2026-08-28','metadata'=>[]]);$service->verifySource($organisationId,$userId,'owner',$source['id']);$definition=$service->createDefinition($organisationId,$userId,'owner',['profession'=>'engineer','name'=>'Engineering time estimate','version'=>'1.0.0','formulaType'=>'time_based','sourceVersionId'=>$source['id'],'configuration'=>['vatRate'=>.15,'complexities'=>['normal'=>1],'categories'=>['standard'=>1],'stages'=>[['id'=>'all','name'=>'All services','weight'=>1]]]]);$service->verifyDefinition($organisationId,$userId,'owner',$definition['id']);
        foreach([
            ['categorySplits'=>['standard'=>.8]],
            ['professionalOverride'=>['amount'=>1000,'reason'=>'']],
            ['discount'=>['percentage'=>.1,'reason'=>'']],
        ] as $change){try{$service->calculate($organisationId,$userId,$definition['id'],array_replace(['projectValue'=>0,'complexityId'=>'normal','categorySplits'=>['standard'=>1],'selectedStages'=>['all'=>['applicable'=>true,'reduction'=>0]],'hourlyLines'=>[['label'=>'Engineer','hours'=>10,'rate'=>1500]],'unitLines'=>[],'disbursements'=>[],'statutoryFees'=>[],'vatApplicable'=>false],$change));self::fail('Invalid calculation must be rejected.');}catch(FeeException){self::assertTrue(true);}}
    }

    public function testSupportsEveryPackRequiredFormulaFamily(): void
    {
        [$organisationId,$userId]=$this->seedTenant();$service=new FeeCalculatorService($this->pdo);$source=$service->createSource($organisationId,$userId,'owner',['profession'=>'architect','body'=>'Practice','title'=>'Formula test schedule','effectiveDate'=>'2026-08-28','metadata'=>[]]);$service->verifySource($organisationId,$userId,'owner',$source['id']);
        $expected=['sliding_scale'=>9500.0,'percentage_of_cost'=>5000.0,'stage_apportioned'=>5000.0,'time_based'=>1000.0,'area_unit'=>400.0,'hybrid'=>6400.0];$version=1;
        foreach($expected as$formula=>$amount){$configuration=['vatRate'=>.15,'complexities'=>['normal'=>1],'categories'=>['standard'=>1],'stages'=>[['id'=>'all','name'=>'All','weight'=>1]]];if($formula==='sliding_scale')$configuration+=['minimumFee'=>0,'brackets'=>[['upTo'=>null,'rate'=>.095]]];if(in_array($formula,['percentage_of_cost','stage_apportioned','hybrid'],true))$configuration['percentage']=.05;$definition=$service->createDefinition($organisationId,$userId,'owner',['profession'=>'architect','name'=>$formula,'version'=>'1.0.'.$version++,'formulaType'=>$formula,'sourceVersionId'=>$source['id'],'configuration'=>$configuration]);$service->verifyDefinition($organisationId,$userId,'owner',$definition['id']);$result=$service->calculate($organisationId,$userId,$definition['id'],['projectValue'=>100000,'complexityId'=>'normal','categorySplits'=>['standard'=>1],'selectedStages'=>['all'=>['applicable'=>true,'reduction'=>0]],'hourlyLines'=>[['label'=>'Hours','hours'=>10,'rate'=>100]],'unitLines'=>[['label'=>'Area','quantity'=>2,'unitRate'=>200]],'disbursements'=>[],'statutoryFees'=>[],'vatApplicable'=>false]);self::assertSame($amount,$result['guidelineProfessionalFee'],$formula);}
    }

    /** @return array{string,string} */
    private function seedTenant():array{$organisationId=Identifier::generate();$userId=Identifier::generate();$suffix=substr($organisationId,0,8);$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId,'email'=>'fee-'.$suffix.'@example.test']);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Fee Practice',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'fee-'.$suffix]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation_id'=>$organisationId,'user_id'=>$userId]);return[$organisationId,$userId];}
}

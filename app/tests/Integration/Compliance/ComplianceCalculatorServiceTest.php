<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Compliance;

use Architex\Platform\Compliance\ComplianceCalculatorService;
use Architex\Platform\Compliance\ComplianceException;
use Architex\Platform\Compliance\ComplianceRuleRegistry;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class ComplianceCalculatorServiceTest extends MariaDbTestCase
{
    public function testAllFourCalculatorsProduceStructuredSourceBoundEvidence(): void
    {
        [$organisationId,$ownerId]=$this->seedTenant();$registry=new ComplianceRuleRegistry($this->pdo);$source=$registry->ensureImplementationSeed($organisationId,$ownerId,'owner');$service=new ComplianceCalculatorService($registry);
        $project=['projectName'=>'Compliance House','reference'=>'CMP-1','climateZone'=>4,'buildingType'=>'residential','storeys'=>2,'floorArea'=>200];

        $fenestration=$service->calculate($organisationId,$source['id'],'fenestration_xa_calculator',['project'=>$project,'elements'=>[
            ['id'=>'W01','storey'=>1,'room'=>'Living','orientation'=>'N','frameType'=>'alu','glassArea'=>10,'wallArea'=>100,'uValue'=>2.8,'shgc'=>0.4,'shadingFactor'=>0.5,'description'=>'North window'],
            ['id'=>'W02','storey'=>2,'room'=>'Study','orientation'=>'E','frameType'=>'thermal','glassArea'=>30,'wallArea'=>100,'uValue'=>3.0,'shgc'=>0.6,'shadingFactor'=>0,'description'=>'East window'],
        ]]);
        self::assertSame('fail',$fenestration['verdict']);self::assertSame('pass',$fenestration['results'][0]['verdict']);self::assertSame('fail',$fenestration['results'][1]['verdict']);
        self::assertSame(0.2,$fenestration['results'][0]['effectiveShgc']);self::assertSame(0.1,$fenestration['results'][0]['glazingPercent']);self::assertSame(1,$fenestration['byStorey']['1']['pass']);self::assertSame(1,$fenestration['byStorey']['2']['fail']);self::assertNotEmpty($fenestration['actionCards']);

        $assemblies=$service->calculate($organisationId,$source['id'],'r_value_thermal_calculator',['project'=>$project,'assemblies'=>[
            ['id'=>'R01','type'=>'roof','climateZone'=>4,'area'=>150,'description'=>'Insulated roof','layers'=>[['material'=>'glasswool','thicknessMm'=>150]]],
            ['id'=>'WALL01','type'=>'wall','climateZone'=>4,'area'=>100,'description'=>'Brick wall','layers'=>[['material'=>'brick','thicknessMm'=>110]]],
        ]]);
        self::assertSame('fail',$assemblies['verdict']);self::assertSame(3.75,$assemblies['results'][0]['totalR']);self::assertSame('pass',$assemblies['results'][0]['verdict']);self::assertSame('fail',$assemblies['results'][1]['verdict']);self::assertGreaterThan(0,$assemblies['results'][1]['uValue']);

        $forms=$service->calculate($organisationId,$source['id'],'sans_forms_checklist_builder',['project'=>$project,'occupancyClass'=>'H4','includesCompetentPersons'=>false,'parts'=>['A','XA']]);
        self::assertSame('watch',$forms['verdict']);self::assertSame(['A1','A19','XA'],array_column($forms['results'],'id'));self::assertContains('fenestration report',$forms['requiredEvidence']);self::assertNotEmpty($forms['actionCards']);

        $drawings=$service->calculate($organisationId,$source['id'],'drawing_compliance_precheck',['project'=>$project,'sheets'=>[
            ['number'=>'A-001','title'=>'Site plan','scale'=>null,'revision'=>'P01','hasNorthPoint'=>false,'hasProfessionalBlock'=>false,'hasDimensions'=>false,'hasRoomLabels'=>true,'referencedSheets'=>['A-999']],
        ]]);
        self::assertSame('fail',$drawings['verdict']);self::assertContains('A-001: missing scale',$drawings['actionCards']);self::assertContains('A-001: unresolved sheet reference A-999',$drawings['actionCards']);self::assertStringContainsString('No dimensions inferred',$drawings['limitations']);

        foreach([$fenestration,$assemblies,$forms,$drawings]as$output){self::assertSame($source['version'],$output['sourceVersion']);self::assertSame($source['payloadSha256'],$output['sourcePayloadSha256']);self::assertSame('review_required',$output['sourceStatus']);self::assertFalse($output['issueEligible']);self::assertTrue($output['professionalSignoffRequired']);self::assertFalse($output['municipalApproval']);self::assertTrue($output['advisoryOnly']);}
    }

    public function testInvalidOrInventedInputsFailClosed(): void
    {
        [$organisationId,$ownerId]=$this->seedTenant();$registry=new ComplianceRuleRegistry($this->pdo);$source=$registry->ensureImplementationSeed($organisationId,$ownerId,'owner');$service=new ComplianceCalculatorService($registry);$project=['projectName'=>'House','reference'=>'H-1','climateZone'=>4,'buildingType'=>'residential','storeys'=>1,'floorArea'=>100];
        foreach([
            fn()=> $service->calculate($organisationId,$source['id'],'fenestration_xa_calculator',['project'=>$project,'elements'=>[['id'=>'W1','storey'=>1,'room'=>'Room','orientation'=>'UP','frameType'=>'alu','glassArea'=>1,'wallArea'=>10,'uValue'=>2,'shgc'=>0.4,'shadingFactor'=>0,'description'=>'Window']]]),
            fn()=> $service->calculate($organisationId,$source['id'],'r_value_thermal_calculator',[
                'project'=>$project,
                'assemblies'=>[[
                    'id'=>'R1','type'=>'roof','climateZone'=>4,'area'=>10,'description'=>'Unknown',
                    'layers'=>[['material'=>'invented','thicknessMm'=>20]],
                ]],
            ]),
            fn()=> $service->calculate($organisationId,$source['id'],'sans_forms_checklist_builder',['project'=>$project,'occupancyClass'=>'H4','includesCompetentPersons'=>false,'parts'=>['INVENTED']]),
            fn()=> $service->calculate($organisationId,$source['id'],'drawing_compliance_precheck',['project'=>$project,'sheets'=>[]]),
            fn()=> $service->calculate($organisationId,$source['id'],'unknown_tool',['project'=>$project]),
        ]as$operation){try{$operation();self::fail('Invalid compliance input should fail closed.');}catch(ComplianceException){self::assertTrue(true);}}
    }

    public function testRefuseCalculatorProducesSourceBoundCapacityAreaAndFacilityEvidence(): void
    {
        [$organisationId,$ownerId]=$this->seedTenant();$registry=new ComplianceRuleRegistry($this->pdo);$source=$registry->ensureRefuseImplementationSeed($organisationId,$ownerId,'owner');$service=new ComplianceCalculatorService($registry);
        $input=['project'=>['projectName'=>'Refuse Court','reference'=>'REF-1','climateZone'=>4,'buildingType'=>'residential','storeys'=>3,'floorArea'=>1800],
            'occupancyUse'=>'residential_dwelling','quantity'=>12,'collectionIntervalDays'=>7,'contingencyPercent'=>10,'binType'=>'standard_1100l','proposedStorageAreaM2'=>13,
            'facilities'=>['collectionVehicleAccess'=>true,'washdownWater'=>true,'drainage'=>true,'ventilation'=>true,'wasteSeparation'=>true],
            'assumptions'=>'Twelve dwellings; municipal service frequency requires confirmation.'];
        $result=$service->calculate($organisationId,$source['id'],'refuse_area_calculator',$input);
        self::assertSame('pass',$result['verdict']);
        self::assertSame(10080.0,$result['calculation']['baseVolumeLitres']);
        self::assertSame(11088.0,$result['calculation']['designVolumeLitres']);
        self::assertSame(11,$result['calculation']['requiredBinCount']);
        self::assertSame(12.38,$result['calculation']['requiredStorageAreaM2']);
        self::assertSame(0.62,$result['calculation']['areaSurplusM2']);
        self::assertSame([],$result['actionCards']);
        self::assertSame('architect_or_civil_engineer',$result['professionalRoleRequired']);
        self::assertSame($source['payloadSha256'],$result['sourcePayloadSha256']);
        self::assertFalse($result['issueEligible']);self::assertFalse($result['municipalApproval']);self::assertTrue($result['advisoryOnly']);

        $input['proposedStorageAreaM2']=10;$input['facilities']['drainage']=false;
        $failed=$service->calculate($organisationId,$source['id'],'refuse_area_calculator',$input);
        self::assertSame('fail',$failed['verdict']);
        self::assertContains('Increase the proposed refuse storage area by at least 2.38 m2.',$failed['actionCards']);
        self::assertContains('Confirm and record a compliant drainage arrangement.',$failed['actionCards']);

        foreach([0,8]as$days){$input['collectionIntervalDays']=$days;try{$service->calculate($organisationId,$source['id'],'refuse_area_calculator',$input);self::fail('Unsupported collection interval must fail closed.');}catch(ComplianceException$exception){self::assertSame('invalid_refuse_input',$exception->reason);}}
    }

    /** @return array{string,string} */
    private function seedTenant():array
    {
        $organisationId=Identifier::generate();$ownerId=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'calculator-owner@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$ownerId]);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Calculator Practice',:slug,'active')")->execute(['id'=>$organisationId,'slug'=>'calculator-'.substr($organisationId,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$ownerId]);return[$organisationId,$ownerId];
    }
}

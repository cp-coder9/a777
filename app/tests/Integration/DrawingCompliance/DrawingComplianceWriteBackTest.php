<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\DrawingCompliance;

use Architex\Platform\DrawingCompliance\DrawingComplianceException;
use Architex\Platform\DrawingCompliance\DrawingComplianceService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class DrawingComplianceWriteBackTest extends MariaDbTestCase
{
    public function testExplicitProjectSaveCreatesGovernedRecordsBlockersAndAuditWithoutChangingAuthority(): void
    {
        [$organisation,$owner,$project]=$this->seedProject('drawing-writeback');
        [$otherOrganisation,$otherOwner]=$this->seedOrganisation('drawing-outsider');
        $service=new DrawingComplianceService($this->pdo);
        $service->evaluateBoundaryWall(['unitType'=>'solid','thicknessMm'=>90,'heightM'=>1.1,'earthRetained'=>false]);
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='drawing_compliance'")->fetchColumn());

        $cases=[
            ['Fail review',['unitType'=>'solid','thicknessMm'=>90,'heightM'=>1.1,'earthRetained'=>false],'fail'],
            ['Watch review',['unitType'=>'solid','thicknessMm'=>140,'heightM'=>1.0,'earthRetained'=>true],'watch'],
            ['Needs input review',['unitType'=>'solid','thicknessMm'=>90,'earthRetained'=>false],'needs_input'],
        ];
        $saved=[];
        foreach($cases as[$title,$wall,$status]){
            $row=$service->saveReview($organisation,$owner,['mode'=>'project','projectId'=>$project,'title'=>$title,'drawingRevision'=>'A101-P03','responsibleRole'=>'architect','boundaryWall'=>$wall,'buildingDescription'=>'Dwelling boundary wall','availableDrawingTypes'=>['general_notes_title_block','site_plan','floor_plans','roof_plan','elevations','sections','area_zoning_parking_schedule'],'siteContext'=>array_fill_keys(['municipality','erfNumber','zoning','landUseSchemeName','currentUse','proposedUse','parkingProvided','coverage','FAR','heightStoreys','buildingLinesChecked'],'confirmed')]);
            self::assertSame($status,$row['review']['boundaryWall']['status']);$saved[]=$row;
        }
        self::assertSame(3,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='drawing_compliance' AND record_type='drawing_compliance_review'")->fetchColumn());
        self::assertSame(3,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type='drawing_compliance_blocker'")->fetchColumn());
        self::assertSame(3,(int)$this->pdo->query("SELECT COUNT(*) FROM workflow_events WHERE source_module='drawing_compliance' AND event_type='municipal_readiness_compliance_blocker'")->fetchColumn());
        self::assertSame(3,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action='drawing_compliance.review_saved'")->fetchColumn());
        self::assertSame('pending',$saved[0]['professionalReviewStatus']);
        self::assertSame('architect',$saved[0]['responsibleRole']);
        self::assertSame('A101-P03',$saved[0]['drawingRevision']);
        self::assertSame($saved[0]['snapshotSha256'],$service->review($organisation,$owner,$saved[0]['id'])['snapshotSha256']);

        try{$service->saveReview($otherOrganisation,$otherOwner,['mode'=>'project','projectId'=>$project,'title'=>'Cross tenant attempt','drawingRevision'=>'P01']);self::fail('Cross-tenant project binding must fail.');}
        catch(DrawingComplianceException$exception){self::assertSame('drawing_compliance_project_not_found',$exception->getMessage());}
    }

    /** @return array{string,string,string} */
    private function seedProject(string$slug):array{[$organisation,$owner]=$this->seedOrganisation($slug);$project=Identifier::generate();$this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),:code,:name,'Client','Johannesburg','Erf 10','Residential','Human review required','architect','municipal_submission',UNHEX(:u))")->execute(['p'=>$project,'o'=>$organisation,'code'=>'DC-'.substr($project,0,6),'name'=>'Drawing review project','u'=>$owner]);return[$organisation,$owner,$project];}
    /** @return array{string,string} */
    private function seedOrganisation(string$slug):array{$organisation=Identifier::generate();$owner=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$owner,'email'=>$slug.'@example.test']);$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$organisation,'name'=>$slug,'slug'=>$slug.'-'.substr($organisation,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$owner]);return[$organisation,$owner];}
}

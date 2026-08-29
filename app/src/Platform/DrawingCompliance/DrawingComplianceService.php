<?php

declare(strict_types=1);

namespace Architex\Platform\DrawingCompliance;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use Throwable;

final readonly class DrawingComplianceService
{
    private const SOURCE = [
        'key'=>'pack20_revf','version'=>'1.6.0-RevF','title'=>'Pack 20 SANS/NBR codified toolbox illustrative source',
        'url'=>'https://mwo-aec.github.io/sans-codified-poc/','status'=>'illustrative_poc_until_verified',
        'sha256'=>'18f77490c2af8e1ed6fcd79fb6cc66dc3c2dcfe02845fe566574fedb89820e23','productionRelianceAllowed'=>false,
    ];

    public function __construct(private ?PDO $pdo=null){}

    /** @return list<array<string,mixed>> */
    public function sourceRegistry():array
    {
        if($this->pdo===null)return[self::SOURCE];
        $rows=$this->pdo->query("SELECT source_key `key`,version,title,source_url url,source_status status,source_sha256 sha256,production_reliance_allowed FROM drawing_compliance_sources ORDER BY created_at DESC")->fetchAll();
        return array_map(static fn(array$row):array=>['key'=>$row['key'],'version'=>$row['version'],'title'=>$row['title'],'url'=>$row['url'],'status'=>$row['status'],'sha256'=>$row['sha256'],'productionRelianceAllowed'=>(bool)$row['production_reliance_allowed']],$rows);
    }

    /** @param array<string,mixed>$input @return array<string,mixed> */
    public function evaluateBoundaryWall(array$input):array
    {
        $missing=[];foreach(['unitType','thicknessMm','heightM','earthRetained']as$key)if(!array_key_exists($key,$input)||$input[$key]===null||$input[$key]==='')$missing[]=$key;
        if($missing!==[])return$this->finding('needs_input','Missing required boundary-wall parameters.',['missing'=>$missing]);
        if($input['earthRetained']===true)return$this->finding('watch','Retaining condition requires a retaining-wall check and competent-person review.',['clauseRef'=>'SANS 10400-K 4.2.4.1']);
        $rows=[
            ['unitType'=>'solid','thicknessMm'=>90,'maxHeightM'=>0.9,'pierRequired'=>false],
            ['unitType'=>'solid','thicknessMm'=>140,'maxHeightM'=>1.1,'pierRequired'=>false],
            ['unitType'=>'solid','thicknessMm'=>140,'maxHeightM'=>1.8,'pierRequired'=>true,'pierSpacingMaxM'=>1.8,'pierSize'=>'600x300'],
            ['unitType'=>'solid','thicknessMm'=>190,'maxHeightM'=>1.4,'pierRequired'=>true,'pierSpacingMaxM'=>2.4,'pierSize'=>'390x390'],
            ['unitType'=>'solid','thicknessMm'=>220,'maxHeightM'=>1.8,'pierRequired'=>true,'pierSpacingMaxM'=>2.7,'pierSize'=>'440x440'],
        ];
        $candidates=array_values(array_filter($rows,static fn(array$row):bool=>$row['unitType']===$input['unitType']&&$row['thicknessMm']===(int)$input['thicknessMm']));
        if($candidates===[])return$this->finding('needs_input','No illustrative row exists for this wall unit and thickness.',['input'=>$input]);
        $withPier=array_key_exists('pierSpacingM',$input)&&$input['pierSpacingM']!==null&&$input['pierSpacingM']!=='';
        $eligible=array_values(array_filter($candidates,static fn(array$row):bool=>$withPier?($row['pierRequired']===true):($row['pierRequired']===false)));
        $row=$eligible[0]??$candidates[0];$trace=['clauseRef'=>'SANS 10400-K 4.2.4.2','tableRef'=>'K-T17 demo pattern','sourceStatus'=>self::SOURCE['status'],'tableRow'=>$row,'input'=>$input];
        if((float)$input['heightM']>(float)$row['maxHeightM'])return$this->finding('fail','Wall height exceeds the illustrative maximum.',$trace);
        if(($row['pierRequired']??false)&&((float)($input['pierSpacingM']??0)>(float)$row['pierSpacingMaxM']))return$this->finding('watch','Wall height passes the illustrative row, but pier spacing exceeds its demo spacing.',$trace);
        return$this->finding('pass','Wall height is within the illustrative row, subject to verified source and professional review.',$trace);
    }

    /** @param array<string,mixed>$input @return array<string,mixed> */
    public function evaluate(array$input):array
    {
        $occupancy=$this->occupancy((string)($input['buildingDescription']??''),(array)($input['seatingCounts']??[]),(array)($input['floorAreas']??[]));
        $package=$this->package((array)($input['availableDrawingTypes']??[]),(bool)($input['sdpRequired']??false));
        $references=$this->references((array)($input['drawingRegister']??[]),(array)($input['references']??[]));
        $landUse=$this->landUse((string)($input['projectDescription']??$input['buildingDescription']??''),(array)($input['siteContext']??[]));
        $boundary=isset($input['boundaryWall'])&&is_array($input['boundaryWall'])?$this->evaluateBoundaryWall($input['boundaryWall']):null;
        $statuses=array_filter([$boundary['status']??null,$package['missingDrawingTypes']===[]?'pass':'needs_input',$references['unresolvedCount']===0?'pass':'needs_input',$landUse['missingInputs']===[]?'watch':'needs_input']);
        $overall=in_array('fail',$statuses,true)?'fail':(in_array('needs_input',$statuses,true)?'needs_input':(in_array('watch',$statuses,true)?'watch':'pass'));
        return['source'=>self::SOURCE,'boundaryWall'=>$boundary,'occupancy'=>$occupancy,'package'=>$package,'crossReferences'=>$references,'landUse'=>$landUse,'overallStatus'=>$overall,'professionalReviewRequired'=>true,'answerContract'=>['requiredSections'=>['drawing_findings','mapped_sans_nbr_references','pass_watch_fail_needs_input','missing_information','professional_review_gate','next_actions'],'noGoPhrases'=>['approved by municipality','professionally certified by AI','guaranteed compliant']],'boundary'=>'This deterministic screen cannot grant or deny municipal approval and is not professional certification. Licensed or officially verified source material and responsible-professional review are required.'];
    }

    /** @param array<string,mixed>$input @return array<string,mixed> */
    public function saveReview(string$organisationId,string$userId,array$input):array
    {
        $pdo=$this->requiredPdo();$this->assertMember($organisationId,$userId);$mode=$input['mode']??null;if(!in_array($mode,['standalone','project'],true))throw new DrawingComplianceException('drawing_compliance_mode_invalid');
        $projectId=$mode==='project'?($input['projectId']??null):null;if($mode==='project')$this->assertProject($organisationId,$projectId);
        $title=$this->text($input['title']??null,220,'drawing_compliance_title_required');$revision=$this->optional($input['drawingRevision']??null,80);$role=$mode==='project'?$this->text($input['responsibleRole']??null,80,'drawing_compliance_responsible_role_required'):null;
        $review=$this->evaluate($input);$snapshot=['source'=>self::SOURCE,'input'=>$input,'review'=>$review];$snapshotJson=$this->json($snapshot);$sha=hash('sha256',$snapshotJson);$id=Identifier::generate();
        $pdo->beginTransaction();
        try{
            $pdo->prepare('INSERT INTO drawing_compliance_reviews(id,organisation_id,user_id,project_id,source_key,source_version,mode,title,drawing_revision,responsible_role,overall_status,input_snapshot,review_snapshot,snapshot_sha256) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),UNHEX(:p),:source_key,:source_version,:mode,:title,:revision,:role,:status,:input,:review,:sha)')->execute(['id'=>$id,'o'=>$organisationId,'u'=>$userId,'p'=>$projectId,'source_key'=>self::SOURCE['key'],'source_version'=>self::SOURCE['version'],'mode'=>$mode,'title'=>$title,'revision'=>$revision,'role'=>$role,'status'=>$review['overallStatus'],'input'=>$this->json($input),'review'=>$this->json($review),'sha'=>$sha]);
            if($mode==='project'&&in_array($review['boundaryWall']['status']??null,['watch','fail','needs_input'],true))$this->writeProjectBlocker($organisationId,$userId,$projectId,$role,$id,$title,$revision,$sha,$review);
            (new AuditService($pdo))->append($organisationId,$userId,'drawing_compliance.review_saved','drawing_compliance_review',$id,['projectId'=>$projectId,'mode'=>$mode,'sourceStatus'=>self::SOURCE['status'],'snapshotSha256'=>$sha,'overallStatus'=>$review['overallStatus'],'professionalReviewStatus'=>'pending','formalApprovalChanged'=>false]);
            $pdo->commit();
        }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();throw$exception;}
        return$this->review($organisationId,$userId,$id);
    }

    /** @return array<string,mixed> */
    public function review(string$organisationId,string$userId,string$id):array
    {
        $statement=$this->requiredPdo()->prepare("SELECT LOWER(HEX(id)) id,mode,title,drawing_revision,responsible_role,overall_status,professional_review_status,input_snapshot,review_snapshot,snapshot_sha256,source_key,source_version,DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s.%fZ') created_at FROM drawing_compliance_reviews WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)");$statement->execute(['id'=>$id,'o'=>$organisationId,'u'=>$userId]);$row=$statement->fetch();if(!is_array($row))throw new DrawingComplianceException('drawing_compliance_review_not_found');
        return['id'=>$row['id'],'mode'=>$row['mode'],'title'=>$row['title'],'drawingRevision'=>$row['drawing_revision'],'responsibleRole'=>$row['responsible_role'],'overallStatus'=>$row['overall_status'],'professionalReviewStatus'=>$row['professional_review_status'],'sourceKey'=>$row['source_key'],'sourceVersion'=>$row['source_version'],'sourceStatus'=>self::SOURCE['status'],'input'=>json_decode($row['input_snapshot'],true,64,JSON_THROW_ON_ERROR),'review'=>json_decode($row['review_snapshot'],true,64,JSON_THROW_ON_ERROR),'snapshotSha256'=>$row['snapshot_sha256'],'createdAt'=>$row['created_at']];
    }

    /** @return array{filename:string,mimeType:string,content:string,sha256:string} */
    public function export(string$organisationId,string$userId,string$id):array
    {
        $content=json_encode($this->review($organisationId,$userId,$id),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        return['filename'=>'drawing-compliance-'.$id.'.json','mimeType'=>'application/json','content'=>$content,'sha256'=>hash('sha256',$content)];
    }

    /** @param array<string,mixed>$review */
    private function writeProjectBlocker(string$o,string$u,string$p,string$role,string$reviewId,string$title,?string$revision,string$sha,array$review):void
    {
        $status=(string)$review['overallStatus'];$phase=$this->requiredPdo()->prepare('SELECT current_phase FROM projects WHERE id=UNHEX(:p) AND organisation_id=UNHEX(:o)');$phase->execute(['p'=>$p,'o'=>$o]);$projectPhase=(string)$phase->fetchColumn();
        $payload=['reviewId'=>$reviewId,'drawingRevision'=>$revision,'responsibleRole'=>$role,'sourceStatus'=>self::SOURCE['status'],'sourceVersion'=>self::SOURCE['version'],'snapshotSha256'=>$sha,'overallStatus'=>$status,'boundaryStatus'=>$review['boundaryWall']['status']??null,'professionalReviewStatus'=>'pending','formalApprovalChanged'=>false];$json=$this->json($payload);$recordId=Identifier::generate();
        $this->requiredPdo()->prepare("INSERT INTO project_records(id,organisation_id,project_id,phase,module_key,record_type,title,status,payload,approvals,linked_record_ids,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:phase,'drawing_compliance','drawing_compliance_review',:title,'pending_review',:payload,:approvals,'[]',UNHEX(:u))")->execute(['id'=>$recordId,'o'=>$o,'p'=>$p,'phase'=>$projectPhase,'title'=>$title,'payload'=>$json,'approvals'=>$this->json(['required'=>true,'responsibleRole'=>$role,'professionalReviewStatus'=>'pending']),'u'=>$u]);
        $priority=$status==='fail'?'high':'medium';
        $this->requiredPdo()->prepare("INSERT INTO workflow_events(id,organisation_id,project_id,event_type,priority,source_module,payload) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),'municipal_readiness_compliance_blocker',:priority,'drawing_compliance',:payload)")->execute(['id'=>Identifier::generate(),'o'=>$o,'p'=>$p,'priority'=>$priority,'payload'=>$json]);
        $this->requiredPdo()->prepare("INSERT INTO inbox_items(id,organisation_id,project_id,assigned_role,event_type,title,detail,priority,status,payload) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),:role,'drawing_compliance_blocker',:title,:detail,:priority,'open',:payload)")->execute(['id'=>Identifier::generate(),'o'=>$o,'p'=>$p,'role'=>$role,'title'=>'Drawing compliance review requires action: '.$title,'detail'=>'Resolve the saved drawing-review evidence and obtain responsible-professional review. This does not change municipal or professional approval state.','priority'=>$priority,'payload'=>$json]);
    }

    /** @return array<string,mixed> */ private function finding(string$status,string$message,array$trace):array{return['status'=>$status,'message'=>$message,'trace'=>$trace+['sourceStatus'=>self::SOURCE['status']],'professionalReviewRequired'=>true];}
    /** @param array<string,int|float>$seats @param list<array<string,mixed>>$areas @return array<string,mixed> */
    private function occupancy(string$description,array$seats,array$areas):array{$class=match(true){preg_match('/shul|synagogue|church|mosque|temple|prayer|worship/i',$description)===1=>'A2',preg_match('/office|consulting/i',$description)===1=>'F3',preg_match('/school|crèche|preschool|daycare/i',$description)===1=>'E1',preg_match('/hospital|clinic|medical/i',$description)===1=>'C1',preg_match('/flat|apartment/i',$description)===1=>'H2',preg_match('/house|dwelling|residence/i',$description)===1=>'H1',default=>'unclassified'};$totalSeats=(int)array_sum($seats);$area=0.0;foreach($areas as$row)$area+=(float)($row['areaM2']??0);return['classification'=>['class'=>$class,'description'=>$class==='unclassified'?'Not classified; responsible professional must confirm.':'Illustrative occupancy candidate '.$class],'totalSeats'=>$totalSeats?:null,'totalFloorArea'=>$area,'estimatedPopulation'=>$totalSeats?:($area>0?(int)ceil($area/0.5):null),'isPlaceOfAssembly'=>in_array($class,['A1','A2','A3'],true),'sourceStatus'=>self::SOURCE['status'],'professionalReviewRequired'=>true];}
    /** @param list<mixed>$available @return array<string,mixed> */
    private function package(array$available,bool$sdp):array{$required=['general_notes_title_block','site_plan','floor_plans','roof_plan','elevations','sections','area_zoning_parking_schedule'];if($sdp)$required[]='sdp_site_layout';$normal=array_map(static fn(mixed$v):string=>strtolower(str_replace(' ','_',trim((string)$v))),$available);$missing=array_values(array_diff($required,$normal));return['requiredDrawingTypes'=>$required,'presentCount'=>count($required)-count($missing),'missingDrawingTypes'=>$missing,'packageVerdict'=>$missing===[]?'drawing_type_set_complete_subject_to_content_review':'not_ready_missing_required_drawing_types'];}
    /** @param list<array<string,mixed>>$register @param list<array<string,mixed>>$references @return array<string,mixed> */
    private function references(array$register,array$references):array{$sheets=[];$details=[];foreach($register as$sheet){$number=$this->normalRef($sheet['drawingNo']??$sheet['sheetNo']??$sheet['id']??null);if($number!==null)$sheets[$number]=true;foreach((array)($sheet['details']??[])as$detail){$key=$this->normalRef(($detail['detailNo']??'').'/'.$number);if($key!==null)$details[$key]=true;}}$results=[];foreach($references as$reference){$sheet=$this->normalRef($reference['targetSheet']??$reference['sheet']??$reference['drawingNo']??null);$detail=isset($reference['detailNo'])?$this->normalRef($reference['detailNo'].'/'.$sheet):null;$ok=$sheet!==null&&isset($sheets[$sheet])&&($detail===null||isset($details[$detail]));$results[]=$reference+['targetSheet'=>$sheet,'detailKey'=>$detail,'status'=>$ok?'resolved':'unresolved_reference'];}$unresolved=array_values(array_filter($results,static fn(array$r):bool=>$r['status']!=='resolved'));return['referenceCount'=>count($results),'unresolvedCount'=>count($unresolved),'unresolved'=>$unresolved,'results'=>$results,'verdict'=>$unresolved===[]?'internal_references_resolved':'not_ready_unresolved_internal_references'];}
    /** @param array<string,mixed>$site @return array<string,mixed> */
    private function landUse(string$description,array$site):array{$map=[['/shul|synagogue|church|mosque|temple|place of worship|religious/i','place_of_worship'],['/school|crèche|daycare|training|college/i','education_use'],['/office|consulting|medical|clinic/i','office_or_medical_use'],['/shop|retail|restaurant|café|take.?away/i','business_use'],['/apartment|flat|second dwelling|commune|guest house|bnb|hotel/i','residential_intensification'],['/boundary wall|retaining wall|pergola|deck|additions|alterations|extension/i','building_work'],['/heritage|older than 60|historic/i','heritage']];$text=implode(' ',[$description,(string)($site['currentUse']??''),(string)($site['proposedUse']??'')]);$triggers=[];foreach($map as[$regex,$trigger])if(preg_match($regex,$text)===1)$triggers[]=['trigger'=>$trigger];$required=['municipality','erfNumber','zoning','landUseSchemeName','currentUse','proposedUse','parkingProvided','coverage','FAR','heightStoreys','buildingLinesChecked'];$missing=[];foreach($required as$key)if(!array_key_exists($key,$site)||$site[$key]===null||$site[$key]==='')$missing[]=$key;return['scope'=>'planning_land_use_review_not_building_code_check','triggers'=>$triggers,'missingInputs'=>$missing,'needsPlanningReview'=>$triggers!==[]||$missing!==[],'likelyApplicationPath'=>in_array('place_of_worship',array_column($triggers,'trigger'),true)?'check_if_place_of_worship_is_primary_right_or_consent_use':'screen_against_zoning_controls_and_title_restrictions','professionalReviewRequired'=>true];}
    private function normalRef(mixed$value):?string{if(!is_string($value)||trim($value)==='')return null;return strtoupper((string)preg_replace('/\s+/','',trim($value)));}
    private function requiredPdo():PDO{if($this->pdo===null)throw new DrawingComplianceException('drawing_compliance_database_required');return$this->pdo;}
    private function assertMember(string$o,string$u):void{$statement=$this->requiredPdo()->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active'");$statement->execute(['o'=>$o,'u'=>$u]);if($statement->fetchColumn()===false)throw new DrawingComplianceException('drawing_compliance_membership_not_found');}
    private function assertProject(string$o,mixed$p):void{if(!is_string($p)||preg_match('/\A[a-f0-9]{32}\z/i',$p)!==1)throw new DrawingComplianceException('drawing_compliance_project_required');$statement=$this->requiredPdo()->prepare('SELECT 1 FROM projects WHERE id=UNHEX(:p) AND organisation_id=UNHEX(:o)');$statement->execute(['p'=>$p,'o'=>$o]);if($statement->fetchColumn()===false)throw new DrawingComplianceException('drawing_compliance_project_not_found');}
    private function text(mixed$value,int$max,string$error):string{if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new DrawingComplianceException($error);return trim($value);}
    private function optional(mixed$value,int$max):?string{if($value===null||$value==='')return null;if(!is_string($value)||mb_strlen(trim($value))>$max)throw new DrawingComplianceException('drawing_compliance_value_invalid');return trim($value);}
    /** @param array<string,mixed>$value */ private function json(array$value):string{return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
}

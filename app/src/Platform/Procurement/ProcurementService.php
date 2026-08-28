<?php

declare(strict_types=1);

namespace Architex\Platform\Procurement;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Project\ProjectContext;
use Architex\Platform\Project\ProjectPhase;
use Architex\Platform\Records\ProjectRecordService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Throwable;

final readonly class ProcurementService
{
    private const LISTING_TYPES=['contractor','subcontractor','supplier','specialist_consultant','quantity_surveyor_support','freelancer_candidate_professional','equipment_resource'];
    private const METHODS=['open_tender','invited_tender','rfq','direct_appointment'];
    private const CAPACITY=['available','limited','unavailable'];

    public function __construct(private PDO$pdo,private ProcurementScopeClassifier$classifier){}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createListing(string$organisationId,string$userId,string$role,array$input):array
    {
        $this->assertMember($organisationId,$userId,$role,['owner','admin','member']);
        $type=$this->choice($input['listingType']??null,self::LISTING_TYPES,'marketplace_listing_type_invalid');
        $supervision=$input['supervisionRequired']??null;
        if(!is_bool($supervision))throw new ProcurementException('marketplace_supervision_invalid');
        if($type==='freelancer_candidate_professional')$supervision=true;
        $id=Identifier::generate();
        $this->pdo->prepare("INSERT INTO marketplace_listings(id,organisation_id,created_by,listing_type,name,public_summary,disciplines_trades,service_areas,capacity_status,verification_status,supervision_required,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:type,:name,:summary,:trades,:areas,:capacity,'unverified',:supervision,'draft')")->execute([
            'id'=>$id,'organisation'=>$organisationId,'user'=>$userId,'type'=>$type,
            'name'=>$this->text($input['name']??null,180,'marketplace_listing_name_required'),
            'summary'=>$this->text($input['publicSummary']??null,1000,'marketplace_listing_summary_required'),
            'trades'=>$this->json($this->values($input['disciplinesTrades']??null,50,'marketplace_listing_trades_invalid')),
            'areas'=>$this->json($this->values($input['serviceAreas']??null,50,'marketplace_listing_areas_invalid')),
            'capacity'=>$this->choice($input['capacityStatus']??null,self::CAPACITY,'marketplace_capacity_invalid'),
            'supervision'=>$supervision?1:0,
        ]);
        (new AuditService($this->pdo))->append($organisationId,$userId,'procurement.marketplace_listing_created','marketplace_listing',$id,['listingType'=>$type,'supervisionRequired'=>$supervision]);
        return$this->getListing($organisationId,$userId,$id);
    }

    /** @return array<string,mixed> */
    public function publishListing(string$organisationId,string$userId,string$role,string$listingId,int$expectedVersion,bool$humanConfirmed,string$reason):array
    {
        $this->assertMember($organisationId,$userId,$role,['owner','admin']);
        if(!$humanConfirmed)throw new ProcurementException('marketplace_publication_confirmation_required');
        $reason=$this->text($reason,1000,'marketplace_publication_reason_required');
        $listing=$this->listingRow($organisationId,$listingId,true);
        if($listing['status']!=='draft'||(int)$listing['version']!==$expectedVersion)throw new ProcurementException('marketplace_listing_version_conflict');
        $evidence=$this->verificationEvidence($organisationId,(string)$listing['created_by'],(string)$listing['listing_type']);
        $status=$evidence===null?'unverified':'verified';
        $updated=$this->pdo->prepare("UPDATE marketplace_listings SET status='published',verification_status=:verification,verification_record_id=UNHEX(:record),version=version+1,published_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND version=:version AND status='draft'");
        $updated->execute(['verification'=>$status,'record'=>$evidence['id']??null,'id'=>$listingId,'organisation'=>$organisationId,'version'=>$expectedVersion]);
        if($updated->rowCount()!==1)throw new ProcurementException('marketplace_listing_version_conflict');
        (new AuditService($this->pdo))->append($organisationId,$userId,'procurement.marketplace_listing_published','marketplace_listing',$listingId,['reason'=>$reason,'verificationStatus'=>$status,'verificationRecordId'=>$evidence['id']??null,'emailDeliveryClaimed'=>false]);
        return$this->getListing($organisationId,$userId,$listingId);
    }

    /** @param array<string,mixed> $filters @return list<array<string,mixed>> */
    public function searchMarketplace(array$filters=[]):array
    {
        $type=$filters['listingType']??null;if($type!==null)$type=$this->choice($type,self::LISTING_TYPES,'marketplace_listing_type_invalid');
        $trade=$filters['trade']??null;if($trade!==null)$trade=$this->token($trade,'marketplace_trade_invalid');
        $area=$filters['serviceArea']??null;if($area!==null)$area=$this->text($area,120,'marketplace_service_area_invalid');
        $statement=$this->pdo->query("SELECT LOWER(HEX(id)) id,listing_type,name,public_summary,disciplines_trades,service_areas,capacity_status,verification_status,supervision_required,version FROM marketplace_listings WHERE status='published' AND capacity_status<>'unavailable' ORDER BY FIELD(verification_status,'verified','pending','unverified','expired'),published_at DESC,id DESC LIMIT 200");
        $result=[];
        foreach($statement as$row){$mapped=$this->publicListing($row);if($type!==null&&$mapped['listingType']!==$type)continue;if($trade!==null&&!in_array($trade,$mapped['disciplinesTrades'],true))continue;if($area!==null&&!in_array($area,$mapped['serviceAreas'],true))continue;$result[]=$mapped;if(count($result)>=100)break;}
        return$result;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createPackage(string$organisationId,string$userId,string$role,string$projectId,array$input):array
    {
        $this->assertProject($organisationId,$userId,$projectId);
        $classification=$this->classifier->classify($input);
        $method=$this->choice($input['procurementMethod']??null,self::METHODS,'procurement_method_invalid');
        $returnables=$this->values($input['requiredReturnables']??null,50,'procurement_returnables_invalid');if($returnables===[])throw new ProcurementException('procurement_returnables_required');
        $documents=$this->ids($input['documentRevisionIds']??null,100,'procurement_documents_invalid');if($documents===[])throw new ProcurementException('procurement_documents_required');
        $criteria=$this->criteria($input['evaluationCriteria']??null);
        $deadline=$this->date($input['deadlineAt']??null,'procurement_deadline_invalid');
        $id=Identifier::generate();
        try{$this->pdo->prepare("INSERT INTO procurement_packages(id,organisation_id,project_id,package_number,title,procurement_type,procurement_method,method_reason,scope_summary,estimated_value_zar,complexity,urgency,province,location,required_trades,required_specialists,required_returnables,evaluation_criteria,document_revision_ids,municipal_readiness_score,classification_snapshot,risk_flags,deadline_at,status,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),:number,:title,:type,:method,:method_reason,:scope,:value,:complexity,:urgency,:province,:location,:trades,:specialists,:returnables,:criteria,:documents,:score,:classification,:risks,:deadline,'draft',UNHEX(:user))")->execute([
            'id'=>$id,'organisation'=>$organisationId,'project'=>$projectId,'number'=>$this->text($input['packageNumber']??null,80,'procurement_package_number_required'),'title'=>$this->text($input['title']??null,220,'procurement_package_title_required'),'type'=>$classification['procurementType'],'method'=>$method,'method_reason'=>$this->text($input['methodReason']??null,1000,'procurement_method_reason_required'),'scope'=>$this->text($input['scopeSummary']??null,10000,'procurement_scope_summary_required'),'value'=>$this->decimal($input['estimatedValueZar']??null,0,9999999999999.99,'procurement_value_invalid'),'complexity'=>$this->choice($input['complexity']??null,['low','medium','high'],'procurement_complexity_invalid'),'urgency'=>$this->choice($input['urgency']??null,['normal','urgent','emergency'],'procurement_urgency_invalid'),'province'=>$this->text($input['province']??null,120,'procurement_province_required'),'location'=>$this->text($input['location']??null,220,'procurement_location_required'),'trades'=>$this->json($this->values($input['requiredTrades']??null,50,'procurement_trades_invalid')),'specialists'=>$this->json($this->values($input['requiredSpecialists']??null,50,'procurement_specialists_invalid')),'returnables'=>$this->json($returnables),'criteria'=>$this->json($criteria),'documents'=>$this->json($documents),'score'=>$this->decimal($input['municipalReadinessScore']??null,0,100,'procurement_readiness_score_invalid'),'classification'=>$this->json($classification),'risks'=>$this->json($classification['riskFlags']),'deadline'=>$deadline->format('Y-m-d H:i:s.u'),'user'=>$userId,
        ]);}catch(PDOException$e){if((string)$e->getCode()==='23000')throw new ProcurementException('procurement_package_number_exists');throw$e;}
        (new AuditService($this->pdo))->append($organisationId,$userId,'procurement.package_created','procurement_package',$id,['projectId'=>$projectId,'packageNumber'=>$input['packageNumber'],'procurementType'=>$classification['procurementType'],'selectedMethod'=>$method,'recommendedMethod'=>$classification['recommendedMethod']]);
        return$this->getPackage($organisationId,$userId,$id);
    }

    /** @return array<string,mixed> */
    public function issuePackage(string$organisationId,string$userId,string$role,string$packageId,int$expectedVersion,bool$humanConfirmed,string$reason):array
    {
        if(!$humanConfirmed)throw new ProcurementException('procurement_issue_confirmation_required');
        $reason=$this->text($reason,1000,'procurement_issue_reason_required');
        $this->assertMember($organisationId,$userId,$role,['owner','admin','member']);
        return$this->transaction(function()use($organisationId,$userId,$role,$packageId,$expectedVersion,$reason):array{
            $package=$this->packageRow($organisationId,$packageId,true);if($package['status']!=='draft'||(int)$package['version']!==$expectedVersion)throw new ProcurementException('procurement_package_version_conflict');
            $documents=$this->documentEvidence($organisationId,(string)$package['project_id'],$this->decode($package['document_revision_ids']));
            $deadline=new DateTimeImmutable((string)$package['deadline_at'],new DateTimeZone('UTC'));if($deadline<=new DateTimeImmutable('now',new DateTimeZone('UTC')))throw new ProcurementException('procurement_deadline_passed');
            $snapshot=['package'=>$this->mapPackage($package),'controlledDocuments'=>$documents,'issue'=>['issuedBy'=>$userId,'reason'=>$reason,'issuedAt'=>$this->now()],'fairnessRule'=>'Material clarifications must be issued as immutable addenda to every active bidder.','automaticAward'=>false,'emailDeliveryClaimed'=>false];
            $json=$this->json($snapshot);$sha=hash('sha256',$json);$revisionId=Identifier::generate();
            $this->pdo->prepare("INSERT INTO procurement_package_revisions(id,organisation_id,package_id,revision,status,snapshot_payload,snapshot_sha256,issue_reason,issued_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:package),1,'issued',:snapshot,:sha,:reason,UNHEX(:user))")->execute(['id'=>$revisionId,'organisation'=>$organisationId,'package'=>$packageId,'snapshot'=>$json,'sha'=>$sha,'reason'=>$reason,'user'=>$userId]);
            $updated=$this->pdo->prepare("UPDATE procurement_packages SET status='issued',version=version+1,current_revision_id=UNHEX(:revision),issued_by=UNHEX(:user),issued_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND version=:version AND status='draft'");$updated->execute(['revision'=>$revisionId,'user'=>$userId,'id'=>$packageId,'organisation'=>$organisationId,'version'=>$expectedVersion]);if($updated->rowCount()!==1)throw new ProcurementException('procurement_package_version_conflict');
            $recordId=(new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId,$userId,(string)$package['project_id'],$role),ProjectPhase::TenderProcurement,'procurement_marketplace','rfq_package',(string)$package['title'],'issued',['packageId'=>$packageId,'revisionId'=>$revisionId,'snapshotSha256'=>$sha,'procurementType'=>$package['procurement_type'],'procurementMethod'=>$package['procurement_method'],'deadlineAt'=>$deadline->format(DATE_ATOM),'automaticAward'=>false],['required'=>true,'humanApprovalConfirmed'=>true,'approvedBy'=>$userId],array_column($documents,'projectRecordId'));
            (new InboxService($this->pdo))->create(new ProjectContext($organisationId,$userId,(string)$package['project_id'],$role),'procurement_package_issued','Procurement package issued',(string)$package['package_number'].' is ready for advisory marketplace matching.','medium',['packageId'=>$packageId,'snapshotSha256'=>$sha,'projectRecordId'=>$recordId]);
            (new AuditService($this->pdo))->append($organisationId,$userId,'procurement.package_issued','procurement_package',$packageId,['revisionId'=>$revisionId,'snapshotSha256'=>$sha,'projectRecordId'=>$recordId,'reason'=>$reason,'automaticAward'=>false]);
            return$this->getPackage($organisationId,$userId,$packageId);
        });
    }

    /** @return list<array<string,mixed>> */
    public function matchMarketplace(string$organisationId,string$userId,string$packageId):array
    {
        $package=$this->getPackage($organisationId,$userId,$packageId);if($package['status']!=='issued')throw new ProcurementException('procurement_package_not_issued');
        $listings=$this->searchMarketplace();$matches=[];
        foreach($listings as$listing){$score=0;$reasons=[];$hits=count(array_intersect($package['requiredTrades'],$listing['disciplinesTrades']));if($hits>0){$score+=min(40,$hits*20);$reasons[]=$hits.' trade or discipline match'.($hits===1?'':'es');}if(in_array($package['province'],$listing['serviceAreas'],true)||in_array($package['location'],$listing['serviceAreas'],true)){$score+=25;$reasons[]='service area match';}$expected=match($package['procurementType']){'main_contractor'=>'contractor','trade_package'=>'subcontractor','supplier'=>'supplier','specialist_consultant'=>'specialist_consultant','freelancer_support'=>'freelancer_candidate_professional'};if($listing['listingType']===$expected){$score+=15;$reasons[]='participant category match';}if($listing['verificationStatus']==='verified'){$score+=10;$reasons[]='verified marketplace evidence';}else$reasons[]='verification remains '.$listing['verificationStatus'];$score+=$listing['capacityStatus']==='available'?10:4;$reasons[]='conflict declaration required at quote submission';if($listing['supervisionRequired'])$reasons[]='registered-professional supervision gate required';if($score<35)continue;$id=Identifier::generate();$this->pdo->prepare("INSERT INTO procurement_matches(id,organisation_id,package_id,listing_id,score,reasons,conflict_flag,supervision_required,advisory_only) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:package),UNHEX(:listing),:score,:reasons,0,:supervision,1) ON DUPLICATE KEY UPDATE score=VALUES(score),reasons=VALUES(reasons),supervision_required=VALUES(supervision_required),advisory_only=1")->execute(['id'=>$id,'organisation'=>$organisationId,'package'=>$packageId,'listing'=>$listing['id'],'score'=>$score,'reasons'=>$this->json($reasons),'supervision'=>$listing['supervisionRequired']?1:0]);$matches[]=['listing'=>$listing,'score'=>$score,'reasons'=>$reasons,'conflict'=>false,'supervisionRequired'=>$listing['supervisionRequired'],'advisoryOnly'=>true];}
        usort($matches,static fn(array$a,array$b):int=>$b['score']<=>$a['score']);
        (new AuditService($this->pdo))->append($organisationId,$userId,'procurement.marketplace_matched','procurement_package',$packageId,['matchCount'=>count($matches),'advisoryOnly'=>true,'automaticAward'=>false]);
        return$matches;
    }

    /** @return array<string,mixed> */
    public function invite(string$organisationId,string$userId,string$role,string$packageId,string$listingId,bool$humanConfirmed):array
    {
        $this->assertMember($organisationId,$userId,$role,['owner','admin','member']);if(!$humanConfirmed)throw new ProcurementException('procurement_invitation_confirmation_required');
        $package=$this->packageRow($organisationId,$packageId,false);if($package['status']!=='issued')throw new ProcurementException('procurement_package_not_issued');$listing=$this->publicListingRow($listingId);if($listing===null)throw new ProcurementException('marketplace_listing_not_found');
        $plain=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$expires=(new DateTimeImmutable((string)$package['deadline_at'],new DateTimeZone('UTC')))->modify('+30 days');$id=Identifier::generate();
        try{$this->pdo->prepare("INSERT INTO procurement_invitations(id,organisation_id,package_id,listing_id,token_hash,status,delivery_status,expires_at,invited_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:package),UNHEX(:listing),:hash,'invited','in_app_ready',:expires,UNHEX(:user))")->execute(['id'=>$id,'organisation'=>$organisationId,'package'=>$packageId,'listing'=>$listingId,'hash'=>hash('sha256',$plain),'expires'=>$expires->format('Y-m-d H:i:s.u'),'user'=>$userId]);}catch(PDOException$e){if((string)$e->getCode()==='23000')throw new ProcurementException('procurement_listing_already_invited');throw$e;}
        $recordId=(new ProjectRecordService($this->pdo))->issue(new ProjectContext($organisationId,$userId,(string)$package['project_id'],$role),ProjectPhase::TenderProcurement,'procurement_marketplace','bidder_invitation','Bidder invitation: '.$listing['name'],'issued',['packageId'=>$packageId,'invitationId'=>$id,'listingId'=>$listingId,'listingName'=>$listing['name'],'deliveryStatus'=>'in_app_ready','emailDeliveryClaimed'=>false,'expiresAt'=>$expires->format(DATE_ATOM)],['required'=>false,'humanApprovalConfirmed'=>true]);
        (new AuditService($this->pdo))->append($organisationId,$userId,'procurement.bidder_invited','procurement_invitation',$id,['packageId'=>$packageId,'listingId'=>$listingId,'deliveryStatus'=>'in_app_ready','emailDeliveryClaimed'=>false,'projectRecordId'=>$recordId,'tokenStored'=>'sha256_only']);
        return['id'=>$id,'packageId'=>$packageId,'listingId'=>$listingId,'listingName'=>$listing['name'],'status'=>'invited','deliveryStatus'=>'in_app_ready','emailDeliveryClaimed'=>false,'accessToken'=>$plain,'expiresAt'=>$expires->format(DATE_ATOM)];
    }

    /** @return array<string,mixed> */
    public function getPackage(string$organisationId,string$userId,string$packageId):array
    {
        $this->assertMember($organisationId,$userId,null,['owner','admin','member','viewer']);return$this->mapPackage($this->packageRow($organisationId,$packageId,false));
    }

    /** @return array<string,mixed> */
    public function getListing(string$organisationId,string$userId,string$listingId):array
    {
        $this->assertMember($organisationId,$userId,null,['owner','admin','member','viewer']);$row=$this->listingRow($organisationId,$listingId,false);return$this->mapListing($row);
    }

    /** @return array<string,mixed> */
    private function mapPackage(array$row):array
    {
        $classification=$this->decode($row['classification_snapshot']);return['id'=>$row['id'],'projectId'=>$row['project_id'],'packageNumber'=>$row['package_number'],'title'=>$row['title'],'procurementType'=>$row['procurement_type'],'procurementMethod'=>$row['procurement_method'],'methodReason'=>$row['method_reason'],'scopeSummary'=>$row['scope_summary'],'estimatedValueZar'=>(float)$row['estimated_value_zar'],'complexity'=>$row['complexity'],'urgency'=>$row['urgency'],'province'=>$row['province'],'location'=>$row['location'],'requiredTrades'=>$this->decode($row['required_trades']),'requiredSpecialists'=>$this->decode($row['required_specialists']),'requiredReturnables'=>$this->decode($row['required_returnables']),'evaluationCriteria'=>$this->decode($row['evaluation_criteria']),'documentRevisionIds'=>$this->decode($row['document_revision_ids']),'municipalReadinessScore'=>(float)$row['municipal_readiness_score'],'classification'=>$classification,'riskFlags'=>$this->decode($row['risk_flags']),'deadlineAt'=>$this->atom($row['deadline_at']),'status'=>$row['status'],'version'=>(int)$row['version'],'revisionId'=>$row['current_revision_id'],'snapshotSha256'=>$row['snapshot_sha256']??null,'statutoryThresholdClaimed'=>(bool)($classification['statutoryThresholdClaimed']??false),'createdAt'=>$this->atom($row['created_at']),'issuedAt'=>$this->atom($row['issued_at'])];
    }

    /** @return array<string,mixed> */
    private function mapListing(array$row):array{return['id'=>$row['id'],'organisationId'=>$row['organisation_id'],'createdBy'=>$row['created_by'],'listingType'=>$row['listing_type'],'name'=>$row['name'],'publicSummary'=>$row['public_summary'],'disciplinesTrades'=>$this->decode($row['disciplines_trades']),'serviceAreas'=>$this->decode($row['service_areas']),'capacityStatus'=>$row['capacity_status'],'verificationStatus'=>$row['verification_status'],'verificationRecordId'=>$row['verification_record_id'],'supervisionRequired'=>(bool)$row['supervision_required'],'status'=>$row['status'],'version'=>(int)$row['version']];}
    /** @return array<string,mixed> */private function publicListing(array$row):array{return['id'=>$row['id'],'listingType'=>$row['listing_type'],'name'=>$row['name'],'publicSummary'=>$row['public_summary'],'disciplinesTrades'=>$this->decode($row['disciplines_trades']),'serviceAreas'=>$this->decode($row['service_areas']),'capacityStatus'=>$row['capacity_status'],'verificationStatus'=>$row['verification_status'],'supervisionRequired'=>(bool)$row['supervision_required'],'version'=>(int)$row['version']];}
    /** @return array<string,mixed> */private function listingRow(string$o,string$id,bool$lock):array{$s=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,LOWER(HEX(organisation_id)) organisation_id,LOWER(HEX(created_by)) created_by,listing_type,name,public_summary,disciplines_trades,service_areas,capacity_status,verification_status,LOWER(HEX(verification_record_id)) verification_record_id,supervision_required,status,version FROM marketplace_listings WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:o)'.($lock?' FOR UPDATE':''));$s->execute(['id'=>$this->id($id,'marketplace_listing_not_found'),'o'=>$o]);$r=$s->fetch();if(!is_array($r))throw new ProcurementException('marketplace_listing_not_found');return$r;}
    /** @return array<string,mixed>|null */private function publicListingRow(string$id):?array{$s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,listing_type,name,public_summary,disciplines_trades,service_areas,capacity_status,verification_status,supervision_required,version FROM marketplace_listings WHERE id=UNHEX(:id) AND status='published' AND capacity_status<>'unavailable'");$s->execute(['id'=>$this->id($id,'marketplace_listing_not_found')]);$r=$s->fetch();return is_array($r)?$this->publicListing($r):null;}
    /** @return array<string,mixed> */private function packageRow(string$o,string$id,bool$lock):array{$s=$this->pdo->prepare("SELECT LOWER(HEX(p.id)) id,LOWER(HEX(p.project_id)) project_id,p.package_number,p.title,p.procurement_type,p.procurement_method,p.method_reason,p.scope_summary,p.estimated_value_zar,p.complexity,p.urgency,p.province,p.location,p.required_trades,p.required_specialists,p.required_returnables,p.evaluation_criteria,p.document_revision_ids,p.municipal_readiness_score,p.classification_snapshot,p.risk_flags,p.deadline_at,p.status,p.version,LOWER(HEX(p.current_revision_id)) current_revision_id,r.snapshot_sha256,p.issued_at,p.created_at FROM procurement_packages p LEFT JOIN procurement_package_revisions r ON r.id=p.current_revision_id AND r.organisation_id=p.organisation_id WHERE p.id=UNHEX(:id) AND p.organisation_id=UNHEX(:o)".($lock?' FOR UPDATE':''));$s->execute(['id'=>$this->id($id,'procurement_package_not_found'),'o'=>$o]);$r=$s->fetch();if(!is_array($r))throw new ProcurementException('procurement_package_not_found');return$r;}
    /** @return array<string,mixed>|null */private function verificationEvidence(string$o,string$u,string$type):?array{$kind=in_array($type,['contractor','subcontractor','supplier'],true)?'contractor_supplier_compliance':(in_array($type,['specialist_consultant','quantity_surveyor_support','freelancer_candidate_professional'],true)?'professional_registration':'company_document');$s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,grade FROM verification_records WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND kind=:kind AND grade IN ('manually_reviewed','externally_verified') AND reviewed_by IS NOT NULL AND reviewed_by<>user_id AND (expires_on IS NULL OR expires_on>=UTC_DATE()) ORDER BY FIELD(grade,'externally_verified','manually_reviewed'),reviewed_at DESC,id DESC LIMIT 1");$s->execute(['o'=>$o,'u'=>$u,'kind'=>$kind]);$r=$s->fetch();return is_array($r)?$r:null;}

    /** @param list<string> $ids @return list<array<string,mixed>> */private function documentEvidence(string$o,string$p,array$ids):array{$result=[];$s=$this->pdo->prepare("SELECT LOWER(HEX(r.id)) id,r.revision_code revisionCode,r.audit_hash auditHash,d.title,d.document_type documentType,LOWER(HEX(pr.id)) projectRecordId FROM document_revisions r JOIN controlled_documents d ON d.id=r.document_id AND d.organisation_id=r.organisation_id LEFT JOIN project_records pr ON pr.organisation_id=r.organisation_id AND pr.project_id=r.project_id AND JSON_UNQUOTE(JSON_EXTRACT(pr.payload,'$.revisionId'))=LOWER(HEX(r.id)) AND pr.status='issued' WHERE r.id=UNHEX(:id) AND r.organisation_id=UNHEX(:o) AND r.project_id=UNHEX(:p) AND r.status='issued' AND r.issue_purpose='for_tender' LIMIT 1");foreach($ids as$id){$s->execute(['id'=>$id,'o'=>$o,'p'=>$p]);$row=$s->fetch();if(!is_array($row)||!is_string($row['auditHash'])||preg_match('/\A[a-f0-9]{64}\z/',$row['auditHash'])!==1)throw new ProcurementException('procurement_document_not_issued_for_tender');$result[]=$row;}return$result;}
    /** @param list<string> $allowed */private function assertMember(string$o,string$u,?string$claimedRole,array$allowed):void{$s=$this->pdo->prepare("SELECT role FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active'");$s->execute(['o'=>$o,'u'=>$u]);$actual=$s->fetchColumn();if(!is_string($actual)||!in_array($actual,$allowed,true)||($claimedRole!==null&&!hash_equals($actual,$claimedRole)))throw new ProcurementException('procurement_permission_denied');}
    private function assertProject(string$o,string$u,string$p):void{$this->assertMember($o,$u,null,['owner','admin','member']);$s=$this->pdo->prepare('SELECT COUNT(*) FROM projects WHERE id=UNHEX(:p) AND organisation_id=UNHEX(:o)');$s->execute(['p'=>$this->id($p,'procurement_project_not_found'),'o'=>$o]);if((int)$s->fetchColumn()!==1)throw new ProcurementException('procurement_project_not_found');}
    /** @return array<string,float> */private function criteria(mixed$v):array{if(!is_array($v)||$v===[])throw new ProcurementException('procurement_evaluation_criteria_invalid');$allowed=['price','returnables','lead_time','risk','conflict'];$out=[];$sum=0.0;foreach($v as$k=>$weight){if(!is_string($k)||!in_array($k,$allowed,true)||(!is_int($weight)&&!is_float($weight))||!is_finite((float)$weight)||(float)$weight<0||(float)$weight>100)throw new ProcurementException('procurement_evaluation_criteria_invalid');$out[$k]=(float)$weight;$sum+=(float)$weight;}if(abs($sum-100)>0.001)throw new ProcurementException('procurement_evaluation_criteria_total_invalid');return$out;}
    private function id(mixed$v,string$error):string{if(!is_string($v)||preg_match('/\A[a-f0-9]{32}\z/i',$v)!==1)throw new ProcurementException($error);return strtolower($v);}private function token(mixed$v,string$error):string{if(!is_string($v)||preg_match('/\A[a-z0-9][a-z0-9_ -]{0,79}\z/i',$v)!==1)throw new ProcurementException($error);return$v;}
    /** @return list<string> */private function ids(mixed$v,int$max,string$error):array{if(!is_array($v)||count($v)>$max)throw new ProcurementException($error);$out=[];foreach($v as$id)$out[]=$this->id($id,$error);return array_values(array_unique($out));}
    /** @return list<string> */private function values(mixed$v,int$max,string$error):array{if(!is_array($v)||count($v)>$max)throw new ProcurementException($error);$out=[];foreach($v as$item)$out[]=$this->token($item,$error);return array_values(array_unique($out));}
    private function text(mixed$v,int$max,string$error):string{if(!is_string($v)||($v=trim($v))===''||mb_strlen($v)>$max)throw new ProcurementException($error);return$v;}
    /** @param list<string> $allowed */private function choice(mixed$v,array$allowed,string$error):string{if(!is_string($v)||!in_array($v,$allowed,true))throw new ProcurementException($error);return$v;}
    private function decimal(mixed$v,float$min,float$max,string$error):float{if((!is_int($v)&&!is_float($v))||!is_finite((float)$v)||(float)$v<$min||(float)$v>$max)throw new ProcurementException($error);return round((float)$v,2);}
    private function date(mixed$v,string$error):DateTimeImmutable{if(!is_string($v)||trim($v)==='')throw new ProcurementException($error);try{$d=new DateTimeImmutable($v);}catch(Throwable){throw new ProcurementException($error);}return$d->setTimezone(new DateTimeZone('UTC'));}
    /** @param array<mixed> $v */private function json(array$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);}/** @return array<mixed> */private function decode(mixed$v):array{if(!is_string($v))return[];$d=json_decode($v,true,64,JSON_THROW_ON_ERROR);if(!is_array($d))throw new ProcurementException('procurement_integrity_failed');return$d;}
    private function atom(mixed$v):?string{if(!is_string($v)||$v==='')return null;return(new DateTimeImmutable($v,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');}private function now():string{return(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');}
    private function transaction(callable$operation):mixed{$this->pdo->beginTransaction();try{$result=$operation();$this->pdo->commit();return$result;}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
}

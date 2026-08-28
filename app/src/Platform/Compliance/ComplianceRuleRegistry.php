<?php

declare(strict_types=1);

namespace Architex\Platform\Compliance;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use DateTimeImmutable;
use PDO;
use PDOException;

final readonly class ComplianceRuleRegistry
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function ensureImplementationSeed(string $organisationId,string $userId,string $role): array
    {
        $this->assertGovernor($organisationId,$userId,$role);
        $rules=$this->seedRules();$json=$this->json($rules);$id=Identifier::generate();
        $this->pdo->prepare("INSERT IGNORE INTO compliance_rule_versions(id,organisation_id,rule_key,version,status,source_reference,effective_date,rules_payload,payload_sha256,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'sans_10400_xa','implementation-seed-v0.1','review_required','Supplied implementation seed values; official or licensed source review required','2026-06-21',:rules,:sha256,UNHEX(:user_id))")->execute(['id'=>$id,'organisation_id'=>$organisationId,'rules'=>$json,'sha256'=>hash('sha256',$json),'user_id'=>$userId]);
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) FROM compliance_rule_versions WHERE organisation_id=UNHEX(:organisation_id) AND rule_key='sans_10400_xa' AND version='implementation-seed-v0.1'");$statement->execute(['organisation_id'=>$organisationId]);$stored=$statement->fetchColumn();
        if(!is_string($stored))throw new ComplianceException('compliance_seed_failed');
        return$this->get($organisationId,$stored);
    }

    /** @return array<string,mixed> */
    public function ensureRefuseImplementationSeed(string $organisationId,string $userId,string $role): array
    {
        $this->assertGovernor($organisationId,$userId,$role);
        $rules=$this->refuseSeedRules();$json=$this->json($rules);$id=Identifier::generate();
        $this->pdo->prepare("INSERT IGNORE INTO compliance_rule_versions(id,organisation_id,rule_key,version,status,source_reference,effective_date,rules_payload,payload_sha256,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'municipal_refuse','implementation-seed-v0.1','review_required','Supplied implementation seed assumptions; exact current municipal source review required','2026-06-21',:rules,:sha256,UNHEX(:user_id))")->execute(['id'=>$id,'organisation_id'=>$organisationId,'rules'=>$json,'sha256'=>hash('sha256',$json),'user_id'=>$userId]);
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) FROM compliance_rule_versions WHERE organisation_id=UNHEX(:organisation_id) AND rule_key='municipal_refuse' AND version='implementation-seed-v0.1'");$statement->execute(['organisation_id'=>$organisationId]);$stored=$statement->fetchColumn();
        if(!is_string($stored))throw new ComplianceException('compliance_seed_failed');
        return$this->get($organisationId,$stored);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createVersion(string $organisationId,string $userId,string $role,array $input): array
    {
        $this->assertGovernor($organisationId,$userId,$role);
        $ruleKey=$this->requiredPattern($input['ruleKey']??null,'/\A[a-z][a-z0-9_]{2,79}\z/','invalid_rule_key');
        $version=$this->requiredPattern($input['version']??null,'/\A[a-zA-Z0-9][a-zA-Z0-9._-]{1,99}\z/','invalid_rule_version');
        if(str_starts_with($version,'implementation-seed'))throw new ComplianceException('reserved_rule_version');
        $source=$this->requiredText($input['sourceReference']??null,500,'invalid_source_reference');
        $effective=$this->date($input['effectiveDate']??null);
        $rules=$input['rules']??null;if(!is_array($rules)||$rules===[])throw new ComplianceException('invalid_rule_payload');
        $this->validateRules($ruleKey,$rules);$json=$this->json($rules);$id=Identifier::generate();
        try{$this->pdo->prepare("INSERT INTO compliance_rule_versions(id,organisation_id,rule_key,version,status,source_reference,effective_date,rules_payload,payload_sha256,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),:rule_key,:version,'review_required',:source_reference,:effective_date,:rules,:sha256,UNHEX(:user_id))")->execute(['id'=>$id,'organisation_id'=>$organisationId,'rule_key'=>$ruleKey,'version'=>$version,'source_reference'=>$source,'effective_date'=>$effective,'rules'=>$json,'sha256'=>hash('sha256',$json),'user_id'=>$userId]);}
        catch(PDOException $exception){if((string)$exception->getCode()==='23000')throw new ComplianceException('duplicate_rule_version');throw$exception;}
        (new AuditService($this->pdo))->append($organisationId,$userId,'compliance_rule.created','compliance_rule_version',$id,['ruleKey'=>$ruleKey,'version'=>$version,'payloadSha256'=>hash('sha256',$json),'status'=>'review_required']);
        return$this->get($organisationId,$id);
    }

    /** @return array<string,mixed> */
    public function verify(string $organisationId,string $userId,string $role,string $ruleVersionId,bool $confirmed,string $reason): array
    {
        $this->assertGovernor($organisationId,$userId,$role);
        if(!$confirmed)throw new ComplianceException('human_confirmation_required');
        $reason=$this->requiredText($reason,500,'verification_reason_required');$existing=$this->get($organisationId,$ruleVersionId);
        if($existing['status']!=='review_required')throw new ComplianceException('rule_version_not_reviewable');
        if(str_starts_with((string)$existing['version'],'implementation-seed'))throw new ComplianceException('implementation_seed_not_verifiable');
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("UPDATE compliance_rule_versions SET status='withdrawn' WHERE organisation_id=UNHEX(:organisation_id) AND rule_key=:rule_key AND status='verified' AND id<>UNHEX(:id)")->execute(['organisation_id'=>$organisationId,'rule_key'=>$existing['ruleKey'],'id'=>$ruleVersionId]);
            $statement=$this->pdo->prepare("UPDATE compliance_rule_versions SET status='verified',reviewed_by=UNHEX(:user_id),reviewed_at=UTC_TIMESTAMP(6),review_reason=:reason WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND status='review_required'");$statement->execute(['user_id'=>$userId,'reason'=>$reason,'id'=>$ruleVersionId,'organisation_id'=>$organisationId]);
            if($statement->rowCount()!==1)throw new ComplianceException('rule_version_not_reviewable');
            (new AuditService($this->pdo))->append($organisationId,$userId,'compliance_rule.verified','compliance_rule_version',$ruleVersionId,['ruleKey'=>$existing['ruleKey'],'version'=>$existing['version'],'payloadSha256'=>$existing['payloadSha256'],'reason'=>$reason]);
            $this->pdo->commit();
        }catch(\Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$exception;}
        return$this->get($organisationId,$ruleVersionId);
    }

    /** @return array<string,mixed> */
    public function current(string $organisationId,string $ruleKey,bool $verifiedOnly=false): array
    {
        $status=$verifiedOnly?"status='verified'":"status IN ('verified','review_required')";
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(id)) FROM compliance_rule_versions WHERE organisation_id=UNHEX(:organisation_id) AND rule_key=:rule_key AND {$status} ORDER BY FIELD(status,'verified','review_required'),effective_date DESC,created_at DESC LIMIT 1");$statement->execute(['organisation_id'=>$organisationId,'rule_key'=>$ruleKey]);$id=$statement->fetchColumn();
        if(!is_string($id))throw new ComplianceException('compliance_rule_not_found');return$this->get($organisationId,$id);
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId): array
    {
        $statement=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,rule_key,version,status,source_reference,effective_date,rules_payload,payload_sha256,LOWER(HEX(created_by)) created_by,LOWER(HEX(reviewed_by)) reviewed_by,DATE_FORMAT(reviewed_at,\'%Y-%m-%dT%H:%i:%s.%fZ\') reviewed_at,review_reason,DATE_FORMAT(created_at,\'%Y-%m-%dT%H:%i:%s.%fZ\') created_at FROM compliance_rule_versions WHERE organisation_id=UNHEX(:organisation_id) ORDER BY rule_key,FIELD(status,\'verified\',\'review_required\',\'withdrawn\'),effective_date DESC,created_at DESC');$statement->execute(['organisation_id'=>$organisationId]);$items=[];foreach($statement as$row)$items[]=$this->map($row);return$items;
    }

    /** @return array<string,mixed> */
    public function get(string $organisationId,string $id): array
    {
        if(preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1)throw new ComplianceException('compliance_rule_not_found');
        $statement=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,rule_key,version,status,source_reference,effective_date,rules_payload,payload_sha256,LOWER(HEX(created_by)) created_by,LOWER(HEX(reviewed_by)) reviewed_by,DATE_FORMAT(reviewed_at,\'%Y-%m-%dT%H:%i:%s.%fZ\') reviewed_at,review_reason,DATE_FORMAT(created_at,\'%Y-%m-%dT%H:%i:%s.%fZ\') created_at FROM compliance_rule_versions WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id)');$statement->execute(['id'=>$id,'organisation_id'=>$organisationId]);$row=$statement->fetch();if(!is_array($row))throw new ComplianceException('compliance_rule_not_found');return$this->map($row);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function map(array $row): array
    {
        $rules=json_decode((string)$row['rules_payload'],true,64,JSON_THROW_ON_ERROR);$checksum=hash('sha256',$this->json($rules));if(!hash_equals((string)$row['payload_sha256'],$checksum))throw new ComplianceException('compliance_rule_integrity_failed');
        return['id'=>(string)$row['id'],'ruleKey'=>(string)$row['rule_key'],'version'=>(string)$row['version'],'status'=>(string)$row['status'],'issueEligible'=>$row['status']==='verified','sourceReference'=>(string)$row['source_reference'],'effectiveDate'=>(string)$row['effective_date'],'rules'=>$rules,'payloadSha256'=>(string)$row['payload_sha256'],'createdBy'=>(string)$row['created_by'],'reviewedBy'=>$row['reviewed_by'],'reviewedAt'=>$row['reviewed_at'],'reviewReason'=>$row['review_reason'],'createdAt'=>(string)$row['created_at']];
    }

    private function assertGovernor(string $organisationId,string $userId,string $role): void
    {
        if(!in_array($role,['owner','admin'],true))throw new ComplianceException('compliance_rule_role_denied');
        $statement=$this->pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND role=:role AND status='active'");$statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId,'role'=>$role]);if((int)$statement->fetchColumn()!==1)throw new ComplianceException('membership_not_found');
    }

    /** @param array<string,mixed> $rules */
    private function validateRules(string $ruleKey,array $rules): void
    {
        if($ruleKey==='municipal_refuse'){$this->validateRefuseRules($rules);return;}
        if($ruleKey!=='sans_10400_xa')throw new ComplianceException('invalid_rule_key');
        $zones=$rules['climateZones']??null;if(!is_array($zones)||count($zones)!==6)throw new ComplianceException('invalid_rule_payload');
        foreach(range(1,6)as$zone){$item=$zones[(string)$zone]??$zones[$zone]??null;if(!is_array($item)||!is_array($item['uLimit']??null)||!is_array($item['shgc']??null)||!is_array($item['minR']??null))throw new ComplianceException('invalid_rule_payload');}
        if(!is_array($rules['materials']??null)||!is_array($rules['forms']??null))throw new ComplianceException('invalid_rule_payload');
    }

    /** @param array<string,mixed> $rules */
    private function validateRefuseRules(array $rules): void
    {
        $rates=$rules['generationRates']??null;$bins=$rules['bins']??null;$facilities=$rules['requiredFacilities']??null;
        if(!is_array($rates)||$rates===[]||!is_array($bins)||$bins===[]||!is_array($facilities)||$facilities===[])throw new ComplianceException('invalid_rule_payload');
        foreach($rates as$key=>$rate)if(!is_string($key)||preg_match('/\A[a-z][a-z0-9_]{2,79}\z/',$key)!==1||!is_array($rate)||!is_string($rate['label']??null)||!is_string($rate['unit']??null)||!is_numeric($rate['litresPerUnitPerDay']??null)||(float)$rate['litresPerUnitPerDay']<=0)throw new ComplianceException('invalid_rule_payload');
        foreach($bins as$key=>$bin)if(!is_string($key)||preg_match('/\A[a-z][a-z0-9_]{2,79}\z/',$key)!==1||!is_array($bin)||!is_string($bin['label']??null)||!is_numeric($bin['capacityLitres']??null)||(float)$bin['capacityLitres']<=0||!is_numeric($bin['footprintM2']??null)||(float)$bin['footprintM2']<=0)throw new ComplianceException('invalid_rule_payload');
        foreach($facilities as$key=>$label)if(!is_string($key)||!is_string($label)||trim($label)==='')throw new ComplianceException('invalid_rule_payload');
        $interval=$rules['defaultCollectionIntervalDays']??null;$contingency=$rules['defaultContingencyPercent']??null;$circulation=$rules['circulationAllowancePercent']??null;
        if(filter_var($interval,FILTER_VALIDATE_INT)===false||(int)$interval<1||(int)$interval>7||!is_numeric($contingency)||(float)$contingency<0||(float)$contingency>100||!is_numeric($circulation)||(float)$circulation<0||(float)$circulation>200)throw new ComplianceException('invalid_rule_payload');
    }

    /** @return array<string,mixed> */
    private function seedRules(): array
    {
        return['sourceNotice'=>'Implementation seed values supplied by the pack. Official or licensed SANS/NBR verification is required before formal issue.','climateZones'=>[
            '1'=>['name'=>'Interior','uLimit'=>['alu'=>3.5,'thermal'=>2.7,'timber'=>5.7],'shgc'=>['n'=>0.60,'s'=>0.65,'ew'=>0.30],'maxGlazingPercent'=>0.15,'minR'=>['roof'=>3.7,'wall'=>1.9,'floor'=>1.0]],
            '2'=>['name'=>'Coastal','uLimit'=>['alu'=>3.3,'thermal'=>2.5,'timber'=>3.5],'shgc'=>['n'=>0.50,'s'=>0.55,'ew'=>0.25],'maxGlazingPercent'=>0.15,'minR'=>['roof'=>3.7,'wall'=>1.8,'floor'=>1.0]],
            '3'=>['name'=>'KZN Coast','uLimit'=>['alu'=>5.7,'thermal'=>5.7,'timber'=>5.7],'shgc'=>['n'=>0.65,'s'=>0.70,'ew'=>0.30],'maxGlazingPercent'=>0.20,'minR'=>['roof'=>3.7,'wall'=>1.4,'floor'=>0.8]],
            '4'=>['name'=>'Central Interior','uLimit'=>['alu'=>3.0,'thermal'=>2.3,'timber'=>3.0],'shgc'=>['n'=>0.50,'s'=>0.55,'ew'=>0.20],'maxGlazingPercent'=>0.15,'minR'=>['roof'=>3.7,'wall'=>2.2,'floor'=>1.3]],
            '5'=>['name'=>'High Altitude','uLimit'=>['alu'=>3.0,'thermal'=>2.3,'timber'=>3.0],'shgc'=>['n'=>0.60,'s'=>0.65,'ew'=>0.20],'maxGlazingPercent'=>0.15,'minR'=>['roof'=>3.7,'wall'=>2.2,'floor'=>1.3]],
            '6'=>['name'=>'Hot Interior','uLimit'=>['alu'=>5.7,'thermal'=>5.7,'timber'=>5.7],'shgc'=>['n'=>0.50,'s'=>0.55,'ew'=>0.20],'maxGlazingPercent'=>0.20,'minR'=>['roof'=>3.7,'wall'=>1.9,'floor'=>1.0]],
        ],'materials'=>['brick'=>['conductivity'=>0.77],'concrete'=>['conductivity'=>1.4],'plaster'=>['conductivity'=>0.72],'gypsum'=>['conductivity'=>0.25],'glasswool'=>['conductivity'=>0.04],'polyiso'=>['conductivity'=>0.026],'xps'=>['conductivity'=>0.034],'air-cavity'=>['rValue'=>0.18],'roof-tile'=>['rValue'=>0.02],'ceiling-board'=>['conductivity'=>0.21]],'forms'=>[
            ['id'=>'A1','name'=>'Application for Approval checklist','parts'=>['A'],'evidence'=>['owner details','property description','plans','professional details']],
            ['id'=>'A19','name'=>'Competent Person appointment/undertaking','parts'=>['A'],'evidence'=>['competent person details','discipline','signature','registration reference']],
            ['id'=>'XA','name'=>'Energy usage / XA compliance evidence','parts'=>['XA'],'evidence'=>['fenestration report','R-value report','hot-water notes','lighting/AC where applicable']],
            ['id'=>'T','name'=>'Fire protection pre-check','parts'=>['T'],'evidence'=>['occupancy','escape routes','fire notes','competent person if required']],
            ['id'=>'S','name'=>'Facilities for persons with disabilities pre-check','parts'=>['S'],'evidence'=>['access route','sanitary facilities','parking/ramp/lift notes']],
        ]];
    }

    /** @return array<string,mixed> */
    private function refuseSeedRules(): array
    {
        return[
            'sourceNotice'=>'Implementation seed assumptions only. Replace with an exact, current municipality-specific transcription and human verification before formal issue.',
            'generationRates'=>[
                'residential_dwelling'=>['label'=>'Residential dwelling','unit'=>'dwelling','litresPerUnitPerDay'=>120],
                'office_person'=>['label'=>'Office population','unit'=>'person','litresPerUnitPerDay'=>15],
                'retail_floor_area'=>['label'=>'Retail floor area','unit'=>'100 m2','litresPerUnitPerDay'=>45],
            ],
            'defaultCollectionIntervalDays'=>7,'defaultContingencyPercent'=>10,'circulationAllowancePercent'=>25,
            'bins'=>[
                'standard_240l'=>['label'=>'240 litre wheeled bin','capacityLitres'=>240,'footprintM2'=>0.36],
                'standard_1100l'=>['label'=>'1 100 litre mobile bin','capacityLitres'=>1100,'footprintM2'=>0.9],
            ],
            'requiredFacilities'=>[
                'collectionVehicleAccess'=>'collection vehicle access','washdownWater'=>'wash-down water point','drainage'=>'compliant drainage arrangement','ventilation'=>'ventilation provision','wasteSeparation'=>'waste-separation provision',
            ],
        ];
    }

    private function requiredPattern(mixed$value,string$pattern,string$error): string{if(!is_string($value)||preg_match($pattern,$value)!==1)throw new ComplianceException($error);return$value;}
    private function requiredText(mixed$value,int$max,string$error): string{if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new ComplianceException($error);return trim($value);}
    private function date(mixed$value): string{if(!is_string($value))throw new ComplianceException('invalid_effective_date');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new ComplianceException('invalid_effective_date');return$value;}
    /** @param array<string,mixed> $value */
    private function json(array$value): string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);}
}

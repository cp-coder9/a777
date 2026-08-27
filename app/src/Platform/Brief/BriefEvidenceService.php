<?php

declare(strict_types=1);

namespace Architex\Platform\Brief;

use Architex\Http\UploadedFile;
use Architex\Platform\Audit\AuditService;
use Architex\Platform\Files\FileDownload;
use Architex\Platform\Files\FileException;
use Architex\Platform\Files\LocalFileStorage;
use Architex\Platform\Identity\Identifier;
use DateTimeImmutable;
use finfo;
use PDO;
use Throwable;

final readonly class BriefEvidenceService
{
    /** @var array<string,list<string>> */
    private const ALLOWED_TYPES=[
        'application/pdf'=>['pdf'],'image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],
        'application/zip'=>['docx'],'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>['docx'],
    ];
    private const CATEGORIES=['existing_plan','title_deed','site_photo','brief_document','other'];
    public function __construct(private PDO $pdo,private LocalFileStorage $storage,private int $maximumBytes=25_000_000){}

    /** @return array<string,mixed> */
    public function upload(string $organisationId,string $userId,string $briefId,UploadedFile $upload,string $category,string $purpose,string $retentionUntil,bool $consentGiven):array
    {
        $this->assertDraftOwner($organisationId,$userId,$briefId);
        if(!$consentGiven)throw new BriefException('brief_evidence_consent_required');
        $purpose=trim($purpose);if($purpose===''||mb_strlen($purpose)>500)throw new BriefException('invalid_brief_evidence_purpose');
        $retention=$this->retention($retentionUntil);if(!in_array($category,self::CATEGORIES,true))throw new BriefException('invalid_brief_evidence_category');
        if($upload->error!==UPLOAD_ERR_OK||$upload->size<1||!is_file($upload->temporaryPath))throw new FileException('invalid_upload','The uploaded file is invalid.');
        if($upload->size>$this->maximumBytes)throw new FileException('file_too_large','The uploaded file is too large.');
        $name=$this->safeName($upload->clientName);$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload->temporaryPath);
        if(!is_string($mime)||!isset(self::ALLOWED_TYPES[$mime])||!in_array($extension,self::ALLOWED_TYPES[$mime],true))throw new FileException('unsafe_file','The file type is not allowed.');
        $size=filesize($upload->temporaryPath);$sha256=hash_file('sha256',$upload->temporaryPath);if(!is_int($size)||$size!==$upload->size||!is_string($sha256))throw new FileException('invalid_upload','The uploaded file changed during validation.');
        $id=Identifier::generate();$storageName=bin2hex(random_bytes(24));$path=$this->storage->put($organisationId,$storageName,$upload->temporaryPath,$upload->httpUpload);
        if(filesize($path)!==$size||!hash_equals($sha256,(string)hash_file('sha256',$path))){$this->storage->delete($organisationId,$storageName);throw new FileException('storage_unavailable','The stored file failed its integrity check.');}
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('INSERT INTO guided_brief_files(id,organisation_id,brief_id,uploaded_by,category,original_name,storage_name,mime_type,byte_size,sha256,processing_purpose,consent_at,retention_until) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:brief_id),UNHEX(:user_id),:category,:name,:storage_name,:mime,:size,:sha256,:purpose,UTC_TIMESTAMP(6),:retention)')->execute(['id'=>$id,'organisation_id'=>$organisationId,'brief_id'=>$briefId,'user_id'=>$userId,'category'=>$category,'name'=>$name,'storage_name'=>$storageName,'mime'=>$mime,'size'=>$size,'sha256'=>$sha256,'purpose'=>$purpose,'retention'=>$retention]);
            (new AuditService($this->pdo))->append($organisationId,$userId,'guided_brief.file_uploaded','guided_brief_file',$id,['briefId'=>$briefId,'category'=>$category,'mimeType'=>$mime,'byteSize'=>$size,'sha256'=>$sha256,'retentionUntil'=>$retention]);$this->pdo->commit();
        }catch(Throwable $exception){if($this->pdo->inTransaction())$this->pdo->rollBack();$this->storage->delete($organisationId,$storageName);throw$exception;}
        return['id'=>$id,'briefId'=>$briefId,'category'=>$category,'originalName'=>$name,'mimeType'=>$mime,'byteSize'=>$size,'sha256'=>$sha256,'retentionUntil'=>$retention];
    }

    /** @return list<array<string,mixed>> */
    public function list(string $organisationId,string $userId,string $briefId):array
    {
        $this->assertOwner($organisationId,$userId,$briefId);$statement=$this->pdo->prepare("SELECT LOWER(HEX(f.id)) id,f.category,f.original_name,f.mime_type,f.byte_size,f.sha256,DATE_FORMAT(f.retention_until,'%Y-%m-%d') retention_until,f.created_at FROM guided_brief_files f WHERE f.organisation_id=UNHEX(:organisation_id) AND f.brief_id=UNHEX(:brief_id) AND f.uploaded_by=UNHEX(:user_id) AND f.status='active' ORDER BY f.created_at,f.id");$statement->execute(['organisation_id'=>$organisationId,'brief_id'=>$briefId,'user_id'=>$userId]);$rows=[];foreach($statement as $row)$rows[]=['id'=>$row['id'],'briefId'=>$briefId,'category'=>$row['category'],'originalName'=>$row['original_name'],'mimeType'=>$row['mime_type'],'byteSize'=>(int)$row['byte_size'],'sha256'=>$row['sha256'],'retentionUntil'=>$row['retention_until'],'createdAt'=>$row['created_at']];return$rows;
    }

    public function download(string $organisationId,string $userId,string $fileId):FileDownload
    {
        $statement=$this->pdo->prepare("SELECT f.original_name,f.storage_name,f.mime_type,f.byte_size,f.sha256 FROM guided_brief_files f JOIN guided_briefs b ON b.id=f.brief_id AND b.organisation_id=f.organisation_id AND b.created_by=UNHEX(:user_id) JOIN organisation_memberships m ON m.organisation_id=f.organisation_id AND m.user_id=UNHEX(:member_user_id) AND m.status='active' WHERE f.id=UNHEX(:file_id) AND f.organisation_id=UNHEX(:organisation_id) AND f.status='active'");$statement->execute(['user_id'=>$userId,'member_user_id'=>$userId,'file_id'=>$fileId,'organisation_id'=>$organisationId]);$row=$statement->fetch();if(!is_array($row))throw new BriefException('brief_file_not_found');
        $path=$this->storage->path($organisationId,(string)$row['storage_name']);$contents=file_get_contents($path);if(!is_string($contents)||strlen($contents)!==(int)$row['byte_size']||!hash_equals((string)$row['sha256'],hash('sha256',$contents)))throw new FileException('storage_unavailable','The stored file failed its integrity check.');
        return new FileDownload((string)$row['original_name'],(string)$row['mime_type'],(int)$row['byte_size'],(string)$row['sha256'],$path,$contents);
    }

    private function assertDraftOwner(string $organisationId,string $userId,string $briefId):void{$statement=$this->pdo->prepare("SELECT b.status FROM guided_briefs b JOIN organisation_memberships m ON m.organisation_id=b.organisation_id AND m.user_id=UNHEX(:user_id) AND m.status='active' WHERE b.id=UNHEX(:brief_id) AND b.organisation_id=UNHEX(:organisation_id) AND b.created_by=UNHEX(:creator_id)");$statement->execute(['user_id'=>$userId,'creator_id'=>$userId,'brief_id'=>$briefId,'organisation_id'=>$organisationId]);$status=$statement->fetchColumn();if(!is_string($status))throw new BriefException('brief_not_found');if($status!=='draft')throw new BriefException('brief_locked');}
    private function assertOwner(string $organisationId,string $userId,string $briefId):void{$statement=$this->pdo->prepare("SELECT COUNT(*) FROM guided_briefs b JOIN organisation_memberships m ON m.organisation_id=b.organisation_id AND m.user_id=UNHEX(:user_id) AND m.status='active' WHERE b.id=UNHEX(:brief_id) AND b.organisation_id=UNHEX(:organisation_id) AND b.created_by=UNHEX(:creator_id)");$statement->execute(['user_id'=>$userId,'creator_id'=>$userId,'brief_id'=>$briefId,'organisation_id'=>$organisationId]);if((int)$statement->fetchColumn()!==1)throw new BriefException('brief_not_found');}
    private function retention(string $value):string{if(preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$value)!==1)throw new BriefException('invalid_brief_evidence_retention');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);$today=new DateTimeImmutable('today');$limit=$today->modify('+10 years');if(!$date||$date->format('Y-m-d')!==$value||$date<$today||$date>$limit)throw new BriefException('invalid_brief_evidence_retention');return$value;}
    private function safeName(string $name):string{$name=basename(str_replace('\\','/',trim($name)));$name=preg_replace('/[\x00-\x1F\x7F]/u','',$name)??'';if($name===''||$name==='.'||$name==='..'||strlen($name)>255)throw new FileException('invalid_upload','The uploaded filename is invalid.');return$name;}
}

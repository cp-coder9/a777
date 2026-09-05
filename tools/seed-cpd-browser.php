<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';

use Architex\Infrastructure\Database\DisposableDatabaseGuard;
use Architex\Platform\CPD\CPDAssessmentService;
use Architex\Platform\Identity\Identifier;

$environment=[];
foreach(['ARCHITEX_ALLOW_DISPOSABLE_DB','APP_ENV','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $key)$environment[$key]=(string)getenv($key);
DisposableDatabaseGuard::assertAllowed($environment);
$password=(string)getenv('CPD_BROWSER_PASSWORD');
if(strlen($password)<16)throw new RuntimeException('Provide CPD_BROWSER_PASSWORD with at least 16 characters.');
$pdo=new PDO('mysql:host='.$environment['DB_HOST'].';port='.$environment['DB_PORT'].';dbname='.$environment['DB_NAME'].';charset=utf8mb4',$environment['DB_USER'],$environment['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$find=$pdo->prepare('SELECT LOWER(HEX(id)) FROM organisations WHERE slug=:slug');
$find->execute(['slug'=>'cpd-browser-recertification']);
$organisation=$find->fetchColumn();
if(!$organisation){$organisation=Identifier::generate();$pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'CPD Browser Recertification','cpd-browser-recertification','active')")->execute(['id'=>$organisation]);}
$users=[];
foreach(['platform_admin','learner','content_creator','cpd_reviewer','accreditation_partner'] as $role){
    $email='cpd.browser.'.$role.'@local.test';
    $find=$pdo->prepare('SELECT LOWER(HEX(id)) FROM users WHERE email=:email');$find->execute(['email'=>$email]);$id=$find->fetchColumn();
    if(!$id){$id=Identifier::generate();$pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,:hash,'active',UTC_TIMESTAMP(6))")->execute(['id'=>$id,'email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT)]);}
    else{$pdo->prepare('UPDATE users SET password_hash=:hash WHERE id=UNHEX(:id)')->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);}
    $membership=$role==='platform_admin'?'owner':'member';
    $find=$pdo->prepare('SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u)');$find->execute(['o'=>$organisation,'u'=>$id]);
    if(!$find->fetchColumn())$pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$organisation,'u'=>$id,'role'=>$membership]);
    $users[$role]=$id;
}
$service=new CPDAssessmentService($pdo);
foreach($users as $role=>$id){
    $service->saveProfile($organisation,$id,$role==='platform_admin'?'owner':'member',[
        'fullName'=>'Browser '.str_replace('_',' ',$role),'profession'=>'Architect','professionalBody'=>'SACAP',
        'registrationNumber'=>'LOCAL-'.strtoupper($role),'registrationCategory'=>'PrArch','cycleStart'=>'2026-01-01','cycleEnd'=>'2030-12-31',
        'participationRole'=>$role==='platform_admin'?'platform_admin':'learner','popiaConsent'=>true,
    ]);
    if(!in_array($role,['platform_admin','learner'],true))$service->assignParticipationRole($organisation,$users['platform_admin'],'owner',$id,['participationRole'=>$role,'reason'=>'Local browser certification fixture.','humanConfirmed'=>true]);
}
echo json_encode(['organisationId'=>$organisation,'users'=>$users],JSON_THROW_ON_ERROR).PHP_EOL;

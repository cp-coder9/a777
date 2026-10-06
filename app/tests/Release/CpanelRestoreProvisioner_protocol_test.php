<?php
declare(strict_types=1);
// Native PHP protocol proof; reflection-injected HTTPS is SYNTHETIC, not account/provider proof.
require_once __DIR__.'/../../src/Infrastructure/Backup/CpanelRestoreProvisioner.php';
require_once __DIR__.'/../../src/Infrastructure/Backup/AccountOwnedRestoreTarget.php';
use Architex\Infrastructure\Backup\CpanelRestoreProvisioner as P;
use Architex\Infrastructure\Backup\AccountOwnedRestoreTarget as T;
$checks=0;
function check(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('Protocol assertion failed.'); ++$checks; }
function refuses(Closure $call): void { try { $call(); } catch (RuntimeException) { check(true); return; } throw new RuntimeException('Expected refusal.'); }
$r=new ReflectionClass(P::class);
$decode=$r->getMethod('decode');
check($decode->invoke(null,'{"status":1,"errors":null,"data":null}',200)['data']===null);
foreach (['{"status":true,"data":null}','{"status":0,"data":null}','{"status":1,"errors":["denied"],"data":null}','{"status":1}','garbage'] as $bad) refuses(fn()=>$decode->invoke(null,$bad,200));
refuses(fn()=>$decode->invoke(null,'{"status":1,"data":null}',302));
$priv=$r->getMethod('allPrivileges');
check($priv->invoke(null,['ALL PRIVILEGES']));
$full=['ALTER','ALTER ROUTINE','CREATE','CREATE ROUTINE','CREATE TEMPORARY TABLES','CREATE VIEW','DELETE','DROP','EVENT','EXECUTE','INDEX','INSERT','LOCK TABLES','REFERENCES','SELECT','SHOW VIEW','TRIGGER','UPDATE'];
check($priv->invoke(null,$full)); check(!$priv->invoke(null,true)); check(!$priv->invoke(null,['SELECT'])); check(!$priv->invoke(null,array_merge($full,['GRANT OPTION'])));
$name='archite4_ar_0123456789abcdef_test';
foreach (['success','collision','home','partialGrant','lostCreate'] as $scenario) {
    $calls=[]; $created=false;
    $transport=static function(string $module,string $function,array $args) use (&$calls,&$created,$name,$scenario,$full): string {
        $calls[]=[$module,$function,$args];
        if ($module==='Variables') $data=['home'=>$scenario==='home'?'/home/other':'/home/archite4'];
        else $data=match($function) {
            'list_databases'=>array_merge([['database'=>'archite4_dev_rel1']],($created||$scenario==='collision')?[['database'=>$name]]:[]),
            'list_users'=>[['user'=>'archite4_app']],
            'create_database'=>(static function() use (&$created,$scenario) { $created=true; if($scenario==='lostCreate') throw new RuntimeException('Synthetic lost reply'); return null; })(),
            'set_privileges_on_database'=>null,
            'get_privileges_on_database'=>$scenario==='partialGrant'?['SELECT']:$full,
            default=>throw new RuntimeException('Unexpected operation'),
        };
        return json_encode(['status'=>1,'errors'=>null,'data'=>$data],JSON_THROW_ON_ERROR);
    };
    $provider=$r->newInstanceWithoutConstructor(); $r->getConstructor()->invoke($provider,'synthetic-token-not-real',$transport);
    $map=new WeakMap(); $map[$provider]=true; $r->getProperty('authenticated')->setValue(null,$map);
    if($scenario==='success') { $receipt=$provider->provision($name,'archite4_dev_rel1','archite4_app',[]); check($receipt['phase']==='verified'); check($receipt['create']['data']===null); check($receipt['grant']['data']===null); }
    else refuses(fn()=>$provider->provision($name,'archite4_dev_rel1','archite4_app',[]));
    foreach($calls as $i=>$call) if($call[0]==='Mysql') check($i>0 && $calls[$i-1][0]==='Variables');
    if(in_array($scenario,['collision','home'],true)) check(!array_filter($calls,fn($c)=>in_array($c[1],['create_database','set_privileges_on_database'],true)));
    check(!array_filter($calls,fn($c)=>str_contains($c[1],'delete')));
}
$unissued=$r->newInstanceWithoutConstructor(); refuses(fn()=>$unissued->assertAuthenticated()); refuses(fn()=>serialize($unissued));
$target=(new ReflectionClass(T::class))->newInstanceWithoutConstructor(); refuses(fn()=>serialize($target));
require_once __DIR__.'/../../src/Infrastructure/Config/AppConfig.php';
$actualConfig=\Architex\Infrastructure\Config\AppConfig::fromEnvironment(['APP_ENV'=>'development','DB_HOST'=>'managed.example.test','DB_NAME'=>'archite4_dev_rel1','DB_USER'=>'archite4_app','DB_PASSWORD'=>'synthetic']);
$pdo=new class extends PDO { public function __construct() {} public function getAttribute(int $attribute): mixed { throw new LogicException('Must refuse pin before any PDO access.'); } };
refuses(fn()=>T::preflightManaged($pdo,$actualConfig,str_repeat('0',64),$unissued));
refuses(fn()=>T::provisionManaged($pdo,$actualConfig,str_repeat('0',64),$unissued));
if (PHP_OS_FAMILY === 'Windows') refuses(fn()=>P::fromAccountToken()); // No factory/network call on a real provider host.
// New cases extend (never replace) the original 55 assertions.
check($decode->invoke(null,'{"result":{"status":1,"errors":[],"data":null}}',200)['data']===null);
foreach (['{"result":{"status":true,"errors":null,"data":null}}', '{"result":{"status":1,"errors":false,"data":null}}', '{"result":{"status":1,"errors":"0","data":null}}', '{"result":{"status":1,"errors":null}}', '{"status":1,"errors":null,"data":null,"result":{"status":1,"errors":null,"data":null}}', '{"status":1,"errors":0,"data":null}', '{"status":1,"errors":"","data":null}', '{"status":1,"data":null}'] as $bad) refuses(fn()=>$decode->invoke(null,$bad,200));
$production=\Architex\Infrastructure\Config\AppConfig::fromEnvironment(['APP_ENV'=>'production','DB_HOST'=>'managed.example.test','DB_NAME'=>'archite4_dev_rel1','DB_USER'=>'archite4','DB_PASSWORD'=>'synthetic']);
$sourceCheck=(new ReflectionClass(T::class))->getMethod('assertManagedSourceConfig');
$sourceCheck->invoke(null,$production,$production); check($production->appEnv==='production');
refuses(fn()=>$sourceCheck->invoke(null,$production,$actualConfig));
$wrong=\Architex\Infrastructure\Config\AppConfig::fromEnvironment(['APP_ENV'=>'production','DB_HOST'=>'managed.example.test','DB_NAME'=>'archite4_production','DB_USER'=>'archite4','DB_PASSWORD'=>'synthetic']);
refuses(fn()=>$sourceCheck->invoke(null,$wrong,$wrong));
foreach (['archite4_dev_rel1','archite4_production','archite4_ar_0123456789abcdef','archite4_restore_0123456789abcdef'] as $unsafe) refuses(fn()=>$unissued->provision($unsafe,'archite4_dev_rel1','archite4',[]));
$sourceCode=file_get_contents((new ReflectionClass(T::class))->getFileName());
refuses(fn()=>$target->assertEmpty($pdo)); // Private issuance state, not a JSON/provider attestation.
$readonlyCalls=[];
$readonlyTransport=static function(string $module,string $function,array $args) use (&$readonlyCalls): string {
    $readonlyCalls[]=[$module,$function,$args];
    $data=match($function) {
        'get_user_information'=>['home'=>'/home/archite4'],
        'list_databases'=>[['database'=>'archite4_dev_rel1']],
        'list_users'=>[['user'=>'archite4']],
        default=>throw new RuntimeException('Read-only fixture forbids mutation'),
    };
    return json_encode(['result'=>['status'=>1,'errors'=>null,'data'=>$data]],JSON_THROW_ON_ERROR);
};
$readonly=$r->newInstanceWithoutConstructor(); $r->getConstructor()->invoke($readonly,'synthetic-token-not-real',$readonlyTransport);
$map=new WeakMap(); $map[$readonly]=true; $r->getProperty('authenticated')->setValue(null,$map);
$readonly->preflight($production->dbName,$production->dbUser);
check(array_column($readonlyCalls,1)===['get_user_information','list_databases','get_user_information','list_users']);
check(!array_filter($readonlyCalls,fn($call)=>in_array($call[1],['create_database','set_privileges_on_database'],true)));
check(str_contains($sourceCode, "'/home/archite4/public_html/.a777-runtime-active/config/runtime.php'"));
check(!str_contains($sourceCode, "dirname(__DIR__, 3) . '/config/runtime.php'"));
check(str_contains($sourceCode, '!== 0700') && str_contains($sourceCode, '!== 0600'));
$providerCode=file_get_contents($r->getFileName());
check(str_contains($providerCode,'CURLOPT_POST=>true') && str_contains($providerCode,'CURLOPT_POSTFIELDS=>http_build_query'));
check(str_contains($providerCode,"CURLOPT_PROXY=>''") && str_contains($providerCode,'CURLOPT_FOLLOWLOCATION=>false') && str_contains($providerCode,'CURLOPT_SSL_VERIFYPEER=>true'));
foreach ([P::class,T::class] as $class) {
    $file=str_replace('\\','/',(new ReflectionClass($class))->getFileName());
    check($file===str_replace('\\','/',realpath(__DIR__.'/../../src/Infrastructure/Backup/'.(new ReflectionClass($class))->getShortName().'.php')));
    check(hash_file('sha256',$file)===hash('sha256',file_get_contents($file)));
}
printf("PASS %d native assertions; reflection HTTPS fixtures are synthetic; no live DDL.\n",$checks);

<?php
declare(strict_types=1);
namespace Architex\Deployment;
use PDO;
use RuntimeException;
use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
/** Dedicated one-use migration identity. Never modifies an existing user's password. */
final class CpanelNativeOperator
{
    public static function validName(string $name): bool { return preg_match('/\Aarchite4_nd_[a-f0-9]{16}\z/',$name)===1; }
    private static function need(bool $ok): void { if (!$ok) throw new RuntimeException('native_operator_refused'); }
    private static function api(string $operation,array $args): mixed
    {
        self::need(in_array($operation,['create_user','get_privileges_on_database','set_privileges_on_database','revoke_access_to_database'],true));
        $user=$args[$operation==='create_user'?'name':'user']??'';
        self::need(is_string($user) && self::validName($user));
        $keys=array_keys($args); sort($keys);
        $expected=match($operation) {'create_user'=>['name','password'],'set_privileges_on_database'=>['database','privileges','user'],default=>['database','user']};
        self::need($keys===$expected);
        if ($operation!=='create_user') self::need($args['database']==='archite4_dev_rel1');
        if ($operation==='set_privileges_on_database') self::need($args['privileges']==='ALL PRIVILEGES');
        return \CpanelMaintenanceApi::operator($operation,$args);
    }

    public static function prepare(array $values, callable $journal): array
    {
        $name='archite4_nd_'.bin2hex(random_bytes(8)); $password=bin2hex(random_bytes(32));
        $users=\CpanelMaintenanceApi::inventory('list_users');
        foreach($users as $row) { self::need(is_array($row) && is_string($row['user']??null)); self::need($row['user']!==$name); }
        $state=['name'=>$name,'password'=>$password,'createStarted'=>true,'created'=>false];
        $journal(['phase'=>'operator-create-intent','operator'=>$state]);
        // Caller owns state before mutation even if response is lost.
        return $state;
    }
    public static function create(array &$state,callable $journal): void
    {
        try { self::api('create_user',['name'=>$state['name'],'password'=>$state['password']]); }
        catch(\Throwable $e) { throw $e; }
        $matches=array_filter(\CpanelMaintenanceApi::inventory('list_users'),static fn($r)=>is_array($r)&&($r['user']??null)===$state['name']);
        self::need(count($matches)===1); $state['created']=true;
        $journal(['phase'=>'operator-created','name'=>$state['name']]);
        self::need(self::privileges($state)===[]);
        $journal(['phase'=>'operator-grant-intent','name'=>$state['name'],'database'=>'archite4_dev_rel1']);
        self::api('set_privileges_on_database',['user'=>$state['name'],'database'=>'archite4_dev_rel1','privileges'=>'ALL PRIVILEGES']);
        $required=['ALTER','ALTER ROUTINE','CREATE','CREATE ROUTINE','CREATE TEMPORARY TABLES','CREATE VIEW','DELETE','DROP','EVENT','EXECUTE','INDEX','INSERT','LOCK TABLES','REFERENCES','SELECT','SHOW VIEW','TRIGGER','UPDATE'];
        $read=self::privileges($state); sort($read); sort($required); self::need($read===['ALL PRIVILEGES'] || $read===$required);
        $databases=\CpanelMaintenanceApi::inventory('list_databases'); $found=false;
        foreach($databases as $row) { self::need(is_array($row)&&is_array($row['users']??null)); if(($row['database']??null)==='archite4_dev_rel1') { self::need($row['users']===[$state['name']]); $found=true; } else self::need(!in_array($state['name'],$row['users'],true)); }
        self::need($found);
    }
    public static function connect(array $state,array $values): PDO
    {
        $values['DB_USER']=$state['name']; $values['DB_PASSWORD']=$state['password'];
        $pdo=ConnectionFactory::create(AppConfig::fromEnvironment($values));
        self::need($pdo->query('SELECT DATABASE()')->fetchColumn()==='archite4_dev_rel1' && explode('@',(string)$pdo->query('SELECT CURRENT_USER()')->fetchColumn())[0]===$state['name']);
        $capabilities=$pdo->query("SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE TABLE_SCHEMA=DATABASE() AND GRANTEE=CONCAT(CHAR(39),SUBSTRING_INDEX(CURRENT_USER(),'@',1),CHAR(39),'@',CHAR(39),SUBSTRING_INDEX(CURRENT_USER(),'@',-1),CHAR(39))")->fetchAll(PDO::FETCH_COLUMN);
        foreach(['ALTER','ALTER ROUTINE','CREATE','CREATE ROUTINE','CREATE TEMPORARY TABLES','CREATE VIEW','DELETE','DROP','EVENT','EXECUTE','INDEX','INSERT','LOCK TABLES','REFERENCES','SELECT','SHOW VIEW','TRIGGER','UPDATE'] as $required) self::need(in_array($required,$capabilities,true));
        return $pdo;
    }
    private static function privileges(array $state): array
    {
        $read=self::api('get_privileges_on_database',['user'=>$state['name'],'database'=>'archite4_dev_rel1']); self::need(is_array($read)&&array_is_list($read)); return $read;
    }
    public static function revoke(array $state,array $values,callable $journal): void
    {
        self::need($state['created']===true);
        $journal(['phase'=>'operator-revoke-intent','name'=>$state['name'],'database'=>'archite4_dev_rel1']);
        try { self::api('revoke_access_to_database',['user'=>$state['name'],'database'=>'archite4_dev_rel1']); } catch(\Throwable) {}
        self::need(self::privileges($state)===[]);
        try { self::connect($state,$values)->query('SELECT * FROM schema_migrations LIMIT 1'); }
        catch(\PDOException $e) { self::need(in_array((int)($e->errorInfo[1]??0),[1044,1045,1142],true)); $journal(['phase'=>'operator-revoked','name'=>$state['name'],'retainedUnprivileged'=>true]); return; }
        throw new RuntimeException('native_operator_negative_probe_failed');
    }
}

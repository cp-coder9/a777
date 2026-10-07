<?php
declare(strict_types=1);
namespace Architex\Deployment;
use RuntimeException;
/** Fixed approved relocation. No application/database execution or provider mutations. */
final class CpanelPrivateRuntimeRelocation
{
    public const SOURCE='/home/archite4/public_html/.a777-runtime-active';
    public const DESTINATION='/home/archite4/.a777-runtime-private/7eee8a25050fadb3d36b74c40f485061';
    private static function need(bool $ok): void { if (!$ok) throw new RuntimeException('recovery_evidence_refused'); }
    public static function approval(array $plan): void
    {
        self::need(($plan['privateRuntimeRelocation']??null)===true && ($plan['privateRuntimeDestination']??null)===self::DESTINATION);
    }
    /** DomainInfo/domains_data, authenticated against Variables home by the transport. */
    public static function documentRoots(array $data): array
    {
        self::need(isset($data['main_domain']) && is_array($data['main_domain']));
        $roots=['/home/archite4/public_html'=>true];
        foreach (['main_domain','addon_domains','sub_domains','parked_domains'] as $key) {
            self::need(array_key_exists($key,$data) && is_array($data[$key]));
            $rows=$key==='main_domain'?[$data[$key]]:$data[$key];
            foreach ($rows as $row) {
                // Parked names are aliases of the authenticated main-domain root.
                if ($key==='parked_domains' && is_string($row) && $row!=='') continue;
                self::need(is_array($row) && is_string($row['documentroot']??null));
                $root=rtrim($row['documentroot'],'/');
                self::need(str_starts_with($root,'/home/archite4/') && realpath($root)===$root);
                self::ancestry($root,2657); $roots[$root]=true;
            }
        }
        $out=array_keys($roots); sort($out,SORT_STRING); return $out;
    }
    private static function ancestry(string $path,int $uid): void
    {
        clearstatcache(true); self::need(realpath($path)===$path);
        for ($p=$path;;$p=dirname($p)) {
            $s=lstat($p); self::need(is_array($s) && ($s['mode']&0170000)===0040000 && in_array($s['uid'],[0,$uid],true) && ($s['mode']&0022)===0);
            if ($p==='/') break;
        }
    }
    private static function outside(string $path,array $roots): void
    {
        self::need($roots!==[]);
        foreach ($roots as $root) self::need(is_string($root) && $root!=='' && $path!==$root && !str_starts_with($path,$root.'/'));
    }
    private static function snapshot(string $root,int $uid): array
    {
        self::ancestry($root,$uid); $out=[];
        $paths=[$root];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST) as $f) $paths[]=$f->getPathname();
        foreach ($paths as $path) {
            clearstatcache(true,$path); $s=lstat($path); $type=$s['mode']&0170000;
            self::need(realpath($path)===$path && $s['uid']===$uid && ($s['mode']&0022)===0 && in_array($type,[0040000,0100000],true));
            $entry=['dev'=>$s['dev'],'ino'=>$s['ino'],'uid'=>$s['uid'],'type'=>$type];
            if ($type===0100000) {
                self::need($s['nlink']===1); $h=fopen($path,'rb'); self::need(is_resource($h));
                try {
                    $identity=static function(array $v): array { return array_intersect_key($v,array_flip(['dev','ino','mode','uid','gid','nlink','size','mtime','ctime'])); };
                    self::need($identity(fstat($h))===$identity($s));
                    $ctx=hash_init('sha256'); hash_update_stream($ctx,$h); $entry+=['bytes'=>$s['size'],'sha256'=>hash_final($ctx)];
                    clearstatcache(true,$path); self::need($identity(fstat($h))===$identity($s) && $identity(lstat($path))===$identity($s));
                }
                finally { fclose($h); }
            }
            $out[$path===$root?'':substr($path,strlen($root)+1)]=$entry;
        }
        ksort($out,SORT_STRING); return $out;
    }
    private const MUTABLE_LOG='storage/logs/application.jsonl';
    /** Only the operational logger may drift; all directories and other files stay pinned. */
    private static function compare(array $before,array $actual,string $journal,int $uid,?string $root=null): void
    {
        $present=isset($actual[self::MUTABLE_LOG]);
        if ($present) self::need($actual[self::MUTABLE_LOG]['type']===0100000);
        // JsonLogger::toFile does mkdir(0700, recursive): allow ONLY its two
        // exact new scaffolding directories, never an arbitrary storage subtree.
        foreach (['storage','storage/logs'] as $directory) if (!isset($before[$directory]) && isset($actual[$directory])) {
            self::need($root!==null && $actual[$directory]['type']===0040000);
            self::ancestry($root.'/'.$directory,$uid);
            self::need((lstat($root.'/'.$directory)['mode']&07777)===0700);
            unset($actual[$directory]);
        }
        unset($before[self::MUTABLE_LOG],$actual[self::MUTABLE_LOG]);
        $changed=0;
        foreach (array_unique(array_merge(array_keys($before),array_keys($actual))) as $name) {
            if (($before[$name]??null)!==($actual[$name]??null)) ++$changed;
        }
        // Fixed-shape private diagnostics, never names, hashes, contents or journal data.
        $dir=dirname($journal); self::ancestry($dir,$uid);
        self::need((lstat($dir)['mode']&07777)===0700 && lstat($dir)['uid']===$uid);
        $path=$dir.'/relocation-inventory-check.json';
        clearstatcache(true,$path);
        if (file_exists($path)||is_link($path)) {
            $s=lstat($path); self::need(realpath($path)===$path && ($s['mode']&0170000)===0100000 && $s['uid']===$uid && $s['nlink']===1 && ($s['mode']&07777)===0600);
        }
        $tmp=$path.'.'.bin2hex(random_bytes(8)); $f=fopen($tmp,'xb'); self::need(is_resource($f));
        try {
            self::need(chmod($tmp,0600));
            $bytes=json_encode(['expectedImmutableCount'=>count($before),'actualImmutableCount'=>count($actual),'mutableLogPresent'=>$present,'changedImmutableCount'=>$changed],JSON_THROW_ON_ERROR)."\n";
            self::need(fwrite($f,$bytes)===strlen($bytes) && fflush($f));
            if (function_exists('fsync')) self::need(fsync($f));
        } finally { fclose($f); }
        self::need(rename($tmp,$path));
        self::need($changed===0);
    }
    public static function prepareLogPrivacy(string $root,array $h,string $journal): void
    {
        self::need(in_array($root,[self::SOURCE,self::DESTINATION],true) && PHP_OS_FAMILY==='Linux' && posix_geteuid()===2657);
        self::repairLogPrivacy($root,2657,$h,$journal);
    }
    private static function repairLogPrivacy(string $root,int $uid,array $h,string $journal): void
    {
        $path=$root.'/'.self::MUTABLE_LOG;
        clearstatcache(true,$path); if (!file_exists($path) && !is_link($path)) return;
        $before=self::snapshot($root,$uid);
        self::need($before[self::MUTABLE_LOG]['type']===0100000);
        foreach ([$root,$root.'/storage',$root.'/storage/logs'] as $dir) {
            self::ancestry($dir,$uid); $s=lstat($dir); self::need($s['uid']===$uid && ($s['mode']&07777)===0700);
        }
        if ((lstat($path)['mode']&07777)!==0600) {
            $h['append']($journal,['phase'=>'private-runtime-mode-intent','path'=>$path,'mode'=>0600,'inode'=>$before[self::MUTABLE_LOG]['ino']]);
            self::need(chmod($path,0600));
            self::need(self::snapshot($root,$uid)===$before);
            $h['append']($journal,['phase'=>'private-runtime-log-mode-repaired','inode'=>$before[self::MUTABLE_LOG]['ino'],'bytes'=>$before[self::MUTABLE_LOG]['bytes'],'sha256'=>$before[self::MUTABLE_LOG]['sha256']]);
        }
    }
    public static function prove(array $roots): void
    {
        self::outside(self::DESTINATION,$roots); self::ancestry(self::DESTINATION,2657);
        self::need(!file_exists(self::SOURCE) && !is_link(self::SOURCE));
        self::snapshot(self::DESTINATION,2657);
        self::controls(self::DESTINATION,2657);
    }
    private static function controls(string $root,int $uid): void
    {
        foreach (self::snapshot($root,$uid) as $name=>$s) {
            $path=$root.($name===''?'':'/'.$name); clearstatcache(true,$path);
            self::need((lstat($path)['mode']&07777)===($s['type']===0040000?0700:0600));
        }
        clearstatcache(true,dirname($root)); self::need((lstat(dirname($root))['mode']&07777)===0700 && lstat(dirname($root))['uid']===$uid);
    }
    public static function relocate(array $approval,array $roots,array $helpers,string $journal): void
    {
        self::approval($approval); self::need(PHP_OS_FAMILY==='Linux' && posix_geteuid()===2657);
        self::transition(self::SOURCE,self::DESTINATION,2657,$roots,$helpers,$journal);
        self::prove($roots);
    }
    /** Internal engine used by real POSIX scratch fixtures; production paths never caller-selected. */
    private static function transition(string $source,string $dest,int $uid,array $roots,array $h,string $journal): void
    {
        self::outside($dest,$roots); self::outside(dirname($dest),$roots);
        self::ancestry(dirname(dirname($dest)),$uid);
        $records=[]; if (is_file($journal)) foreach (file($journal,FILE_IGNORE_NEW_LINES) as $line) $records[]=json_decode($line,true,512,JSON_THROW_ON_ERROR);
        $intents=array_values(array_filter($records,static fn($r)=>($r['phase']??null)==='private-runtime-rename-intent'));
        self::need(count($intents)<=1);
        clearstatcache(true); $src=file_exists($source)||is_link($source); $dst=file_exists($dest)||is_link($dest);
        self::need($src!==$dst);
        if ($intents===[]) {
            self::need($src); $before=self::snapshot($source,$uid);
            $h['append']($journal,['phase'=>'private-runtime-rename-intent','source'=>$source,'destination'=>$dest,'inventory'=>$before]);
        } else {
            $intent=$intents[0]; self::need(($intent['source']??null)===$source && ($intent['destination']??null)===$dest && is_array($intent['inventory']??null)); $before=$intent['inventory'];
        }
        $parent=dirname($dest);
        if (!file_exists($parent) && !is_link($parent)) {
            $h['append']($journal,['phase'=>'private-runtime-root-create-intent','path'=>$parent]);
            self::need(mkdir($parent,0700));
        }
        self::ancestry($parent,$uid); $s=lstat($parent);
        self::need($s['uid']===$uid && ($s['mode']&07777)===0700);
        foreach (new \FilesystemIterator($parent,\FilesystemIterator::SKIP_DOTS) as $entry) self::need($entry->getPathname()===$dest);
        self::compare($before,self::snapshot($src?$source:$dest,$uid),$journal,$uid,$src?$source:$dest);
        if ($src) {
            self::need(lstat($source)['dev']===$s['dev']);
            // Rename itself is atomic. Lost reply is reconciled through persisted inode+inventory.
            self::need(rename($source,$dest));
        }
        clearstatcache(true); self::need(!file_exists($source) && !is_link($source));
        $current=self::snapshot($dest,$uid); self::compare($before,$current,$journal,$uid,$dest);
        foreach ($current as $name=>$entry) {
            $path=$dest.($name===''?'':'/'.$name); $mode=$entry['type']===0040000?0700:0600;
            clearstatcache(true,$path);
            if ((lstat($path)['mode']&07777)!==$mode) {
                $h['append']($journal,['phase'=>'private-runtime-mode-intent','path'=>$path,'mode'=>$mode,'inode'=>$entry['ino']]);
                self::need(chmod($path,$mode));
            }
        }
        // chmod is permission-only: require the opened-handle byte/inode inventory unchanged.
        self::need(self::snapshot($dest,$uid)===$current);
        self::controls($dest,$uid); self::compare($before,self::snapshot($dest,$uid),$journal,$uid,$dest);
        $h['append']($journal,['phase'=>'private-runtime-relocated','destination'=>$dest,'inode'=>$before['']['ino'],'inventorySha256'=>hash('sha256',json_encode($before,JSON_THROW_ON_ERROR))]);
    }
    public static function legacyShim(string $name): string
    {
        return str_replace(self::SOURCE,self::DESTINATION,CpanelNativeDeployment::publicShim($name));
    }
    public static function shim(string $name): string
    {
        return str_replace("declare(strict_types=1);\n","declare(strict_types=1);\numask(0077);\n",self::legacyShim($name));
    }
}

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
        self::need(self::snapshot($src?$source:$dest,$uid)===$before);
        if ($src) {
            self::need(lstat($source)['dev']===$s['dev']);
            // Rename itself is atomic. Lost reply is reconciled through persisted inode+inventory.
            self::need(rename($source,$dest));
        }
        clearstatcache(true); self::need(!file_exists($source) && !is_link($source) && self::snapshot($dest,$uid)===$before);
        foreach ($before as $name=>$entry) {
            $path=$dest.($name===''?'':'/'.$name); $mode=$entry['type']===0040000?0700:0600;
            clearstatcache(true,$path);
            if ((lstat($path)['mode']&07777)!==$mode) {
                $h['append']($journal,['phase'=>'private-runtime-mode-intent','path'=>$path,'mode'=>$mode,'inode'=>$entry['ino']]);
                self::need(chmod($path,$mode));
            }
        }
        self::controls($dest,$uid); self::need(self::snapshot($dest,$uid)===$before);
        $h['append']($journal,['phase'=>'private-runtime-relocated','destination'=>$dest,'inode'=>$before['']['ino'],'inventorySha256'=>hash('sha256',json_encode($before,JSON_THROW_ON_ERROR))]);
    }
    public static function shim(string $name): string
    {
        $legacy=CpanelNativeDeployment::publicShim($name);
        return str_replace(self::SOURCE,self::DESTINATION,$legacy);
    }
}

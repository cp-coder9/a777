<?php
declare(strict_types=1);
use Architex\Deployment\CpanelNativeRecovery;
require_once __DIR__.'/CpanelNativeRecovery.php';
/** Separate approval channel: never rewrites or consumes the original operator plan. */
final class CpanelNativeDeploymentRecovery
{
    private static function need(bool $ok): void { CpanelGitDeploy::requireValue($ok,'native_recovery_contract_refused'); }
    public static function privateBytes(string $path): string
    {
        clearstatcache(true);
        $s=@lstat($path); $parent=@lstat(dirname($path));
        self::need(realpath($path)===$path && !is_link($path) && is_array($s)
            && ($s['mode']&0170000)===0100000 && ($s['mode']&07777)===0600
            && $s['uid']===posix_geteuid() && $s['nlink']===1
            && realpath(dirname($path))===dirname($path) && is_array($parent)
            && ($parent['mode']&07777)===0700 && $parent['uid']===posix_geteuid());
        $f=fopen($path,'rb'); self::need(is_resource($f));
        try { self::need(fstat($f)===$s); $bytes=stream_get_contents($f,1048577); self::need(is_string($bytes)&&strlen($bytes)<=1048576); return $bytes; }
        finally { fclose($f); }
    }
    public static function receipt(array $outcome,array $p,string $sha): array
    {
        $ok=($outcome['status']??null)==='recovered_and_deployed'
            && ($outcome['sourceAppliedPriorCount']??null)===8 && ($outcome['sourceMigrationsReplayed']??null)===false
            && ($outcome['maintenanceRetained']??null)===false;
        foreach (['sourceAlreadyCurrent','appPrivilegesRestored','operatorPrivilegesRevoked','appConfigDeltaVerified','readinessPassed','artifactVerified'] as $key) $ok=$ok && ($outcome[$key]??null)===true;
        foreach (['operatorStateUnknown','sourcePrivilegeStateUnknown'] as $key) $ok=$ok && ($outcome[$key]??null)===false;
        foreach (['backupId','archiveSha256','artifactTreeSha256','artifactManifestSha256','buildSha256'] as $key) $ok=$ok && isset($outcome[$key],$p[$key]) && $outcome[$key]===$p[$key];
        $r=['format'=>'a777-cpanel-native-recover-v1','mode'=>'native-recover',
            'commit'=>$p['expectedRecoveryCommit'],'expectedRecoveryCommit'=>$p['expectedRecoveryCommit'],
            'releaseCommit'=>$p['releaseCommit'],'oldPlanSha256'=>$p['oldPlanSha256'],'planSha256'=>$sha,
            'recoveredDeploymentId'=>$p['recoveredDeploymentId'],'phase'=>$ok?'complete':'recovery_required',
            'deployed'=>$ok,'sourceMigrationsReplayed'=>false,'sourceAlreadyCurrent'=>$ok,
            'sourceAppliedPriorCount'=>$ok?8:0,'maintenanceActive'=>!$ok,'maintenanceRetained'=>!$ok,
            'appPrivilegesRestored'=>$ok,'operatorPrivilegesRevoked'=>$ok,'operatorStateUnknown'=>!$ok,
            'sourcePrivilegeStateUnknown'=>!$ok,'appConfigDeltaVerified'=>$ok,'readinessPassed'=>$ok,'artifactVerified'=>$ok];
        foreach (['backupId','archiveSha256','artifactTreeSha256','artifactManifestSha256','buildSha256'] as $key) $r[$key]=$ok?($outcome[$key]??null):null;
        return $r;
    }
    public static function run(string $pointer): void
    {
        $root=CpanelGitDeploy::PRIVATE_ROOT;
        $v=json_decode(self::privateBytes($pointer),true,32,JSON_THROW_ON_ERROR);
        self::need(is_array($v) && count($v)===4 && ($v['mode']??null)==='native-recover'
            && preg_match('/\A[a-f0-9]{40}\z/',$v['expectedRecoveryCommit']??'')===1
            && ($v['planPath']??null)===$root.'/recovery-plan-'.$v['expectedRecoveryCommit'].'.json'
            && preg_match('/\A[a-f0-9]{64}\z/',$v['planSha256']??'')===1);
        $git='/usr/local/cpanel/3rdparty/bin/git'; $repo=CpanelGitDeploy::REPO;
        self::need(CpanelGitDeploy::command([$git,'rev-parse','HEAD'],$repo)===$v['expectedRecoveryCommit']
            && CpanelGitDeploy::command([$git,'status','--porcelain','--untracked-files=all'],$repo)===''
            && CpanelGitDeploy::command([$git,'rev-parse','--abbrev-ref','HEAD'],$repo)==='master');
        $bytes=self::privateBytes($v['planPath']); self::need(hash('sha256',$bytes)===$v['planSha256']);
        $p=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        self::need(($p['mode']??null)==='native-recover' && ($p['expectedRecoveryCommit']??null)===$v['expectedRecoveryCommit']
            && ($p['account']??null)==='archite4' && ($p['repository']??null)===$repo && ($p['target']??null)==='/home/archite4'
            && ($p['originalPlanPath']??null)===$root.'/operator-plan.json'
            && preg_match('/\A[a-f0-9]{64}\z/',$p['oldPlanSha256']??'')===1);
        $oldBytes=self::privateBytes($p['originalPlanPath']); self::need(hash('sha256',$oldBytes)===$p['oldPlanSha256']);
        $old=json_decode($oldBytes,true,32,JSON_THROW_ON_ERROR);
        self::need(($old['expectedCommit']??null)===$p['releaseCommit'] && ($old['artifactPath']??null)===$p['artifactPath']);
        $matches=[];
        foreach (glob($root.'/native-deploy-'.$p['releaseCommit'].'-*.json')?:[] as $path) {
            if (!preg_match('/\Anative-deploy-[a-f0-9]{40}-[a-f0-9]{16}\.json\z/',basename($path))) continue;
            $prior=json_decode(self::privateBytes($path),true,32,JSON_THROW_ON_ERROR);
            if (($prior['planSha256']??null)!==$p['oldPlanSha256']) continue;
            CpanelNativeRecovery::validateReceipt($prior,$p['oldPlanSha256']);
            self::need(($prior['deploymentId']??null)===$p['recoveredDeploymentId']);
            foreach (['backupId','archiveSha256','artifactTreeSha256','artifactManifestSha256','buildSha256'] as $key) self::need(isset($prior[$key]) && $prior[$key]===$p[$key]);
            $matches[]=$path;
        }
        self::need(count($matches)===1);
        $marker=json_decode(self::privateBytes($root.'/native-consumed-'.$p['oldPlanSha256'].'.jsonl'),true,32,JSON_THROW_ON_ERROR);
        self::need($marker===['deploymentId'=>$p['recoveredDeploymentId'],'commit'=>$p['releaseCommit']]);
        spl_autoload_register(static function(string $class): void {
            if (str_starts_with($class,'Architex\\')) { $path=CpanelGitDeploy::REPO.'/app/src/'.str_replace('\\','/',substr($class,9)).'.php'; if(is_file($path)) require_once $path; }
        },true,true);
        $outcome=CpanelNativeRecovery::run($p['originalPlanPath'],$matches[0],$p['expectedRecoveryCommit'],$p['oldPlanSha256']);
        $r=self::receipt($outcome,$p,$v['planSha256']);
        $path=$root.'/native-recover-'.$p['expectedRecoveryCommit'].'-'.bin2hex(random_bytes(8)).'.json';
        CpanelGitDeploy::journal($path,$r);
        self::need(json_decode(self::privateBytes($path),true,32,JSON_THROW_ON_ERROR)===$r);
        print json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        self::need($r['deployed']);
    }
}

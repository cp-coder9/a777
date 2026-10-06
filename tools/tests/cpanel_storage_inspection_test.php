<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cpanel_git_deploy.php';
function checkStorage(bool $ok): void { if (!$ok) throw new RuntimeException('fixture assertion failed'); }
$root = getenv('TMPDIR') . '/storage-fixture-' . bin2hex(random_bytes(8));
mkdir($root,0700); mkdir($root.'/public',0700); mkdir($root.'/public/files',0700); mkdir($root.'/archive',0700); mkdir($root.'/private',0700);
try {
    foreach ([$root.'/public/files', $root] as $unsafe) {
        $r = CpanelGitDeploy::storageInspection($unsafe,$root.'/public',$root.'/archive');
        checkStorage($r['storageExists'] && $r['storageCanonical']);
        checkStorage($r['blockingGate'] === 'backup_storage_not_private_or_archive_inside_storage');
        foreach (['migrationAuthorized','backupAuthorized','promotionAuthorized','liveDdlAuthorized'] as $key) checkStorage($r[$key] === false);
        try { CpanelGitDeploy::requireRecoveryStorage($unsafe,$root.'/public',$root.'/archive'); throw new LogicException('unsafe recovery accepted'); }
        catch (RuntimeException $e) { checkStorage($e->getMessage() === $r['blockingGate']); }
    }
    checkStorage(CpanelGitDeploy::storageInspection($root.'/private',$root.'/public',$root.'/archive')['storageClass'] === 'outside-public');
    checkStorage(CpanelGitDeploy::storageInspection($root.'/absent',$root.'/public',$root.'/archive')['storageClass'] === 'missing');
    checkStorage(CpanelGitDeploy::storageInspection($root.'/public/files',$root.'/public',$root.'/archive')['storageClass'] === 'under-public-privacy-unproven');
    checkStorage(CpanelGitDeploy::scopedOnlyGrants(['GRANT USAGE ON *.* TO fixture', 'GRANT SELECT, INSERT ON `fixture_db`.* TO fixture'],'fixture_db'));
    foreach (['GRANT ALL PRIVILEGES ON *.* TO fixture','GRANT SELECT ON `other`.* TO fixture','GRANT SELECT ON `fixture_db`.* TO fixture WITH GRANT OPTION'] as $grant) checkStorage(!CpanelGitDeploy::scopedOnlyGrants([$grant],'fixture_db'));
    $source = file_get_contents(dirname(__DIR__).'/cpanel_git_deploy.php');
    checkStorage(!str_contains($source, "self::requireValue(is_string(\$storage)"));
    checkStorage(str_contains($source, "\$report['status'] = 'inspected-not-authorized'"));
    print "PASS storage inspection and independent unsafe recovery guard; grant classifications\n";
} finally { rmdir($root.'/public/files'); rmdir($root.'/public'); rmdir($root.'/archive'); rmdir($root.'/private'); rmdir($root); }

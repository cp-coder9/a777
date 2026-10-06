<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cpanel_git_deploy.php';
$root = sys_get_temp_dir() . '/cpanel-stage-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700); mkdir($root . '/incoming', 0700);
file_put_contents($root . '/incoming/a.txt', 'real-file-fixture');
$checks = 0;
function check(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('assertion failed'); $checks++; }
function refuses(callable $call, string $gate): void { try { $call(); } catch (RuntimeException $e) { check($e->getMessage() === $gate); return; } throw new RuntimeException('expected refusal'); }
try {
    $digest = CpanelGitDeploy::digest(CpanelGitDeploy::inventory($root . '/incoming'));
    refuses(fn()=>CpanelGitDeploy::stage($root . '/incoming', $root . '/wrong', str_repeat('0', 64)), 'artifact_digest_mismatch');
    check(!file_exists($root . '/wrong'));
    CpanelGitDeploy::stage($root . '/incoming', $root . '/candidate', $digest);
    check(file_get_contents($root . '/candidate/a.txt') === 'real-file-fixture');
    check(CpanelGitDeploy::inventory($root . '/candidate') === CpanelGitDeploy::inventory($root . '/incoming'));
    refuses(fn()=>CpanelGitDeploy::stage($root . '/incoming', $root . '/candidate', $digest), 'candidate_exists');
    file_put_contents($root . '/incoming/runtime.php', '<?php');
    refuses(fn()=>CpanelGitDeploy::inventory($root . '/incoming'), 'artifact_private_content');
    unlink($root . '/incoming/runtime.php');
    file_put_contents($root . '/incoming/a.txt', 'mutated');
    refuses(fn()=>CpanelGitDeploy::stage($root . '/incoming', $root . '/mutated', $digest), 'artifact_digest_mismatch');
    CpanelGitDeploy::journal($root . '/receipt.json', ['status'=>'staged-not-promoted']);
    check(json_decode(file_get_contents($root . '/receipt.json'), true)['status'] === 'staged-not-promoted');
    refuses(fn()=>CpanelGitDeploy::journal($root . '/receipt.json', []), 'receipt_already_exists');
    CpanelGitDeploy::exclusiveGrantUser([['database'=>'archite4_dev_rel1','users'=>['archite4_app']]], 'archite4_app'); check(true);
    refuses(fn()=>CpanelGitDeploy::exclusiveGrantUser([], 'archite4_app'), 'source_inventory_not_unique');
    refuses(fn()=>CpanelGitDeploy::exclusiveGrantUser([['database'=>'archite4_dev_rel1']], 'archite4_app'), 'exclusive_database_grant_inventory_unproven');
    refuses(fn()=>CpanelGitDeploy::exclusiveGrantUser([['database'=>'archite4_dev_rel1','users'=>['archite4_app','archite4_other']]], 'archite4_app'), 'exclusive_database_grant_inventory_unproven');
    refuses(fn()=>CpanelGitDeploy::exclusiveGrantUser([['database'=>'archite4_dev_rel1','users'=>['archite4_app'=>true]]], 'archite4_app'), 'exclusive_database_grant_inventory_unproven');
    mkdir($root . '/sql', 0700); $sql = realpath($root . '/sql');
    refuses(fn()=>CpanelGitDeploy::activeSql($sql), 'active_sql_empty');
    file_put_contents($sql . '/0001_base.sql', '-- real file fixture');
    check(CpanelGitDeploy::activeSql($sql) === ['0001_base.sql'=>hash_file('sha256', $sql . '/0001_base.sql')]);
    file_put_contents($sql . '/runtime.php', '<?php throw new Exception("never execute");');
    refuses(fn()=>CpanelGitDeploy::activeSql($sql), 'active_sql_entry_invalid');
    if (PHP_OS_FAMILY !== 'Linux') refuses(fn()=>CpanelGitDeploy::run(), 'linux_php83_cli_required');
    print "$checks file-fixture assertions passed; host/SQL gates NOT exercised\n";
} finally {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}

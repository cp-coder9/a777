<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cpanel_git_deploy.php';
require dirname(__DIR__) . '/CpanelReadOnlyApi.php';
$n = 0;
function verify(bool $ok): void { global $n; if (!$ok) throw new RuntimeException('assertion_failed'); $n++; }
function reject(callable $call, string $gate): void {
    try { $call(); } catch (RuntimeException $error) { verify($error->getMessage() === $gate); return; }
    throw new RuntimeException('expected_refusal');
}
$cwd = dirname(__DIR__, 2);
verify(CpanelGitDeploy::command([PHP_BINARY, '-r', 'print "fixture";'], $cwd) === 'fixture');
verify(CpanelGitDeploy::commandDiagnostics()['exitCode'] === 0);
reject(fn()=>CpanelGitDeploy::command([PHP_BINARY, '-r', 'fwrite(STDERR,"Permission denied PRIVATE_SECRET"); exit(37);'], $cwd), 'command_failed');
verify(CpanelGitDeploy::commandDiagnostics() === ['status'=>'failed','exitCode'=>37,'stderrClass'=>'permission_denied']);
verify(!str_contains(json_encode(CpanelGitDeploy::commandDiagnostics()), 'PRIVATE_SECRET'));
verify(CpanelGitDeploy::stderrClass('PRIVATE_SECRET') === 'unclassified');
$spec = CpanelReadOnlyApi::requestSpec('Variables', 'get_user_information', 'SYNTHETIC_TOKEN_123');
verify($spec['url'] === 'https://cp73.domains.co.za:2083/execute/Variables/get_user_information?name=home');
verify($spec['headers'][0] === 'Authorization: cpanel archite4:SYNTHETIC_TOKEN_123');
verify(!str_contains($spec['url'], 'SYNTHETIC_TOKEN_123'));
reject(fn()=>CpanelReadOnlyApi::requestSpec('Mysql','create_database','fixture'), 'api_readonly_function_required');
foreach (['list_databases','list_users'] as $function) verify(CpanelReadOnlyApi::requestSpec('Mysql',$function,'fixture')['url'] === CpanelReadOnlyApi::ORIGIN . '/execute/Mysql/' . $function);
verify(CpanelReadOnlyApi::decode('{"status":1,"errors":null,"data":{"home":"/home/archite4"}}',200) === ['home'=>'/home/archite4']);
verify(CpanelReadOnlyApi::decode('{"status":1,"errors":null,"data":[{"database":"archite4_dev_rel1","users":["archite4_app"]}]}',200)[0]['database'] === 'archite4_dev_rel1');
reject(fn()=>CpanelReadOnlyApi::decode('PRIVATE_SECRET',302), 'api_http_failed');
reject(fn()=>CpanelReadOnlyApi::decode('PRIVATE_SECRET',200), 'api_response_invalid');
reject(fn()=>CpanelReadOnlyApi::decode('{"status":0,"errors":["PRIVATE_SECRET"],"data":[]}',200),'api_uapi_failed');
reject(fn()=>CpanelReadOnlyApi::decode(str_repeat('x',1048577),200),'api_response_too_large');
$stat = ['mode'=>0100600,'uid'=>1001,'nlink'=>1,'dev'=>1,'ino'=>2];
CpanelReadOnlyApi::validateTokenStat($stat,$stat,1001); verify(true);
foreach (['mode'=>0100644,'uid'=>1002,'nlink'=>2] as $key=>$value) {
    $bad=$stat; $bad[$key]=$value;
    reject(fn()=>CpanelReadOnlyApi::validateTokenStat($bad,$bad,1001),'api_token_not_private');
}
$bad=$stat; $bad['mode']=0120600;
reject(fn()=>CpanelReadOnlyApi::validateTokenStat($bad,$bad,1001),'api_token_not_private');
$bad=$stat; $bad['ino']=3;
reject(fn()=>CpanelReadOnlyApi::validateTokenStat($stat,$bad,1001),'api_token_not_private');
// Cross-class real CLI failure; no token is read unless explicitly enabled.
$evidence=[];
if (PHP_OS_FAMILY !== 'Linux') {
    try { CpanelGitDeploy::inspectInventory('list_databases',false,$evidence); throw new RuntimeException('expected_refusal'); }
    catch (RuntimeException $error) { verify(in_array($error->getMessage(), ['command_failed','command_unavailable'], true)); }
    verify($evidence['mysqlDatabaseTransport'] === 'native-cli');
    reject(function() use (&$evidence) { CpanelGitDeploy::inspectInventory('list_databases',true,$evidence); },'api_linux_identity_required');
    verify($evidence['mysqlDatabaseTransport'] === 'authenticated-https-fallback-attempted');
}
$source=file_get_contents(dirname(__DIR__) . '/CpanelReadOnlyApi.php');
foreach (['CURLOPT_FOLLOWLOCATION=>false','CURLOPT_SSL_VERIFYPEER=>true','CURLOPT_SSL_VERIFYHOST=>2','CURLOPT_PROXY=>\'\'','realpath(self::TOKEN) === self::TOKEN',"['home'] ?? null) === '/home/archite4'"] as $control) verify(str_contains($source,$control));
$deploy=file_get_contents(dirname(__DIR__) . '/cpanel_git_deploy.php');
verify(str_contains($deploy,"'planSha256'=>hash_file('sha256', \$planPath)"));
verify(str_contains($deploy,"\$requested === '--preflight' && \$plan['mode'] === 'preflight'"));
verify(str_contains($deploy,"native-cli-then-private-token-https"));
print "$n assertions passed; synthetic protocol/stat fixtures and real local subprocesses; no hosting or production proof\n";

<?php
declare(strict_types=1);
// Regression inventory only: never included by the runtime or release bootstrap.
$bootstrap=$argv[1]??null;$destination=$argv[2]??null;
if(!is_string($bootstrap)||!is_string($destination)){fwrite(STDERR,"Usage: php role-workflow-inventory.php TEST_BOOTSTRAP OUTPUT_JSON\n");exit(2);}
require $bootstrap;
use Architex\Platform\Toolbox\ProductionToolRegistry;
use Architex\Platform\Authorization\PermissionService;
use Architex\Platform\Wingman\WingmanRoleTutorials;
$root=dirname(__DIR__,2);
$roles=WingmanRoleTutorials::roles();
$permissions=(new ReflectionClass(PermissionService::class))->getConstant('ROLE_PERMISSIONS');
$placement=json_decode(file_get_contents($root.'/app/public/assets/route-placement.json'),true,flags:JSON_THROW_ON_ERROR);
$inventory=[];$coverage=[];
foreach($roles as $role){
    $profile=WingmanRoleTutorials::profile($role);
    $entry=['profileRole'=>$role,'label'=>$profile['label'],'workflowStages'=>$profile['stages'],'responsibility'=>$profile['evidence'],'authorityBoundary'=>$profile['boundary'],'scopes'=>[]];
    foreach($permissions as $membership=>$scopes){
        $tools=ProductionToolRegistry::workspaceCatalogue($membership,$role);
        $stageTools=[];
        foreach($tools as $tool){
            $stages=$tool['stage']==='All stages'?array_column($placement['stages'],'id'):array_map(static fn(string $stage)=>strtolower(str_replace(' ','-',trim($stage))),explode('/',$tool['stage']));
            foreach($stages as $stage)$stageTools[$stage][]=['toolId'=>$tool['id'],'recommended'=>$tool['recommended'],'access'=>$tool['access'],'nativeActions'=>$tool['nativeActions'],'boundary'=>$tool['nativeBoundary']];
        }
        $entry['scopes'][$membership]=['permissions'=>$scopes,'stageTools'=>$stageTools,'tools'=>$tools];
        $coverage[$membership]=['tools'=>count($tools),'readable'=>count(array_filter($tools,fn($t)=>$t['access']['read'])),'membershipWriteScopeEligible'=>count(array_filter($tools,fn($t)=>$t['access']['write'])),'nativeActions'=>array_sum(array_map(fn($t)=>count(array_filter($t['nativeActions'],fn($a)=>$a['allowed'])),$tools))];
    }
    $inventory[]=$entry;
}
$tools=ProductionToolRegistry::workspaceCatalogue('owner');
$legacy=array_values(array_filter($tools,fn($t)=>$t['status']==='limited'));
$adapters=[];
foreach(glob($root.'/app/public/assets/v1-clone/js/adapters/*.js') as $file){
    $source=file_get_contents($file);preg_match_all('/(?:async\s+)?function\s+([a-zA-Z0-9_]+)\s*\(/',$source,$methods);preg_match_all("~['\"](/(?:api/v1/)?[a-z][a-z0-9_/?{}=.+-]*)['\"]~i",$source,$paths);
    $adapters[]=['file'=>str_replace('\\','/',substr($file,strlen($root)+1)),'functions'=>array_values(array_unique($methods[1])),'literalRouteFragments'=>array_values(array_unique($paths[1]))];
}
$report=['sourceRoot'=>$root,'profileRoleCount'=>count($roles),'membershipRoleCount'=>count($permissions),'workspaceToolCount'=>count($tools),'lifecycleStages'=>$placement['stages'],'legacyLimitedToolCount'=>count($legacy),'legacyLimitedTools'=>array_column($legacy,'id'),'nativeEntryActionCount'=>array_sum(array_map(fn($t)=>count($t['nativeActions']),$tools)),'roleScopeToolRows'=>count($roles)*count($permissions)*count($tools),'coverage'=>$coverage,'inventory'=>$inventory,'adapterContracts'=>$adapters,'proofBoundary'=>'Code-derived discovery and contracts are not live provider or professional evidence. External status was not promoted.'];
file_put_contents($destination,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo json_encode(array_intersect_key($report,array_flip(['profileRoleCount','membershipRoleCount','workspaceToolCount','legacyLimitedToolCount','nativeEntryActionCount','roleScopeToolRows','coverage'])),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";

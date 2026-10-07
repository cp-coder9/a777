<?php
declare(strict_types=1);
require dirname(__DIR__).'/CpanelNativeOperator.php';
use Architex\Deployment\CpanelNativeOperator;
$rows=[['TABLE_SCHEMA'=>'archite4\\_dev\\_rel1','PRIVILEGE_TYPE'=>'CREATE'],['TABLE_SCHEMA'=>'archite4\\_dev\\_rel1','PRIVILEGE_TYPE'=>'ALTER'],['TABLE_SCHEMA'=>'archite4_%','PRIVILEGE_TYPE'=>'DROP'],['TABLE_SCHEMA'=>'other_database','PRIVILEGE_TYPE'=>'DELETE']];
if(CpanelNativeOperator::schemaCapabilities($rows,'archite4_dev_rel1')!==['CREATE','ALTER'])throw new RuntimeException('Escaped schema metadata regression');
$rows=[['TABLE_SCHEMA'=>'archite4_dev_rel1','PRIVILEGE_TYPE'=>'SELECT']];
if(CpanelNativeOperator::schemaCapabilities($rows,'archite4_dev_rel1')!==['SELECT'])throw new RuntimeException('Plain schema metadata regression');
echo "PASS escaped and plain exact-schema metadata; wildcard and foreign schemas excluded\n";

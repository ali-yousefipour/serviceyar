<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$required=[
 'backend/public/index.php','backend/src/Auth/AuthService.php','backend/src/Auth/Authorization.php',
 'backend/src/Domain/DomainService.php','backend/src/Reports/ExportService.php','frontend/index.html','frontend/assets/app.js'
];
foreach($required as $p){if(!is_file($root.'/'.$p))throw new RuntimeException("Missing required file: $p");}
$files=glob($root.'/database/migrations/*.sql')?:[];$names=array_map('basename',$files);$sorted=$names;$sorted2=$names;sort($sorted,SORT_STRING);
if($sorted!==$sorted2)throw new RuntimeException('Migration order is not deterministic.');
foreach($files as $file){$sql=file_get_contents($file);if($sql===false||trim($sql)==='')throw new RuntimeException('Empty migration: '.basename($file));}
$index=file_get_contents($root.'/backend/public/index.php');$checks=['/api/v1/auth/login','/api/v1/dashboard/summary','/api/v1/reports/export','/api/v1/monitoring/activity','/api/v1/ai/history'];
foreach($checks as $route)if(strpos($index,$route)===false)throw new RuntimeException("Missing route: $route");
echo "Static smoke tests passed.\n";

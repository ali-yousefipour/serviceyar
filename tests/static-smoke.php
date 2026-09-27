<?php
declare(strict_types=1);

$root=dirname(__DIR__);

$required=[
 'backend/public/index.php',
 'backend/src/Auth/AuthService.php',
 'backend/src/Auth/Authorization.php',
 'backend/src/Domain/DomainService.php',
 'backend/src/Reports/ExportService.php',
 'frontend/index.html',
 'frontend/assets/app.js',
 'database/migrations/0007_auth_sessions.sql'
];

foreach($required as $p){
    if(!is_file($root.'/'.$p)){
        throw new RuntimeException("Missing required file: $p");
    }
}

$files=glob($root.'/database/migrations/*.sql')?:[];
$names=array_map('basename',$files);
$sorted=$names;
sort($sorted,SORT_STRING);

if($names!==$sorted){
    throw new RuntimeException('Migration order is not deterministic.');
}

$prefixes=[];
foreach($names as $name){
    if(!preg_match('/^(\d{4})_/', $name, $m)){
        throw new RuntimeException("Migration filename must start with a four-digit sequence: $name");
    }
    if(isset($prefixes[$m[1]])){
        throw new RuntimeException("Duplicate migration sequence {$m[1]}: {$prefixes[$m[1]]} and $name");
    }
    $prefixes[$m[1]]=$name;
}

$expected=[
 '0001_create_migrations_table.sql',
 '0002_create_core_tables.sql',
 '0003_agency_settings.sql',
 '0004_school_transport_domain.sql',
 '0005_cross_module_tables.sql',
 '0006_security_hardening.sql',
 '0007_auth_sessions.sql'
];

foreach($expected as $name){
    if(!isset($prefixes[substr($name,0,4)]) || $prefixes[substr($name,0,4)]!==$name){
        throw new RuntimeException("Unexpected migration sequence: expected $name");
    }
}

if(isset($prefixes['0020']) || is_file($root.'/database/migrations/002_auth_sessions.sql')){
    throw new RuntimeException('Legacy unordered auth migration is still present.');
}

foreach($files as $file){
    $sql=file_get_contents($file);
    if($sql===false || trim($sql)===''){
        throw new RuntimeException('Empty migration: '.basename($file));
    }
}

$index=file_get_contents($root.'/backend/public/index.php');
if($index===false){
    throw new RuntimeException('Cannot read backend entry point.');
}

$checks=[
 '/api/v1/auth/login',
 '/api/v1/dashboard/summary',
 '/api/v1/reports/export',
 '/api/v1/monitoring/activity',
 '/api/v1/ai/history'
];

foreach($checks as $route){
    if(strpos($index,$route)===false){
        throw new RuntimeException("Missing route: $route");
    }
}

$auth=file_get_contents($root.'/database/migrations/0007_auth_sessions.sql');
if($auth===false || strpos($auth,'CREATE TABLE IF NOT EXISTS user_sessions')===false){
    throw new RuntimeException('Ordered auth migration does not create user_sessions.');
}

echo "Static smoke tests passed.\n";

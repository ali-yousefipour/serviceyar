<?php
declare(strict_types=1);

/**
 * Targeted repair for legacy production databases with incomplete migration history.
 *
 * php backend/bin/repair-company-user-permissions.php          # dry run
 * php backend/bin/repair-company-user-permissions.php --apply  # explicit write
 *
 * No unrelated migrations, tables or users are modified.
 */
require dirname(__DIR__).'/src/bootstrap.php';

use ServiceYar\Database\Connection;

$pdo=Connection::get();
$apply=in_array('--apply',$argv,true);
$names=['agency_admin','school_company_manager','super_admin'];
$permissions=['users.view','users.manage'];
$inRoles=implode(',',array_fill(0,count($names),'?'));
$inPermissions=implode(',',array_fill(0,count($permissions),'?'));
$roles=$pdo->prepare("SELECT name FROM roles WHERE name IN ($inRoles)");
$roles->execute($names);
$knownRoles=$roles->fetchAll(PDO::FETCH_COLUMN);
$missingRoles=array_values(array_diff($names,$knownRoles));
if($missingRoles){
    fwrite(STDERR,"Required roles missing: ".implode(',',$missingRoles)."\n");
    exit(2);
}
$existing=$pdo->prepare("SELECT r.name AS role_name,p.name AS permission_name
  FROM role_permissions rp JOIN roles r ON r.id=rp.role_id
  JOIN permissions p ON p.id=rp.permission_id
  WHERE r.name IN ($inRoles) AND p.name IN ($inPermissions)");
$existing->execute([...$names,...$permissions]);
$have=[];
foreach($existing as $row)$have[$row['role_name']][$row['permission_name']]=true;
$missing=[];
foreach($names as $name)foreach($permissions as $permission)
    if(!isset($have[$name][$permission]))$missing[]="$name:$permission";
if(!$apply){
    echo json_encode(['mode'=>'read-only','missing_grants'=>$missing,'apply_command'=>'php backend/bin/repair-company-user-permissions.php --apply'],
        JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    exit(0);
}
$pdo->beginTransaction();
try{
    $pdo->exec("INSERT IGNORE INTO permissions(name,title) VALUES
       ('users.view','مشاهده کاربران شرکت'),
       ('users.manage','مدیریت کاربران شرکت')");
    $pdo->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id)
       SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
       WHERE r.name IN ('agency_admin','school_company_manager','super_admin')
       AND p.name IN ('users.view','users.manage')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
       id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
       migration VARCHAR(255) NOT NULL UNIQUE,
       applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $q=$pdo->prepare("INSERT IGNORE INTO migrations(migration) VALUES(?)");
    $q->execute(['0114_school_company_manager_user_permissions.sql']);
    $pdo->commit();
    echo "Applied idempotent company manager grant repair and registered migration 0114.\n";
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
}

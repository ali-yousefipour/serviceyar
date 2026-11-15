<?php
declare(strict_types=1);

/**
 * Read-only production schema drift report.
 *
 * Usage: php backend/bin/schema-audit.php
 * Does not run migrations, modify data, expose users or print secrets.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

use ServiceYar\Database\Connection;

$pdo=Connection::get();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$tableNames=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")
    ->fetchAll(PDO::FETCH_COLUMN);
$tables=array_fill_keys($tableNames,true);
$cols=[];
$q=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()");
foreach($q as $row)$cols[$row['TABLE_NAME']][$row['COLUMN_NAME']]=true;

$directory=dirname(__DIR__,2).'/database/migrations';
$files=glob($directory.'/*.sql')?:[];
sort($files,SORT_STRING);
$known=array_map('basename',$files);
$applied=isset($tables['migrations'])?$pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN):[];
$pending=array_values(array_diff($known,$applied));
$unknown=array_values(array_diff($applied,$known));

$required=[
    'users'=>['id','username','is_active','deleted_at','mobile','photo_version'],
    'roles'=>['id','name'],
    'permissions'=>['id','name'],
    'role_permissions'=>['role_id','permission_id'],
    'user_roles'=>['user_id','role_id'],
    'agency_users'=>['agency_id','user_id'],
    'agencies'=>['id','is_active'],
    'user_sessions'=>['user_id','token_hash','revoked_at'],
    'admin_impersonation_sessions'=>['admin_user_id','target_user_id','agency_id','admin_session_hash','target_session_hash','ended_at'],
    'platform_settings'=>['setting_key','setting_value'],
    'bale_auth_challenges'=>['user_id','purpose','code_hash'],
    'sms_login_challenges'=>['user_id','purpose','code_hash'],
];
$schemaIssues=[];
foreach($required as $table=>$fields){
    if(!isset($tables[$table])){$schemaIssues[$table]=['missing_table'];continue;}
    $missing=array_values(array_filter($fields,fn($field)=>!isset($cols[$table][$field])));
    if($missing)$schemaIssues[$table]=$missing;
}

$roles=[];
if(isset($tables['roles'],$tables['user_roles'],$tables['users'])){
    $roleRows=$pdo->query("SELECT r.name, COUNT(DISTINCT u.id) AS account_count
       FROM roles r LEFT JOIN user_roles ur ON ur.role_id=r.id
       LEFT JOIN users u ON u.id=ur.user_id AND u.is_active=1
       GROUP BY r.name ORDER BY r.name")->fetchAll(PDO::FETCH_ASSOC);
    foreach($roleRows as $row)$roles[$row['name']]=(int)$row['account_count'];
}
$grants=[];
if(isset($tables['permissions'],$tables['role_permissions'],$tables['roles'])){
    $stmt=$pdo->query("SELECT r.name AS role_name,p.name AS permission_name
       FROM role_permissions rp JOIN roles r ON r.id=rp.role_id
       JOIN permissions p ON p.id=rp.permission_id
       WHERE r.name IN ('super_admin','agency_admin','school_company_manager')
         AND p.name IN ('users.view','users.manage')
       ORDER BY r.name,p.name");
    foreach($stmt as $row)$grants[$row['role_name']][]=$row['permission_name'];
}
$missingGrants=[];
foreach(['super_admin','agency_admin','school_company_manager'] as $name){
    foreach(['users.view','users.manage'] as $permission){
        if(!in_array($permission,$grants[$name]??[],true))$missingGrants[]=$name.':'.$permission;
    }
}
$managerWithoutAgency=0;
if(isset($tables['users'],$tables['roles'],$tables['user_roles'],$tables['agency_users'])){
    $managerWithoutAgency=(int)$pdo->query("SELECT COUNT(DISTINCT u.id)
      FROM users u JOIN user_roles ur ON ur.user_id=u.id
      JOIN roles r ON r.id=ur.role_id
      WHERE r.name IN ('agency_admin','school_company_manager')
        AND u.is_active=1 AND NOT EXISTS
        (SELECT 1 FROM agency_users au WHERE au.user_id=u.id)")->fetchColumn();
}
$backupCandidates=array_values(array_filter($tableNames,static function($name){
    return (bool)preg_match('/(?:^arta_before_|^arta_duplicate_|^arta_school_backup_|^sy_company_username_backup_|^serviceyar_tariff_class_repair_backup_)/',$name);
}));
sort($backupCandidates,SORT_STRING);
$summary=[
 'database'=>(string)$pdo->query('SELECT DATABASE()')->fetchColumn(),
 'migration_files'=>count($known),
 'recorded_migrations'=>count($applied),
 'not_recorded'=>count($pending),
 'extra_migration_records'=>count($unknown),
 'table_count'=>count($tableNames),
 'schema_issue_count'=>count($schemaIssues),
 'missing_manager_permissions'=>count($missingGrants),
 'active_company_managers_without_membership'=>$managerWithoutAgency,
 'candidate_archive_tables'=>count($backupCandidates),
];
$report=[
 'summary'=>$summary,
 'migration_history'=>[
  'not_recorded'=>$pending,
  'recorded_but_not_in_repository'=>$unknown,
  'note'=>'A missing history row does NOT prove the SQL DDL is absent. Never blindly replay these migrations.',
 ],
 'required_schema_issues'=>$schemaIssues,
 'active_role_counts'=>$roles,
 'tenant_user_management_grant_gaps'=>$missingGrants,
 'backup_table_candidates'=>$backupCandidates,
 'warning'=>'Backup candidates are NOT proven unused. This audit is read-only and does not authorize DROP TABLE.',
];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if($schemaIssues||$missingGrants)exit(2);

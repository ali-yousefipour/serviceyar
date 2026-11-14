<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Diagnose and repair only Sepand's schema; never edits transport data. */
final class SepandSchemaMaintenance {
 private const COLS=[
  'agencies'=>['sepand_national_id'=>'VARCHAR(20) NULL'],
  'drivers'=>[
   'gender'=>'VARCHAR(20) NULL','sepand_request_id'=>'VARCHAR(32) NULL',
   'sepand_status'=>"VARCHAR(20) NOT NULL DEFAULT 'unknown'",
   'sepand_status_updated_at'=>'DATETIME NULL'
  ],
  'vehicles'=>[
   'vin'=>'VARCHAR(40) NULL','plate_part_1'=>'VARCHAR(2) NULL',
   'plate_letter'=>'VARCHAR(8) NULL','plate_part_3'=>'VARCHAR(3) NULL',
   'plate_iran'=>'VARCHAR(2) NULL','vehicle_type'=>'VARCHAR(80) NULL',
   'vehicle_system'=>'VARCHAR(100) NULL','vehicle_trim'=>'VARCHAR(120) NULL',
   'make_year'=>'SMALLINT UNSIGNED NULL','fuel_type'=>'VARCHAR(80) NULL',
   'color'=>'VARCHAR(70) NULL','engine_number'=>'VARCHAR(100) NULL',
   'chassis_number'=>'VARCHAR(100) NULL','insurance_number'=>'VARCHAR(120) NULL',
   'sepand_status'=>"VARCHAR(20) NOT NULL DEFAULT 'unknown'",
   'sepand_status_updated_at'=>'DATETIME NULL'
  ]
 ];
 private const INDEXES=[
  'agencies'=>['uq_agencies_sepand_national_id'=>['sepand_national_id',true]],
  'drivers'=>['idx_drivers_sepand_request'=>['agency_id,sepand_request_id',false]],
  'vehicles'=>['idx_vehicles_sepand_vin'=>['agency_id,vin',false]]
 ];
 private static function tables(PDO $pdo): array {
  return array_fill_keys(array_map('strtolower',$pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_COLUMN)),true);
 }
 private static function columns(PDO $pdo): array {
  $q=$pdo->query("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name IN ('agencies','drivers','vehicles')");
  $out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$out[strtolower($r['table_name'])][strtolower($r['column_name'])]=true;
  return $out;
 }
 private static function indexes(PDO $pdo): array {
  $q=$pdo->query("SELECT table_name,index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name IN ('agencies','drivers','vehicles')");
  $out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$out[strtolower($r['table_name'])][strtolower($r['index_name'])]=true;
  return $out;
 }
 public static function inspect(PDO $pdo): array {
  $tables=self::tables($pdo);$columns=self::columns($pdo);$indexes=self::indexes($pdo);
  $missing=[];$missingIndexes=[];
  foreach(self::COLS as $table=>$fields){
   if(!isset($tables[$table]))throw new AuthException(503,'جدول اصلی '.$table.' در دیتابیس وجود ندارد.','SEPAND_BASE_SCHEMA_MISSING');
   foreach($fields as $name=>$definition)if(!isset($columns[$table][$name]))$missing[]=$table.'.'.$name;
  }
  foreach(self::INDEXES as $table=>$fields)foreach($fields as $name=>$meta)
   if(!isset($indexes[$table][$name]))$missingIndexes[]=$table.'.'.$name;
  return [
   'ready'=>!$missing&&!$missingIndexes&&isset($tables['sepand_import_runs']),
   'missingColumns'=>$missing,'missingIndexes'=>$missingIndexes,
   'missingAuditTable'=>!isset($tables['sepand_import_runs']),
   'migration'=>'0103_sepand_vehicle_driver_registry.sql'
  ];
 }
 public static function requireReady(PDO $pdo): void {
  if(!self::inspect($pdo)['ready'])throw new AuthException(409,
   'ساختار دیتابیس سپند کامل نیست؛ پس از پشتیبان‌گیری، از تنظیمات ادمین کل گزینه آماده‌سازی دیتابیس سپند را اجرا کنید.',
   'SEPAND_SCHEMA_MIGRATION_REQUIRED');
 }
 public static function status(int $uid): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'دسترسی فقط برای ادمین کل مجاز است.','SUPER_ADMIN_REQUIRED');
  return self::inspect(Connection::get());
 }
 public static function repair(int $uid,string $confirmation): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'دسترسی فقط برای ادمین کل مجاز است.','SUPER_ADMIN_REQUIRED');
  if($confirmation!=='REPAIR_SEPAND_SCHEMA')throw new AuthException(422,'تأیید آماده‌سازی دیتابیس لازم است.','CONFIRMATION_REQUIRED');
  $pdo=Connection::get();$before=self::inspect($pdo);
  if($before['ready'])return ['alreadyReady'=>true,'before'=>$before,'after'=>$before];
  if($pdo->inTransaction())throw new AuthException(409,'تراکنش فعال مانع تغییر ساختار دیتابیس است.','SCHEMA_TRANSACTION_ACTIVE');
  $existing=self::columns($pdo);
  foreach(self::COLS as $table=>$cols){
   $add=[];
   foreach($cols as $name=>$definition)if(!isset($existing[$table][$name]))$add[]='ADD COLUMN '.$name.' '.$definition;
   if($add)$pdo->exec('ALTER TABLE '.$table.' '.implode(', ',$add));
  }
  $indexes=self::indexes($pdo);
  foreach(self::INDEXES as $table=>$definitions)foreach($definitions as $name=>[$columns,$unique]){
   if(!isset($indexes[$table][$name]))$pdo->exec('CREATE '.($unique?'UNIQUE ':'').'INDEX '.$name.' ON '.$table.' ('.$columns.')');
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS sepand_import_runs (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   file_hash CHAR(64) NOT NULL,performed_by BIGINT UNSIGNED NOT NULL,
   source_driver_rows INT UNSIGNED NOT NULL,source_vehicle_rows INT UNSIGNED NOT NULL,
   driver_created INT UNSIGNED NOT NULL DEFAULT 0,driver_updated INT UNSIGNED NOT NULL DEFAULT 0,
   vehicle_created INT UNSIGNED NOT NULL DEFAULT 0,vehicle_updated INT UNSIGNED NOT NULL DEFAULT 0,
   skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,report_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   UNIQUE KEY uq_sepand_import_hash(file_hash)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $after=self::inspect($pdo);
  if(!$after['ready'])throw new AuthException(503,'ساختار دیتابیس سپند به‌طور کامل آماده نشده است.','SEPAND_SCHEMA_INCOMPLETE');
  $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,migration VARCHAR(255) NOT NULL UNIQUE,
   applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->prepare('INSERT IGNORE INTO migrations(migration) VALUES(?)')->execute([$after['migration']]);
  return ['before'=>$before,'after'=>$after,'updated'=>true];
 }
}

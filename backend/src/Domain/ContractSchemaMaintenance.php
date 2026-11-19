<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

final class ContractSchemaMaintenance {
 private const MIGRATIONS=['0161_student_contract_invoices.sql','0162_contract_number_and_revisions.sql'];
 public static function inspect(PDO $pdo): array {
  $required=['student_contract_invoices'=>['agency_id','student_id','academic_year','contract_id','invoice_id'],
   'contract_number_sequence'=>['id','next_number'],'contract_revisions'=>['agency_id','contract_id','edited_by','previous_json','created_at']];
  $tables=array_fill_keys($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),true);
  $missing=[];foreach($required as $table=>$columns){
   if(!isset($tables[$table])){$missing[]=$table;continue;}
   $existing=array_column($pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_ASSOC),'Field');
   foreach($columns as $column)if(!in_array($column,$existing,true))$missing[]=$table.'.'.$column;
  }
  return ['ready'=>!$missing,'missing'=>$missing,'migrations'=>self::MIGRATIONS];
 }
 public static function requireReady(PDO $pdo): void {
  if(!self::inspect($pdo)['ready'])throw new AuthException(503,'ساختار دیتابیس قراردادها کامل نیست. مدیر کل باید از داشبورد مدیریت کل، گزینه آماده‌سازی دیتابیس قراردادها را اجرا کند.','CONTRACT_SCHEMA_MIGRATION_REQUIRED');
 }
 public static function status(int $uid): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط مدیر کل مجاز است.','SUPER_ADMIN_REQUIRED');
  return self::inspect(Connection::get());
 }
 public static function repair(int $uid,string $confirm): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط مدیر کل مجاز است.','SUPER_ADMIN_REQUIRED');
  if($confirm!=='REPAIR_CONTRACT_SCHEMA')throw new AuthException(422,'تأیید عملیات لازم است.','CONFIRMATION_REQUIRED');
  $pdo=Connection::get();
  if($pdo->inTransaction())throw new AuthException(409,'تراکنش فعال است.','SCHEMA_TRANSACTION_ACTIVE');
  $before=self::inspect($pdo);
  if($before['ready'])return $before;
  try{
   foreach(self::MIGRATIONS as $name){
    $sql=file_get_contents(dirname(__DIR__,3).'/database/migrations/'.$name);
    if($sql===false)throw new \RuntimeException('Missing migration '.$name);
    // These two migrations contain only fixed, additive CREATE IF NOT EXISTS and INSERT IGNORE statements.
    foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$pdo->exec($statement);
   }
   $after=self::inspect($pdo);
   if(!$after['ready'])throw new AuthException(503,'ساختار موجود ناقص است و نیازمند بررسی دستی مدیر دیتابیس است.','CONTRACT_SCHEMA_INCOMPLETE');
   foreach(self::MIGRATIONS as $name)$pdo->prepare('INSERT IGNORE INTO migrations(migration) VALUES(?)')->execute([$name]);
   return $after;
  }catch(\PDOException $e){
   error_log('[ServiceYar] Contract schema repair: '.$e->getMessage());
   throw new AuthException(503,'آماده‌سازی ساختار قراردادها انجام نشد؛ مجوز ساخت جدول و ساختار دیتابیس را بررسی کنید.','CONTRACT_SCHEMA_REPAIR_FAILED');
  }
 }
}

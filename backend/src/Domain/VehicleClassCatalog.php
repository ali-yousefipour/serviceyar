<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Explicitly-approved and editable vehicle model/class dictionary. */
final class VehicleClassCatalog {
 private static function normalize(string $text): string {
  $text=mb_strtolower(strtr($text,[
   'ي'=>'ی','ك'=>'ک','آ'=>'ا','أ'=>'ا','إ'=>'ا','ؤ'=>'و','ئ'=>'ی',
   '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
   '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
   '‌'=>' ','ـ'=>' '
  ]),'UTF-8');
  return trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{N}]+/u',' ',$text)??$text)??$text);
 }
 private static function requireAdmin(int $uid): void {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط مدیر کل سامانه مجاز است.','SUPER_ADMIN_REQUIRED');
 }
 private static function entries(PDO $pdo): array {
  return $pdo->query('SELECT id,class_number,family_name,alias,normalized_alias FROM vehicle_tariff_class_models ORDER BY class_number,family_name,id')->fetchAll(PDO::FETCH_ASSOC);
 }
 private static function ready(PDO $pdo): bool {
  $q=$pdo->query("SHOW TABLES LIKE 'vehicle_tariff_class_models'");
  return (bool)$q->fetchColumn();
 }
 public static function list(int $uid): array {
  self::requireAdmin($uid);
  $pdo=Connection::get();
  if(!self::ready($pdo))return ['ready'=>false,'models'=>[],'migration'=>'0104_vehicle_tariff_class_models.sql'];
  return ['ready'=>true,'models'=>self::entries($pdo)];
 }
 public static function prepare(int $uid,string $confirmation): array {
  self::requireAdmin($uid);
  if($confirmation!=='INSTALL_VEHICLE_CLASS_CATALOG')
   throw new AuthException(422,'تأیید آماده‌سازی فهرست کلاس‌ها لازم است.','CONFIRMATION_REQUIRED');
  $pdo=Connection::get();
  if(self::ready($pdo))return self::list($uid);
  $file=dirname(__DIR__,3).'/database/migrations/0104_vehicle_tariff_class_models.sql';
  $sql=@file_get_contents($file);
  if(!$sql)throw new AuthException(503,'فایل ایجاد جدول کلاس‌های خودرو روی سرور پیدا نشد.','VEHICLE_CATALOG_MIGRATION_MISSING');
  // CREATE TABLE is implicit-commit DDL, therefore only run with explicit approval.
  $pdo->exec($sql);
  $pdo->prepare('INSERT IGNORE INTO migrations(migration) VALUES(?)')->execute([basename($file)]);
  return self::list($uid);
 }
 private static function ensureReady(PDO $pdo): void {
  if(!self::ready($pdo))throw new AuthException(409,
   'فهرست کلاس‌ها هنوز در دیتابیس آماده نشده است. از تنظیمات ادمین کل گزینه آماده‌سازی را اجرا کنید.',
   'VEHICLE_CATALOG_NOT_READY');
 }
 public static function add(int $uid,array $body): array {
  self::requireAdmin($uid);
  $class=(int)($body['vehicleClass']??0);
  $family=trim((string)($body['familyName']??''));
  $alias=trim((string)($body['alias']??''));
  $normalized=self::normalize($alias);
  if(!in_array($class,[1,2,3],true)||mb_strlen($family)>100||mb_strlen($normalized)<2||
   mb_strlen($normalized)>120||mb_strlen($alias)>120||$family==='')
    throw new AuthException(422,'کلاس، خانواده یا نام خودرو نامعتبر است.','INVALID_VEHICLE_MODEL');
  $pdo=Connection::get();self::ensureReady($pdo);
  $test=$pdo->prepare('SELECT class_number FROM vehicle_tariff_class_models WHERE normalized_alias=?');
  $test->execute([$normalized]);$found=$test->fetchColumn();
  if($found!==false)throw new AuthException(409,'این نام قبلاً در کلاس '.(int)$found.' ثبت شده است.','VEHICLE_ALIAS_EXISTS');
  $pdo->prepare('INSERT INTO vehicle_tariff_class_models(class_number,family_name,alias,normalized_alias,created_by) VALUES(?,?,?,?,?)')
   ->execute([$class,$family,$alias,$normalized,$uid]);
  return ['id'=>(int)$pdo->lastInsertId()];
 }
 public static function remove(int $uid,int $id): array {
  self::requireAdmin($uid);
  self::ensureReady(Connection::get());
  $stmt=Connection::get()->prepare('DELETE FROM vehicle_tariff_class_models WHERE id=?');
  $stmt->execute([$id]);return ['deleted'=>$stmt->rowCount()===1];
 }
 private static function matches(array $vehicle,array $models): array {
  $fields=['vehicle_type','vehicle_system','vehicle_trim','model'];
  $texts=[];
  foreach($fields as $field){
   $t=self::normalize((string)($vehicle[$field]??''));
   if($t!=='')$texts[]=$t;
  }
  $hits=[];
  foreach($models as $model){
   $alias=self::normalize((string)$model['normalized_alias']);
   if($alias==='')continue;
   // Exact word/phrase boundaries: "206" must not match "1206", and
   // "پراید" and "تیبا" together must remain an ambiguity, not a guess.
   $regex='/(?<![\p{L}\p{N}])'.preg_quote($alias,'/').'(?![\p{L}\p{N}])/u';
   foreach($texts as $t)if(preg_match($regex,$t)){
    // "پارس خودرو" is a manufacturer, NOT evidence of Peugeot Pars.
    if($alias==='پارس' && preg_match('/^پارس خودرو$/u',$t))continue;
    $hits[(int)$model['class_number']][(string)$model['family_name']]=true;
    break;
   }
  }
  return $hits;
 }
 private static function scan(PDO $pdo): array {
  $models=self::entries($pdo);
  $rows=$pdo->query("SELECT v.id,v.agency_id,v.plate,v.vin,v.model,v.vehicle_type,v.vehicle_system,v.vehicle_trim,
    v.tariff_vehicle_class FROM vehicles v WHERE v.tariff_vehicle_class IS NULL
    ORDER BY v.agency_id,v.id")->fetchAll(PDO::FETCH_ASSOC);
  $classified=[];$unresolved=[];
  foreach($rows as $v){
   $hits=self::matches($v,$models);
   $classes=array_keys($hits);
   $label=['vehicleId'=>(int)$v['id'],'agencyId'=>(int)$v['agency_id'],
    'plate'=>(string)($v['plate']??''),'vin'=>(string)($v['vin']??''),
    'model'=>(string)($v['model']??''),'system'=>(string)($v['vehicle_system']??''),
    'trim'=>(string)($v['vehicle_trim']??''),'type'=>(string)($v['vehicle_type']??'')];
   if(count($classes)===1)$classified[]=$label+['vehicleClass'=>(int)$classes[0],'familyNames'=>array_keys($hits[$classes[0]])];
   else $unresolved[]=$label+['reason'=>count($classes)>1?'چند کلاس متعارض از مشخصات خودرو تشخیص داده شد':'هیچ مدل تأییدشده‌ای شناسایی نشد',
    'matchingClasses'=>array_map('intval',$classes)];
  }
  return ['checked'=>count($rows),'recoverable'=>count($classified),'needReview'=>count($unresolved),
    'classified'=>$classified,'unresolved'=>$unresolved];
 }
 public static function detect(int $uid,bool $apply=false,string $confirm=''): array {
  self::requireAdmin($uid);
  if($apply&&$confirm!=='APPLY_APPROVED_VEHICLE_CLASSES')
   throw new AuthException(422,'تأیید اعمال کلاس خودرو لازم است.','CONFIRMATION_REQUIRED');
  $pdo=Connection::get();self::ensureReady($pdo);
  if($apply)$pdo->beginTransaction();
  try{
   $data=self::scan($pdo);$data['updated']=0;
   if($apply){
    $stmt=$pdo->prepare('UPDATE vehicles SET tariff_vehicle_class=? WHERE id=? AND agency_id=? AND tariff_vehicle_class IS NULL');
    foreach($data['classified'] as $v){
     $stmt->execute([$v['vehicleClass'],$v['vehicleId'],$v['agencyId']]);$data['updated']+=$stmt->rowCount();
    }
    $pdo->commit();
   }
   $data['classified']=array_slice($data['classified'],0,120);
   $data['unresolved']=array_slice($data['unresolved'],0,120);
   return $data;
  }catch(\Throwable $e){if($apply&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}

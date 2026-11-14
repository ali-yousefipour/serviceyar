<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/**
 * Restore only missing vehicle tariff classes from unambiguous existing
 * records. Never infer a class from vehicle model, Sepand approval or capacity.
 */
final class VehicleTariffClassRecovery {
 public static function plateKey(mixed $raw): string {
  $text=strtr((string)$raw,[
   'ي'=>'ی','ك'=>'ک','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
   '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1',
   '٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'
  ]);
  return preg_replace('/[\s\x{200C}\x{200F}\x{200E}\p{P}\p{Z}]+/u','',$text)??$text;
 }
 private static function candidates(PDO $pdo): array {
  $vehicles=$pdo->query("SELECT v.id,v.agency_id,v.plate,v.vin,v.model,v.tariff_vehicle_class,
     GROUP_CONCAT(DISTINCT CONCAT_WS(' ',d.first_name,d.last_name) SEPARATOR '، ') AS drivers
     FROM vehicles v LEFT JOIN drivers d ON d.vehicle_id=v.id AND d.agency_id=v.agency_id
     GROUP BY v.id,v.agency_id,v.plate,v.vin,v.model,v.tariff_vehicle_class
     ORDER BY v.agency_id,v.id")->fetchAll(PDO::FETCH_ASSOC);
  $byPlate=[];$byVin=[];$stats=[];
  foreach($vehicles as $v) {
   $agency=(int)$v['agency_id'];$class=(string)($v['tariff_vehicle_class']??'');
   if(!in_array($class,['1','2','3'],true))continue;
   $plate=self::plateKey($v['plate']);$vin=strtoupper(trim((string)($v['vin']??'')));
   if($plate!=='')$byPlate[$agency.':'.$plate][]=(int)$class;
   if($vin!=='')$byVin[$agency.':'.$vin][]=(int)$class;
  }
  $members=$pdo->query("SELECT v.id AS vehicle_id,st.tariff_vehicle_class AS class
    FROM vehicles v JOIN drivers d ON d.vehicle_id=v.id AND d.agency_id=v.agency_id
    JOIN services sv ON sv.driver_id=d.id AND sv.agency_id=d.agency_id AND sv.is_active=1
    JOIN service_students ss ON ss.service_id=sv.id
    JOIN students st ON st.id=ss.student_id AND st.agency_id=v.agency_id AND st.is_active=1
    WHERE v.is_active=1")->fetchAll(PDO::FETCH_ASSOC);
  foreach($members as $m)$stats[(int)$m['vehicle_id']][]=(string)($m['class']??'');
  $resolved=[];$unresolved=[];$existing=0;
  foreach($vehicles as $v){
   $id=(int)$v['id'];$agency=(int)$v['agency_id'];$class=(string)($v['tariff_vehicle_class']??'');
   if(in_array($class,['1','2','3'],true)){$existing++;continue;}
   $evidence=[];$invalid=false;$sources=[];
   $plate=self::plateKey($v['plate']);$vin=strtoupper(trim((string)($v['vin']??'')));
   if($plate!=='')foreach($byPlate[$agency.':'.$plate]??[] as $c){$evidence[]=$c;$sources['matching_plate']=true;}
   if($vin!=='')foreach($byVin[$agency.':'.$vin]??[] as $c){$evidence[]=$c;$sources['matching_vin']=true;}
   if(isset($stats[$id])){
    $classes=$stats[$id];
    foreach($classes as $c){
     if(!in_array($c,['1','2','3'],true)){$invalid=true;continue;}
     $evidence[]=(int)$c;
    }
    $sources['assigned_students']=true;
   }
   $values=array_values(array_unique($evidence));
   $info=['vehicleId'=>$id,'agencyId'=>$agency,'plate'=>(string)($v['plate']??''),
     'vin'=>(string)($v['vin']??''),'model'=>(string)($v['model']??''),
     'driverNames'=>(string)($v['drivers']??'')];
   if(count($values)===1&&!$invalid){
    $resolved[]=$info+['vehicleClass'=>$values[0],'evidence'=>array_keys($sources)];
   }else{
    $reason=$invalid?'کلاس یک یا چند دانش‌آموز تخصیص‌یافته نامشخص یا نامعتبر است':
      (count($values)>1?'تعارض کلاس بین سوابق خودرو و دانش‌آموزان':'هیچ کلاس معتبر و قابل‌تأییدی در سوابق پیدا نشد');
    $unresolved[]=$info+['reason'=>$reason];
   }
  }
  return ['vehiclesChecked'=>count($vehicles),'classesAlreadyPresent'=>$existing,
   'recoverable'=>count($resolved),'needManualReview'=>count($unresolved),
   'candidates'=>$resolved,'unresolved'=>$unresolved];
 }
 /** Used by the Sepand import inside its existing transaction. */
 public static function recoverKnownClasses(PDO $pdo): int {
  $data=self::candidates($pdo);
  $stmt=$pdo->prepare('UPDATE vehicles SET tariff_vehicle_class=? WHERE id=? AND agency_id=? AND tariff_vehicle_class IS NULL');
  $updated=0;
  foreach($data['candidates'] as $item){
   $stmt->execute([$item['vehicleClass'],$item['vehicleId'],$item['agencyId']]);
   $updated+=$stmt->rowCount();
  }
  return $updated;
 }
 public static function run(int $uid,bool $apply=false,string $confirm=''): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط ادمین کل مجاز است.','SUPER_ADMIN_REQUIRED');
  if($apply&&$confirm!=='RESTORE_VEHICLE_TARIFF_CLASSES')
   throw new AuthException(422,'تأیید عملیات بازیابی کلاس خودرو لازم است.','CONFIRMATION_REQUIRED');
  $pdo=Connection::get();
  // No modification of service, student, tariff, invoice or payroll records.
  if($apply)$pdo->beginTransaction();
  try{
   $data=self::candidates($pdo);$data['updated']=0;
   if($apply){
    $stmt=$pdo->prepare('UPDATE vehicles SET tariff_vehicle_class=? WHERE id=? AND agency_id=? AND tariff_vehicle_class IS NULL');
    foreach($data['candidates'] as $item){
     $stmt->execute([$item['vehicleClass'],$item['vehicleId'],$item['agencyId']]);
     $data['updated']+=$stmt->rowCount();
    }
    $pdo->commit();
   }
   // Avoid overly large JSON payloads for shared-host mobile clients.
   $data['unresolvedTotal']=$data['needManualReview'];
   $data['unresolved']=array_slice($data['unresolved'],0,150);
   $data['candidates']=array_slice($data['candidates'],0,150);
   return $data;
  }catch(\Throwable $e){if($apply&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}

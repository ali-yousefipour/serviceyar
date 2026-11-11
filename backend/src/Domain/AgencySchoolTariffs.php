<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use PDO;

final class AgencySchoolTariffs {
 public const YEAR='1405-1406';
 public static function seedArta(int $uid,int $aid): array {
  Authorization::requirePermission($uid,'settings.manage');
  Authorization::requireAgencyAccess($uid,$aid);
  $pdo=Connection::get();
  $q=$pdo->prepare('SELECT name FROM agencies WHERE id=?');$q->execute([$aid]);
  $name=preg_replace('/[\x{200c}\s]+/u','',(string)$q->fetchColumn());
  if(!in_array($name,['ارتانوین','آرتانوین'],true))throw new AuthException(422,'این نرخ‌نامه فقط برای شرکت ارتانوین است.','AGENCY_TARIFF_MISMATCH');
  $rates=json_decode(file_get_contents(__DIR__.'/school-tariffs-1405.json'),true,512,JSON_THROW_ON_ERROR);
  $q=$pdo->prepare("INSERT INTO agency_school_tariffs(agency_id,academic_year,region,vehicle_class,distance_km,amount_irr)
   VALUES(?,?, '',?,?,?) ON DUPLICATE KEY UPDATE amount_irr=VALUES(amount_irr)");
  $count=0;
  foreach(['1','2'] as $class)foreach($rates[$class] as $km=>$amount){
   if((int)$km<3||(int)$km>20)continue;
   $q->execute([$aid,self::YEAR,(int)$class,(int)$km,(int)$amount]);$count++;
  }
  return ['insertedOrUpdated'=>$count,'currency'=>'IRR','academicYear'=>self::YEAR];
 }
 public static function quote(PDO $pdo,int $aid,int $schoolId,string $year,mixed $distance,mixed $class): ?int {
  $year=str_replace('/','-',$year);
  if($distance===null||$class===null||!is_numeric($distance)||!in_array((string)$class,['1','2','3'],true))return null;
  $km=(float)$distance;
  if(!is_finite($km)||$km<3||$km>1000)return null;
  // Exact kilometre table only: no silent rounding or interpolation.
  if(floor($km)!==$km)return null;
  $s=$pdo->prepare('SELECT region FROM schools WHERE id=? AND agency_id=?');$s->execute([$schoolId,$aid]);$region=$s->fetchColumn();
  if($region===false)return null;
  $q=$pdo->prepare("SELECT amount_irr FROM agency_school_tariffs WHERE agency_id=:a AND academic_year=:y
   AND vehicle_class=:c AND distance_km=:km AND region IN ('',:region)
   ORDER BY (region=:region2) DESC LIMIT 1");
  $q->execute(['a'=>$aid,'y'=>$year,'c'=>(int)$class,'km'=>(int)$km,'region'=>(string)$region,'region2'=>(string)$region]);
  $fee=$q->fetchColumn();return $fee===false?null:(int)$fee;
 }
 public static function recalculate(int $uid,int $aid): array {
  Authorization::requirePermission($uid,'students.manage');Authorization::requireAgencyAccess($uid,$aid);
  $pdo=Connection::get();$q=$pdo->prepare('SELECT id,school_id,academic_year,tariff_distance_km,tariff_vehicle_class FROM students WHERE agency_id=? AND is_active=1');$q->execute([$aid]);
  $update=$pdo->prepare('UPDATE students SET monthly_fee=?,tariff_calculated_at=NOW() WHERE id=? AND agency_id=?');
  $updated=0;$skipped=0;
  foreach($q as $r){
   $fee=self::quote($pdo,$aid,(int)$r['school_id'],(string)($r['academic_year']?:self::YEAR),$r['tariff_distance_km'],$r['tariff_vehicle_class']);
   if($fee===null){$skipped++;continue;}
   $update->execute([$fee,$r['id'],$aid]);$updated++;
  }
  return ['updated'=>$updated,'skipped'=>$skipped,'currency'=>'IRR'];
 }
}

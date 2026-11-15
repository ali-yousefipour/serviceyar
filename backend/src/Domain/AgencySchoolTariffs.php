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
  Authorization::requirePermission($uid,'students.manage');
  Authorization::requireAgencyAccess($uid,$aid);
  // Reprice only agency-rate students. Official Taxi Organization and agreed
  // prices are independent contracts and must never be overwritten here.
  return OperationsService::transaction(function(PDO $pdo)use($aid): array {
   $query=$pdo->prepare("SELECT id,school_id,academic_year,tariff_distance_km,tariff_vehicle_class
     FROM students
     WHERE agency_id=? AND is_active=1 AND fee_source='agency'
     ORDER BY id FOR UPDATE");
   $query->execute([$aid]);
   $students=$query->fetchAll(PDO::FETCH_ASSOC);
   $update=$pdo->prepare("UPDATE students
     SET monthly_fee=?,driver_base_monthly=?,tariff_calculated_at=NOW()
     WHERE id=? AND agency_id=? AND fee_source='agency'");
   $linked=$pdo->prepare("SELECT DISTINCT sv.id
     FROM service_students ss JOIN services sv ON sv.id=ss.service_id
     WHERE ss.student_id=? AND sv.agency_id=? AND sv.is_active=1
       AND sv.status<>'cancelled'");
   $updated=0;$unchanged=0;$skipped=0;$syncedInvoices=0;$serviceIds=[];
   foreach($students as $student){
    $id=(int)$student['id'];
    $fee=self::quote($pdo,$aid,(int)$student['school_id'],
       (string)($student['academic_year']?:self::YEAR),
       $student['tariff_distance_km'],$student['tariff_vehicle_class']);
    if($fee===null){$skipped++;continue;}
    $current=$pdo->prepare('SELECT monthly_fee,driver_base_monthly FROM students WHERE id=? AND agency_id=?');
    $current->execute([$id,$aid]);
    $before=$current->fetch(PDO::FETCH_ASSOC);
    if($before && (int)$before['monthly_fee']===$fee && (int)$before['driver_base_monthly']===$fee){
     $unchanged++;
     continue;
    }
    $update->execute([$fee,$fee,$id,$aid]);
    $invoice=StudentServiceBilling::sync($pdo,$aid,$id);
    if($invoice!==null)$syncedInvoices++;
    $linked->execute([$id,$aid]);
    foreach($linked->fetchAll(PDO::FETCH_COLUMN) as $serviceId)$serviceIds[(int)$serviceId]=true;
    $updated++;
   }
   $gross=$pdo->prepare("SELECT COALESCE(SUM(COALESCE(st.driver_base_monthly,st.monthly_fee)),0)
     FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=?
     WHERE ss.service_id=?");
   $serviceUpdate=$pdo->prepare("UPDATE services SET monthly_fee=? WHERE id=? AND agency_id=?");
   foreach(array_keys($serviceIds) as $serviceId){
    $gross->execute([$aid,$serviceId]);
    $net=OperationsService::driverDeductions((float)$gross->fetchColumn(),$aid)['net_earned'];
    $serviceUpdate->execute([$net,$serviceId,$aid]);
   }
   return [
    'updated'=>$updated,'unchanged'=>$unchanged,'skipped'=>$skipped,
    'invoicesSynced'=>$syncedInvoices,'servicesUpdated'=>count($serviceIds),
    'currency'=>'IRR','feeSource'=>'agency'
   ];
  });
 }

}

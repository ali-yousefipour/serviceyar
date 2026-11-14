<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/**
 * Explicit one-time normalization of legacy service tariffs.
 * Preview is read-only; apply is atomic and keeps an audit snapshot.
 * Posted receipts, payroll documents, invoices and ledger entries are not rewritten.
 */
final class LegacyServiceTariffMigration {
 public static function run(int $uid,int $aid,bool $apply): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط ادمین کل مجاز به اصلاح گروهی نرخ‌ها است.','SUPER_ADMIN_REQUIRED');
  Authorization::requireAgencyAccess($uid,$aid);
  $pdo=Connection::get();
  $sql="SELECT DISTINCT st.id, st.school_id, st.tariff_vehicle_class, st.tariff_distance_km,
       st.monthly_fee, st.driver_base_monthly, st.fee_source, st.academic_year, sc.region
       FROM students st JOIN service_students ss ON ss.student_id=st.id
       JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=st.agency_id
       JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
       WHERE st.agency_id=? ORDER BY st.id";
  $q=$pdo->prepare($sql);$q->execute([$aid]);
  $changes=[];$issues=[];$regions=[];
  // Normalize every school in the agency, including schools without assigned students.
  $schoolList=$pdo->prepare('SELECT id,region FROM schools WHERE agency_id=?');
  $schoolList->execute([$aid]);
  foreach($schoolList->fetchAll(PDO::FETCH_ASSOC) as $school){
   $canonical=self::normalize($school['region']);
   if($canonical===null){$issues[]=['schoolId'=>(int)$school['id'],'reason'=>'ناحیه مدرسه نامعتبر: '.(string)$school['region']];continue;}
   $regions[(int)$school['id']]=$canonical;
  }
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
   $normalized=self::normalize($row['region']);
   if($normalized===null){$issues[]=['studentId'=>(int)$row['id'],'reason'=>'ناحیه مدرسه نامعتبر: '.$row['region']];continue;}
   $regions[(int)$row['school_id']]=$normalized;
   try{
    $quote=TaxiOrganizationTariffs::quote($pdo,(int)($normalized==='تبادکان'?8:$normalized),$row['tariff_vehicle_class'],$row['tariff_distance_km']);
    $changes[]=['id'=>(int)$row['id'],'schoolId'=>(int)$row['school_id'],
     'oldFee'=>(int)$row['monthly_fee'],'newFee'=>(int)$quote['parentMonthly'],
     'oldDriverBase'=>(int)($row['driver_base_monthly']??0),'newDriverBase'=>(int)$quote['driverMonthly'],
     'oldSource'=>(string)$row['fee_source']];
   }catch(\Throwable $e){$issues[]=['studentId'=>(int)$row['id'],'reason'=>$e->getMessage()];}
  }
  $response=['agencyId'=>$aid,'studentsChecked'=>count($changes)+count($issues),'studentsReady'=>count($changes),
   'schoolsToNormalize'=>count($regions),'errors'=>$issues,'applied'=>false,
   'monthlyDifference'=>array_sum(array_column($changes,'newFee'))-array_sum(array_column($changes,'oldFee'))];
  if(!$apply||$issues)return $response;
  $existing=$pdo->query("SHOW TABLES LIKE 'service_tariff_repricing_audit'")->fetchColumn();
  if(!$existing)throw new AuthException(503,'ابتدا مهاجرت پایگاه داده نرخ‌نامه را اجرا کنید.','TARIFF_AUDIT_MIGRATION_REQUIRED');
  $run=bin2hex(random_bytes(16));$pdo->beginTransaction();
  try{
   $audit=$pdo->prepare("INSERT INTO service_tariff_repricing_audit(run_id,agency_id,entity_type,entity_id,old_data,new_data,created_by) VALUES(?,?,?,?,?,?,?)");
   $schoolOld=$pdo->prepare('SELECT region FROM schools WHERE id=? AND agency_id=? FOR UPDATE');
   $updateSchool=$pdo->prepare('UPDATE schools SET region=? WHERE id=? AND agency_id=?');
   foreach($regions as $sid=>$region){
    $schoolOld->execute([$sid,$aid]);$old=(string)$schoolOld->fetchColumn();
    if($old===$region)continue;
    $audit->execute([$run,$aid,'school',$sid,json_encode(['region'=>$old],JSON_UNESCAPED_UNICODE),json_encode(['region'=>$region],JSON_UNESCAPED_UNICODE),$uid]);
    $updateSchool->execute([$region,$sid,$aid]);
   }
   $updateStudent=$pdo->prepare("UPDATE students SET fee_source='taxi',monthly_fee=?,driver_base_monthly=?,tariff_calculated_at=NOW() WHERE id=? AND agency_id=?");
   foreach($changes as $c){
    $audit->execute([$run,$aid,'student',$c['id'],json_encode(['monthlyFee'=>$c['oldFee'],'driverBaseMonthly'=>$c['oldDriverBase'],'feeSource'=>$c['oldSource']]),json_encode(['monthlyFee'=>$c['newFee'],'driverBaseMonthly'=>$c['newDriverBase'],'feeSource'=>'taxi']),$uid]);
    $updateStudent->execute([$c['newFee'],$c['newDriverBase'],$c['id'],$aid]);
   }
   $services=$pdo->prepare('SELECT DISTINCT sv.id,sv.monthly_fee,sv.fee_source FROM services sv JOIN service_students ss ON ss.service_id=sv.id JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id WHERE sv.agency_id=? ORDER BY sv.id FOR UPDATE');
   $services->execute([$aid]);$rows=$services->fetchAll(PDO::FETCH_ASSOC);
   $gross=$pdo->prepare('SELECT COALESCE(SUM(COALESCE(st.driver_base_monthly,st.monthly_fee)),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=? WHERE ss.service_id=?');
   $updateService=$pdo->prepare("UPDATE services SET fee_source='taxi',monthly_fee=? WHERE id=? AND agency_id=?");
   foreach($rows as $s){
    $gross->execute([$aid,$s['id']]);$net=OperationsService::driverDeductions((float)$gross->fetchColumn(),$aid)['net_earned'];
    $audit->execute([$run,$aid,'service',$s['id'],json_encode(['monthlyFee'=>$s['monthly_fee'],'feeSource'=>$s['fee_source']]),json_encode(['monthlyFee'=>$net,'feeSource'=>'taxi']),$uid]);
    $updateService->execute([$net,$s['id'],$aid]);
   }
   $pdo->commit();$response['applied']=true;$response['runId']=$run;$response['servicesUpdated']=count($rows);
  }catch(\Throwable $e){$pdo->rollBack();throw $e;}
  return $response;
 }
 private static function normalize(mixed $raw): ?string {
  $v=trim(strtr((string)$raw,['۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7']));
  $v=preg_replace('/^(?:ناحیه|منطقه)\s*/u','',$v);
  $v=['یک'=>'1','دو'=>'2','سه'=>'3','چهار'=>'4','پنج'=>'5','شش'=>'6','هفت'=>'7'][$v]??$v;if($v==='8')$v='تبادکان';
  return in_array($v,['1','2','3','4','5','6','7','تبادکان'],true)?$v:null;
 }
}

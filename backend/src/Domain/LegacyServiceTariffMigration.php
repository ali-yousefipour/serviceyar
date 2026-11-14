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
  // Cover ALL services, including drafts, cancelled and assignments without students.
  // The legacy code is stable, derived from immutable database ids and checked against existing codes.
  $sq=$pdo->prepare('SELECT id,code,monthly_fee,fee_source FROM services WHERE agency_id=? ORDER BY id');
  $sq->execute([$aid]);$allServices=$sq->fetchAll(PDO::FETCH_ASSOC);
  $usedCodes=[];
  $globalCodes=$pdo->query("SELECT code FROM services WHERE code IS NOT NULL AND TRIM(code)<>''");
  foreach($globalCodes->fetchAll(PDO::FETCH_COLUMN) as $code)$usedCodes[(string)$code]=true;
  $codeUpdates=[];
  foreach($allServices as $service){
   if(trim((string)($service['code']??''))!=='')continue;
   $candidate='SY-'.(int)$aid.'-'.(int)$service['id'];
   if(isset($usedCodes[$candidate]))$candidate='SY-L-'.(int)$aid.'-'.(int)$service['id'].'-'.strtoupper(bin2hex(random_bytes(3)));
   while(isset($usedCodes[$candidate]))$candidate='SY-L-'.(int)$aid.'-'.(int)$service['id'].'-'.strtoupper(bin2hex(random_bytes(3)));
   $usedCodes[$candidate]=true;
   $codeUpdates[(int)$service['id']]=$candidate;
  }
  $studentCandidates=array_fill_keys(array_column($changes,'id'),true);
  $membership=$pdo->prepare('SELECT ss.service_id,ss.student_id FROM service_students ss JOIN services sv ON sv.id=ss.service_id WHERE sv.agency_id=?');
  $membership->execute([$aid]);$serviceMembers=[];
  foreach($membership->fetchAll(PDO::FETCH_ASSOC) as $m)$serviceMembers[(int)$m['service_id']][]=(int)$m['student_id'];
  $eligibleServices=0;$skippedServices=0;
  foreach($allServices as $service){
   $memberIds=$serviceMembers[(int)$service['id']]??[];
   if($memberIds&&count(array_filter($memberIds,static fn($id)=>isset($studentCandidates[$id])))===count($memberIds))$eligibleServices++;
   else $skippedServices++;
  }
  $differentStudents=array_values(array_filter($changes,static fn($c)=>
   $c['oldFee']!==$c['newFee']||$c['oldDriverBase']!==$c['newDriverBase']||$c['oldSource']!=='taxi'));
  $response=['agencyId'=>$aid,'studentsChecked'=>count($changes)+count($issues),'studentsReady'=>count($changes),
   'studentsToChange'=>count($differentStudents),'studentsAlreadyCurrent'=>count($changes)-count($differentStudents),
   'studentChanges'=>$differentStudents,
   'servicesChecked'=>count($allServices),'servicesWithoutCode'=>count($codeUpdates),
   'servicesReady'=>$eligibleServices,'servicesSkipped'=>$skippedServices,
   'schoolsToNormalize'=>count($regions),'errors'=>$issues,'applied'=>false,
   'monthlyDifference'=>array_sum(array_column($changes,'newFee'))-array_sum(array_column($changes,'oldFee'))];
  if(!$apply){$response['errors']=self::describeErrors($pdo,$aid,$issues);return $response;}
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
   $updateCode=$pdo->prepare("UPDATE services SET code=? WHERE id=? AND agency_id=? AND (code IS NULL OR TRIM(code)='')");
   foreach($codeUpdates as $sid=>$newCode){
    $audit->execute([$run,$aid,'service-code',$sid,json_encode(['code'=>null]),json_encode(['code'=>$newCode]),$uid]);
    $updateCode->execute([$newCode,$sid,$aid]);
   }
   // Invalid records must not prevent repricing unrelated valid services.
   // Each student is isolated with a savepoint so an invoice-specific error
   // cannot roll back the company's other valid tuition corrections.
   $updateStudent=$pdo->prepare("UPDATE students SET fee_source='taxi',monthly_fee=?,driver_base_monthly=?,tariff_calculated_at=NOW() WHERE id=? AND agency_id=?");
   $successStudents=[];$updatedStudents=0;$unchangedStudents=0;$invoicesSynced=0;$verifiedStudents=0;
   $verifyStudent=$pdo->prepare('SELECT monthly_fee,driver_base_monthly,fee_source FROM students WHERE id=? AND agency_id=?');
   foreach($changes as $c){
    $pdo->exec('SAVEPOINT sy_reprice_student');
    try{
     $changed=$c['oldFee']!==$c['newFee']||$c['oldDriverBase']!==$c['newDriverBase']||$c['oldSource']!=='taxi';
     if($changed){
      $audit->execute([$run,$aid,'student',$c['id'],json_encode(['monthlyFee'=>$c['oldFee'],'driverBaseMonthly'=>$c['oldDriverBase'],'feeSource'=>$c['oldSource']]),json_encode(['monthlyFee'=>$c['newFee'],'driverBaseMonthly'=>$c['newDriverBase'],'feeSource'=>'taxi']),$uid]);
      $updateStudent->execute([$c['newFee'],$c['newDriverBase'],$c['id'],$aid]);
     }
     if(StudentServiceBilling::sync($pdo,$aid,(int)$c['id'])!==null)$invoicesSynced++;
     $verifyStudent->execute([$c['id'],$aid]);$saved=$verifyStudent->fetch(PDO::FETCH_ASSOC);
     if(!$saved||(int)$saved['monthly_fee']!==$c['newFee']||(int)$saved['driver_base_monthly']!==$c['newDriverBase']||$saved['fee_source']!=='taxi')
      throw new \RuntimeException('تأیید ذخیره شهریه دانش‌آموز ناموفق بود.');
     $pdo->exec('RELEASE SAVEPOINT sy_reprice_student');
     $successStudents[(int)$c['id']]=true;$verifiedStudents++;
     if($changed)$updatedStudents++;else $unchangedStudents++;
    }catch(\Throwable $error){
     $pdo->exec('ROLLBACK TO SAVEPOINT sy_reprice_student');
     $pdo->exec('RELEASE SAVEPOINT sy_reprice_student');
     $issues[]=['studentId'=>(int)$c['id'],'reason'=>'خطای اصلاح شهریه یا فاکتور: '.$error->getMessage()];
    }
   }
   $services=$pdo->prepare('SELECT id,monthly_fee,fee_source FROM services WHERE agency_id=? ORDER BY id FOR UPDATE');
   $services->execute([$aid]);$rows=$services->fetchAll(PDO::FETCH_ASSOC);
   $gross=$pdo->prepare('SELECT COALESCE(SUM(COALESCE(st.driver_base_monthly,st.monthly_fee)),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=? WHERE ss.service_id=?');
   $updateService=$pdo->prepare("UPDATE services SET fee_source='taxi',monthly_fee=? WHERE id=? AND agency_id=?");
   $updatedServices=0;$unchangedServices=0;$verifiedServices=0;$actualServiceChanges=[];
   $verifyService=$pdo->prepare('SELECT monthly_fee,fee_source FROM services WHERE id=? AND agency_id=?');
   foreach($rows as $s){
    $memberIds=$serviceMembers[(int)$s['id']]??[];
    if(!$memberIds){
     $issues[]=['serviceId'=>(int)$s['id'],'reason'=>'سرویس دانش‌آموز تخصیص‌یافته ندارد؛ مبلغ قبلی بدون تغییر باقی ماند.'];
     continue;
    }
    if(count(array_filter($memberIds,static fn($id)=>isset($successStudents[$id])))!==count($memberIds)){
     $issues[]=['serviceId'=>(int)$s['id'],'reason'=>'اطلاعات تعرفه یا فاکتور یک یا چند دانش‌آموز ناقص است؛ از درج مبلغ ناقص برای راننده جلوگیری شد.'];
     continue;
    }
    $gross->execute([$aid,$s['id']]);$net=OperationsService::driverDeductions((float)$gross->fetchColumn(),$aid)['net_earned'];
    $newFee=(int)round($net);$oldFee=(int)round((float)$s['monthly_fee']);
    $changed=$newFee!==$oldFee||(string)$s['fee_source']!=='taxi';
    if($changed){
     $audit->execute([$run,$aid,'service',$s['id'],json_encode(['monthlyFee'=>$oldFee,'feeSource'=>$s['fee_source']]),json_encode(['monthlyFee'=>$newFee,'feeSource'=>'taxi']),$uid]);
     $updateService->execute([$newFee,$s['id'],$aid]);
    }
    $verifyService->execute([$s['id'],$aid]);$saved=$verifyService->fetch(PDO::FETCH_ASSOC);
    if(!$saved||(int)round((float)$saved['monthly_fee'])!==$newFee||(string)$saved['fee_source']!=='taxi')
     throw new \RuntimeException('تأیید ذخیره مبلغ سرویس '.$s['id'].' ناموفق بود.');
    $verifiedServices++;
    if($changed){$updatedServices++;$actualServiceChanges[]=['serviceId'=>(int)$s['id'],'oldFee'=>$oldFee,'newFee'=>$newFee,'oldSource'=>(string)$s['fee_source']];}
    else $unchangedServices++;
   }
   $pdo->commit();
   $response['applied']=true;$response['runId']=$run;
   $response['studentsUpdated']=$updatedStudents;$response['studentsUnchanged']=$unchangedStudents;
   $response['studentsVerified']=$verifiedStudents;$response['invoicesSynced']=$invoicesSynced;
   $response['servicesUpdated']=$updatedServices;$response['servicesUnchanged']=$unchangedServices;
   $response['servicesVerified']=$verifiedServices;$response['serviceChanges']=$actualServiceChanges;
   $response['servicesSkipped']=count($rows)-$verifiedServices;
   $response['codesFilled']=count($codeUpdates);$response['errors']=self::describeErrors($pdo,$aid,$issues);
   $response['partial']=count($issues)>0;
  }catch(\Throwable $e){$pdo->rollBack();throw $e;}
  return $response;
 }
 private static function describeErrors(PDO $pdo,int $agencyId,array $errors): array {
  if(!$errors)return [];
  $students=[];$schools=[];$services=[];
  // Each lookup is scoped to the company; the returned labels are diagnostics
  // for the authorized super-admin, not client-supplied identifiers.
  $q=$pdo->prepare("SELECT st.id,st.first_name,st.last_name,st.grade,st.national_code,st.tariff_vehicle_class,
    st.tariff_distance_km,st.school_id,sc.name AS school_name
    FROM students st LEFT JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
    WHERE st.agency_id=?");
  $q->execute([$agencyId]);
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $st){
   $st['serviceNames']=[];$students[(int)$st['id']]=$st;
  }
  $q=$pdo->prepare('SELECT id,name,region FROM schools WHERE agency_id=?');
  $q->execute([$agencyId]);
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $school)$schools[(int)$school['id']]=$school;
  $q=$pdo->prepare("SELECT sv.id,sv.title,sv.code,sv.school_id,sc.name AS school_name,
    CONCAT_WS(' ',dr.first_name,dr.last_name) AS driver_name,
    st.id AS student_id
    FROM services sv
    LEFT JOIN schools sc ON sc.id=sv.school_id AND sc.agency_id=sv.agency_id
    LEFT JOIN drivers dr ON dr.id=sv.driver_id AND dr.agency_id=sv.agency_id
    LEFT JOIN service_students ss ON ss.service_id=sv.id
    LEFT JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
    WHERE sv.agency_id=?");
  $q->execute([$agencyId]);
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $sv){
   $id=(int)$sv['id'];
   if(!isset($services[$id]))$services[$id]=[
    'id'=>$id,'title'=>(string)$sv['title'],'code'=>(string)($sv['code']??''),
    'schoolName'=>(string)($sv['school_name']??''),
    'driverName'=>trim((string)($sv['driver_name']??'')),'studentNames'=>[]
   ];
   $studentId=(int)($sv['student_id']??0);
   if($studentId && isset($students[$studentId])){
    $label=trim((string)$students[$studentId]['first_name'].' '.(string)$students[$studentId]['last_name']);
    $services[$id]['studentNames'][$studentId]=$label;
    $students[$studentId]['serviceNames'][$id]=trim((string)$sv['title']).($sv['code']?' ('.(string)$sv['code'].')':'');
   }
  }
  foreach($errors as &$error){
   if(isset($error['studentId'],$students[(int)$error['studentId']])){
    $st=$students[(int)$error['studentId']];
    $error['studentName']=trim((string)$st['first_name'].' '.(string)$st['last_name']);
    $error['schoolName']=(string)($st['school_name']??'');
    $error['grade']=(string)($st['grade']??'');
    $error['vehicleClass']=(string)($st['tariff_vehicle_class']??'');
    $error['distanceKm']=$st['tariff_distance_km'];
    $error['serviceNames']=array_values($st['serviceNames']);
   }
   if(isset($error['schoolId'],$schools[(int)$error['schoolId']])){
    $school=$schools[(int)$error['schoolId']];
    $error['schoolName']=(string)$school['name'];
    $error['region']=(string)$school['region'];
   }
   if(isset($error['serviceId'],$services[(int)$error['serviceId']])){
    $sv=$services[(int)$error['serviceId']];
    $error['serviceTitle']=$sv['title'];$error['serviceCode']=$sv['code'];
    $error['schoolName']=$sv['schoolName'];$error['driverName']=$sv['driverName'];
    $error['studentNames']=array_values($sv['studentNames']);
   }
  }unset($error);
  return $errors;
 }
 private static function normalize(mixed $raw): ?string {
  $v=trim(strtr((string)$raw,['۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7']));
  $v=preg_replace('/^(?:ناحیه|منطقه)\s*/u','',$v);
  $v=['یک'=>'1','دو'=>'2','سه'=>'3','چهار'=>'4','پنج'=>'5','شش'=>'6','هفت'=>'7'][$v]??$v;if($v==='8')$v='تبادکان';
  return in_array($v,['1','2','3','4','5','6','7','تبادکان'],true)?$v:null;
 }
}

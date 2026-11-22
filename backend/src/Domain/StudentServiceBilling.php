<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** A stable invoice per assigned student and academic year. Never alters received money. */
final class StudentServiceBilling {
 public static function sync(PDO $pdo,int $agencyId,int $studentId): ?array {
  // Assignment/repricing no longer creates or changes tuition invoices.
  // The immutable issued parent contract is the only automatic billing trigger.
  return null;
 }

 public static function account(int $uid,int $agencyId,int $studentId): array {
  Authorization::requirePermission($uid,'students.view');
  Authorization::requireAgencyAccess($uid,$agencyId);
  $pdo=Connection::get();
  $q=$pdo->prepare("SELECT i.id,i.title,i.amount,i.status,i.issue_date,i.due_date
    FROM invoices i WHERE i.agency_id=? AND i.party_type='student' AND i.party_id=? AND i.status<>'cancelled' ORDER BY i.id DESC");
  $q->execute([$agencyId,$studentId]);$invoices=$q->fetchAll(PDO::FETCH_ASSOC);
  $p=$pdo->prepare("SELECT p.id,p.invoice_id,p.amount,p.payment_date,p.method,p.reference_no,'invoice' AS source
    FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id
    WHERE p.agency_id=? AND i.party_type='student' AND i.party_id=? ORDER BY p.payment_date DESC,p.id DESC");
  $p->execute([$agencyId,$studentId]);$payments=$p->fetchAll(PDO::FETCH_ASSOC);
  $l=$pdo->prepare("SELECT le.id,le.amount,le.entry_date AS payment_date,le.method,le.reference_no,le.direction
    FROM ledger_entries le WHERE le.agency_id=? AND le.party_type='student' AND le.party_id=? AND le.is_active=1 ORDER BY le.entry_date DESC,le.id DESC");
  $l->execute([$agencyId,$studentId]);$ledger=$l->fetchAll(PDO::FETCH_ASSOC);
  $total=(float)array_sum(array_column($invoices,'amount'));
  $received=(float)array_sum(array_column($payments,'amount'));
  foreach($ledger as $row){$received+=($row['direction']==='receipt'?1:-1)*(float)$row['amount'];$payments[]=[
   'id'=>$row['id'],'invoice_id'=>null,'amount'=>($row['direction']==='receipt'?1:-1)*(float)$row['amount'],
   'payment_date'=>$row['payment_date'],'method'=>$row['method'],'reference_no'=>$row['reference_no'],'source'=>'ledger'];}
  $adjustments=[];
  if(VehicleClassSchema::exists($pdo)){
  $adj=$pdo->prepare("SELECT a.id,a.change_id,a.academic_year,a.effective_date,a.amount_irr,vc.old_vehicle_class,vc.new_vehicle_class
      FROM vehicle_class_debt_adjustments a JOIN vehicle_class_changes vc ON vc.id=a.change_id AND vc.agency_id=a.agency_id
      WHERE a.agency_id=? AND a.student_id=? ORDER BY a.effective_date DESC,a.id DESC");
  $adj->execute([$agencyId,$studentId]);
  $adjustments=$adj->fetchAll(PDO::FETCH_ASSOC);
  $total+=(float)array_sum(array_map(static fn($a)=>((string)$a['effective_date']<=date('Y-m-d'))?(float)$a['amount_irr']:0.0,$adjustments));
  }
  StudentBalance::allocate($invoices,$received);
  return ['invoices'=>$invoices,'payments'=>$payments,'vehicleClassAdjustments'=>$adjustments,'totals'=>StudentBalance::totals($total,$received)];
 }

 public static function driverAccount(int $uid,int $agencyId,int $driverId): array {
  Authorization::requirePermission($uid,'drivers.view');Authorization::requireAgencyAccess($uid,$agencyId);
  $pdo=Connection::get();
  $q=$pdo->prepare("SELECT sv.id,sv.code,sv.monthly_fee FROM services sv WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1 AND sv.status='active'");
  $q->execute([$agencyId,$driverId]);$services=$q->fetchAll(PDO::FETCH_ASSOC);
  $monthly=(float)array_sum(array_column($services,'monthly_fee'));
  // Full academic-year driver entitlement is based on each student's driver
  // tariff and actual school duration, with agency deductions applied once.
  $grossQuery=$pdo->prepare("SELECT st.id AS student_id,st.academic_year,sv.id AS service_id,st.grade,sc.school_type,sc.education_levels,COALESCE(st.driver_base_monthly,st.monthly_fee) base_monthly
   FROM service_students ss JOIN services sv ON sv.id=ss.service_id
   JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
   JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
   WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1 AND sv.status='active' AND st.is_active=1");
  $grossQuery->execute([$agencyId,$driverId]);
  $ratePeriods=[];
  if(VehicleClassSchema::exists($pdo)){
  $rateQuery=$pdo->prepare("SELECT p.id,p.subject_id,p.service_id,p.effective_date,p.old_monthly_irr,p.new_monthly_irr
      FROM vehicle_class_rate_periods p
      JOIN services sv ON sv.id=p.service_id AND sv.agency_id=p.agency_id
      WHERE p.agency_id=? AND sv.driver_id=? AND p.subject_type='driver_pay'
      ORDER BY p.subject_id,p.service_id,p.effective_date,p.id");
  $rateQuery->execute([$agencyId,$driverId]);
  foreach($rateQuery->fetchAll(PDO::FETCH_ASSOC) as $entry)
      $ratePeriods[(int)$entry['subject_id'].':'.(int)$entry['service_id']][]=$entry;
  }
  $annualGross=0;
  foreach($grossQuery->fetchAll(PDO::FETCH_ASSOC) as $row){
    $months=ServiceFinancialBasis::monthsOfStudent($row);
    $rate=$ratePeriods[(int)$row['student_id'].':'.(int)$row['service_id']]??[];
    if($rate && !empty($row['academic_year'])){
      $year=ContractBilling::year((string)$row['academic_year']);
      $annualGross+=VehicleClassEffectiveDates::annualEntitlement($year,$months===8.5,
          (int)round((float)$row['base_monthly']),$rate);
    }else{
      $annualGross+=(int)round((float)$row['base_monthly']*$months);
    }
  }
  $deductions=OperationsService::driverDeductions($annualGross,$agencyId);
  $annualNet=(float)$deductions['net_earned'];
  $p=$pdo->prepare('SELECT id,amount,payment_date,reference_no FROM driver_payments WHERE agency_id=? AND driver_id=? ORDER BY payment_date DESC,id DESC');
  $p->execute([$agencyId,$driverId]);$paid=$p->fetchAll(PDO::FETCH_ASSOC);
  $l=$pdo->prepare("SELECT id,amount,entry_date,direction FROM ledger_entries WHERE agency_id=? AND party_type='driver' AND party_id=? AND is_active=1");
  $l->execute([$agencyId,$driverId]);$ledger=$l->fetchAll(PDO::FETCH_ASSOC);
  $paidTotal=(float)array_sum(array_column($paid,'amount'));
  foreach($ledger as $entry){
   $value=($entry['direction']==='payment'?1:-1)*(float)$entry['amount'];
   $paidTotal+=$value;
   $paid[]=['id'=>$entry['id'],'amount'=>$value,'payment_date'=>$entry['entry_date'],
    'reference_no'=>null,'source'=>'ledger'];
  }
  usort($paid,static fn($a,$b)=>strcmp((string)$b['payment_date'],(string)$a['payment_date']) ?: (int)$b['id']<=>(int)$a['id']);
  return ['services'=>$services,'payments'=>$paid,'monthlyEntitlement'=>$monthly,
   'estimatedAnnualEntitlement'=>$annualNet,'grossAnnualEntitlement'=>$annualGross,'paidTotal'=>$paidTotal,
   'remaining'=>max(0,$annualNet-$paidTotal),'overpaid'=>max(0,$paidTotal-$annualNet),
   'note'=>'برآورد سالانه بر اساس نرخ دانش‌آموزان تخصیص‌یافته و کسورات شرکت است؛ پرداخت‌های ثبت‌شده به راننده از آن کسر می‌شوند. اسناد نهایی حقوق مستقل هستند.'];
 }
}

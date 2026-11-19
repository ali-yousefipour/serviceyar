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
  // Apply payments to the oldest invoice first for an accurate per-invoice
  // remaining amount. No source receipt is changed or counted twice.
  $available=max(0,$received);
  for($index=count($invoices)-1;$index>=0;$index--){
   $amount=(float)$invoices[$index]['amount'];$allocated=min($amount,$available);
   $available-=$allocated;
   $invoices[$index]['paidAmount']=$allocated;
   $invoices[$index]['remainingAmount']=max(0,$amount-$allocated);
   $invoices[$index]['displayStatus']=$allocated >= $amount?'paid':'open';
  }
  return ['invoices'=>$invoices,'payments'=>$payments,'totals'=>[
   'invoiced'=>$total,'received'=>$received,'balance'=>max(0,$total-$received),'credit'=>max(0,$received-$total)]];
 }

 public static function driverAccount(int $uid,int $agencyId,int $driverId): array {
  Authorization::requirePermission($uid,'drivers.view');Authorization::requireAgencyAccess($uid,$agencyId);
  $pdo=Connection::get();
  $q=$pdo->prepare("SELECT sv.id,sv.code,sv.monthly_fee FROM services sv WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1 AND sv.status='active'");
  $q->execute([$agencyId,$driverId]);$services=$q->fetchAll(PDO::FETCH_ASSOC);
  $monthly=(float)array_sum(array_column($services,'monthly_fee'));
  // Full academic-year driver entitlement is based on each student's driver
  // tariff and actual school duration, with agency deductions applied once.
  $grossQuery=$pdo->prepare("SELECT
   COALESCE(SUM(st.monthly_fee *
     (CASE WHEN sc.school_type REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9
           WHEN sc.school_type REGEXP 'ابتدایی|دبستان|primary|elementary' THEN 8.5
           WHEN st.grade REGEXP 'ابتدایی|دبستان|primary|elementary' OR st.grade IN ('1','2','3','4','5','6','۱','۲','۳','۴','۵','۶') THEN 8.5
           ELSE 9 END)),0)
   FROM service_students ss JOIN services sv ON sv.id=ss.service_id
   JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
   JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
   WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1 AND sv.status='active' AND st.is_active=1");
  $grossQuery->execute([$agencyId,$driverId]);
  $annualGross=(float)$grossQuery->fetchColumn();
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

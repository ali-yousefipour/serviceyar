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
  $q=$pdo->prepare("SELECT st.id,st.agency_id,st.grade,st.school_id,st.academic_year,st.monthly_fee,sc.school_type
   FROM students st JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
   WHERE st.id=? AND st.agency_id=? AND st.is_active=1 FOR UPDATE");
  $q->execute([$studentId,$agencyId]);$student=$q->fetch(PDO::FETCH_ASSOC);
  if(!$student)return null;
  $services=$pdo->prepare("SELECT sv.id,sv.code FROM service_students ss JOIN services sv ON sv.id=ss.service_id
   WHERE ss.student_id=? AND sv.agency_id=? AND sv.is_active=1 AND sv.status<>'cancelled' ORDER BY sv.id LIMIT 2");
  $services->execute([$studentId,$agencyId]);$assigned=$services->fetchAll(PDO::FETCH_ASSOC);
  if(!$assigned)return null;
  if(count($assigned)>1)throw new AuthException(409,'دانش‌آموز بیش از یک سرویس فعال دارد.','DUPLICATE_SERVICE_ASSIGNMENT');
  $fee=(float)$student['monthly_fee'];
  // Legacy/imported draft assignments may not yet have a usable tariff.
  // Never issue a zero-value invoice or block the assignment; a later tariff
  // update will create the invoice idempotently.
  if(!is_finite($fee)||$fee<=0)return null;
  $type=(string)($student['school_type']??'');$grade=(string)($student['grade']??'');
  $primary=preg_match('/ابتدایی|دبستان|primary|elementary/ui',$type)
   ||(!preg_match('/متوسطه|دبیرستان|secondary|high.school/ui',$type)
   && (preg_match('/ابتدایی|دبستان|primary|elementary/ui',$grade)||in_array($grade,['1','2','3','4','5','6','۱','۲','۳','۴','۵','۶'],true)));
  $months=$primary?8.5:9;
  $amount=(int)round($fee*$months);
  $year=trim(str_replace('/','-',(string)($student['academic_year']?:AgencySchoolTariffs::YEAR)));
  $map=$pdo->prepare('SELECT invoice_id FROM student_service_debt_invoices WHERE agency_id=? AND student_id=? AND academic_year=? FOR UPDATE');
  $map->execute([$agencyId,$studentId,$year]);$existing=$map->fetchColumn();
  $title='شهریه سرویس مدارس '.$year.' · سرویس '.($assigned[0]['code']?:$assigned[0]['id']);
  if($existing){
   $pdo->prepare("UPDATE invoices SET title=?,amount=?,status=CASE WHEN status='cancelled' THEN 'open' ELSE status END
     WHERE id=? AND agency_id=? AND party_type='student' AND party_id=?")
    ->execute([$title,$amount,$existing,$agencyId,$studentId]);
   return ['invoiceId'=>(int)$existing,'amount'=>$amount,'months'=>$months,'created'=>false];
  }
  $pdo->prepare("INSERT INTO invoices(agency_id,title,party_type,party_id,amount,status,issue_date)
   VALUES(?,?,'student',?,?,'open',CURRENT_DATE())")->execute([$agencyId,$title,$studentId,$amount]);
  $id=(int)$pdo->lastInsertId();
  $pdo->prepare('INSERT INTO student_service_debt_invoices(agency_id,student_id,academic_year,invoice_id,service_id) VALUES(?,?,?,?,?)')
   ->execute([$agencyId,$studentId,$year,$id,(int)$assigned[0]['id']]);
  return ['invoiceId'=>$id,'amount'=>$amount,'months'=>$months,'created'=>true];
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
  return ['invoices'=>$invoices,'payments'=>$payments,'totals'=>[
   'invoiced'=>$total,'received'=>$received,'balance'=>max(0,$total-$received),'credit'=>max(0,$received-$total)]];
 }

 public static function driverAccount(int $uid,int $agencyId,int $driverId): array {
  Authorization::requirePermission($uid,'drivers.view');Authorization::requireAgencyAccess($uid,$agencyId);
  $pdo=Connection::get();
  $q=$pdo->prepare("SELECT sv.id,sv.code,sv.monthly_fee FROM services sv WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1 AND sv.status<>'cancelled'");
  $q->execute([$agencyId,$driverId]);$services=$q->fetchAll(PDO::FETCH_ASSOC);
  $monthly=(float)array_sum(array_column($services,'monthly_fee'));
  // Full academic-year driver entitlement is based on each student's driver
  // tariff and actual school duration, with agency deductions applied once.
  $grossQuery=$pdo->prepare("SELECT
   COALESCE(SUM(COALESCE(st.driver_base_monthly,st.monthly_fee) *
     (CASE WHEN sc.school_type REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9
           WHEN sc.school_type REGEXP 'ابتدایی|دبستان|primary|elementary' THEN 8.5
           WHEN st.grade REGEXP 'ابتدایی|دبستان|primary|elementary' OR st.grade IN ('1','2','3','4','5','6','۱','۲','۳','۴','۵','۶') THEN 8.5
           ELSE 9 END)),0)
   FROM service_students ss JOIN services sv ON sv.id=ss.service_id
   JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
   JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
   WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1 AND sv.status<>'cancelled'");
  $grossQuery->execute([$agencyId,$driverId]);
  $annualGross=(float)$grossQuery->fetchColumn();
  $deductions=OperationsService::driverDeductions($annualGross,$agencyId);
  $annualNet=(float)$deductions['net_earned'];
  $p=$pdo->prepare('SELECT id,amount,payment_date,reference_no FROM driver_payments WHERE agency_id=? AND driver_id=? ORDER BY payment_date DESC,id DESC');
  $p->execute([$agencyId,$driverId]);$paid=$p->fetchAll(PDO::FETCH_ASSOC);
  $l=$pdo->prepare("SELECT id,amount,entry_date,direction FROM ledger_entries WHERE agency_id=? AND party_type='driver' AND party_id=? AND is_active=1");
  $l->execute([$agencyId,$driverId]);$ledger=$l->fetchAll(PDO::FETCH_ASSOC);
  $paidTotal=(float)array_sum(array_column($paid,'amount'));
  foreach($ledger as $entry)$paidTotal+=($entry['direction']==='payment'?1:-1)*(float)$entry['amount'];
  return ['services'=>$services,'payments'=>$paid,'monthlyEntitlement'=>$monthly,
   'estimatedAnnualEntitlement'=>$annualNet,'grossAnnualEntitlement'=>$annualGross,'paidTotal'=>$paidTotal,
   'remaining'=>max(0,$annualNet-$paidTotal),'overpaid'=>max(0,$paidTotal-$annualNet),
   'note'=>'برآورد سالانه بر اساس نرخ دانش‌آموزان تخصیص‌یافته و کسورات شرکت است؛ پرداخت‌های ثبت‌شده به راننده از آن کسر می‌شوند. اسناد نهایی حقوق مستقل هستند.'];
 }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;

/** Student account: non-cancelled invoices are charges; actual cash remains cash
 * even if a charge is cancelled. Refunds are negative ledger receipts. */
final class StudentBalance {
 public static function yearSql(string $column): string {
  $expr="TRIM($column)";
  foreach(['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','/'=>'-',' '=>'','–'=>'-','—'=>'-'] as $from=>$to)
   $expr="REPLACE($expr,'$from','$to')";
  return "CONVERT($expr USING utf8mb4) COLLATE utf8mb4_unicode_ci";
 }
 public static function invoicesSql(): string {
  $base="(SELECT COALESCE(SUM(i.amount),0) FROM invoices i WHERE i.party_type='student' AND i.party_id=students.id AND i.agency_id=students.agency_id AND i.status<>'cancelled')";
  if(VehicleClassSchema::exists())$base="($base + (SELECT COALESCE(SUM(adj.amount_irr),0) FROM vehicle_class_debt_adjustments adj WHERE adj.student_id=students.id AND adj.agency_id=students.agency_id AND adj.effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE)))";
  if(StudentDiscountSchema::exists())$base="($base - (SELECT COALESCE(SUM(CASE WHEN sd.event_type='create' THEN sd.student_discount_irr ELSE -sd.student_discount_irr END),0) FROM student_discount_financial_effects sd WHERE sd.student_id=students.id AND sd.agency_id=students.agency_id AND sd.effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE)))";
  return $base;
 }
 public static function receiptsSql(): string {
  return "((SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id WHERE i.party_type='student' AND i.party_id=students.id AND p.agency_id=students.agency_id) + (SELECT COALESCE(SUM(CASE WHEN le.direction='receipt' THEN le.amount ELSE -le.amount END),0) FROM ledger_entries le WHERE le.agency_id=students.agency_id AND le.party_type='student' AND le.party_id=students.id AND le.is_active=1))";
 }
 /** Explicit contract/legacy invoice year; unknown documents remain unallocated. */
 public static function invoiceYearSql(string $alias='i'): string {
  if(!preg_match('/^[a-z][a-z0-9_]*$/D',$alias))throw new \InvalidArgumentException('Invalid SQL alias');
  return "COALESCE((SELECT ".self::yearSql('m.academic_year')." FROM student_contract_invoices m WHERE m.invoice_id=$alias.id AND m.agency_id=$alias.agency_id AND m.student_id=$alias.party_id),(SELECT ".self::yearSql('l.academic_year')." FROM student_service_debt_invoices l WHERE l.invoice_id=$alias.id AND l.agency_id=$alias.agency_id AND l.student_id=$alias.party_id))";
 }
 public static function annualBreakdown(PDO $pdo,int $agency,int $student): array {
  $year=self::invoiceYearSql('i');
  $q=$pdo->prepare("SELECT $year academic_year, SUM(CASE WHEN i.status<>'cancelled' THEN i.amount ELSE 0 END) invoiced, SUM((SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.invoice_id=i.id AND p.agency_id=i.agency_id)) received FROM invoices i WHERE i.agency_id=? AND i.party_type='student' AND i.party_id=? GROUP BY academic_year");
  $q->execute([$agency,$student]);$groups=[];
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
   try{$key=ContractBilling::year((string)$row['academic_year']);}catch(\ServiceYar\Auth\AuthException $e){$key='unallocated';}
   if(!isset($groups[$key]))$groups[$key]=['invoiced'=>0.0,'received'=>0.0];
   $groups[$key]['invoiced']+=(float)$row['invoiced'];$groups[$key]['received']+=(float)$row['received'];
  }
  $q=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='receipt' THEN amount ELSE -amount END),0) FROM ledger_entries WHERE agency_id=? AND party_type='student' AND party_id=? AND is_active=1");
  $q->execute([$agency,$student]);$ledger=(float)$q->fetchColumn();
  if($ledger!==0.0){$groups['unallocated']??=['invoiced'=>0.0,'received'=>0.0];$groups['unallocated']['received']+=$ledger;}
  // Class-change adjustments are signed charges, never cash receipts.
  // A negative adjustment reduces outstanding debt without mutating a
  // previously issued invoice or the student's payment history.
  if(VehicleClassSchema::exists($pdo)){
  $adjust=$pdo->prepare("SELECT academic_year,SUM(amount_irr) amount FROM vehicle_class_debt_adjustments WHERE agency_id=? AND student_id=? AND effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE) GROUP BY academic_year");
  $adjust->execute([$agency,$student]);
  foreach($adjust->fetchAll(PDO::FETCH_ASSOC) as $row){
   $key=ContractBilling::year((string)$row['academic_year']);
   $groups[$key]??=['invoiced'=>0.0,'received'=>0.0];
   $groups[$key]['invoiced']+=(float)$row['amount'];
  }
  }
  if(StudentDiscountSchema::exists($pdo)){
   $q=$pdo->prepare("SELECT academic_year,SUM(CASE WHEN event_type='create' THEN student_discount_irr ELSE -student_discount_irr END) amount FROM student_discount_financial_effects
       WHERE agency_id=? AND student_id=? AND effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE)
       GROUP BY academic_year");
   $q->execute([$agency,$student]);
   foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
    $key=ContractBilling::year((string)$row['academic_year']);
    $groups[$key]??=['invoiced'=>0.0,'received'=>0.0];
    $groups[$key]['invoiced']-=(float)$row['amount'];
   }
  }
  ksort($groups);
  foreach($groups as $key=>$row)$groups[$key]=self::totals($row['invoiced'],$row['received']);
  return $groups;
 }
 public static function totals(float $invoiced,float $received): array {
  $net=round($invoiced-$received,2);
  return ['invoiced'=>$invoiced,'received'=>$received,'balance'=>max(0,$net),'credit'=>max(0,-$net),'net'=>$net];
 }
 /** Allocate cumulative net receipts oldest first; refund excess remains account debt. */
 public static function allocate(array &$invoices,float $received): void {
  $indices=array_keys($invoices);
  usort($indices,static fn($a,$b)=>strcmp((string)($invoices[$a]['issue_date']??''),(string)($invoices[$b]['issue_date']??'')) ?: ((int)$invoices[$a]['id']<=>(int)$invoices[$b]['id']));
  $available=max(0,$received);
  foreach($indices as $index){
   // Credit adjustments may exceed an issued invoice. Never allocate a
   // negative invoice amount as a payment or increase the cash available.
   $amount=max(0.0,(float)$invoices[$index]['amount']);$allocated=min($amount,$available);
   $available=round($available-$allocated,2);
   $invoices[$index]['paidAmount']=$allocated;
   $invoices[$index]['remainingAmount']=max(0,round($amount-$allocated,2));
   $invoices[$index]['displayStatus']=$allocated >= $amount?'paid':($allocated>0?'partial':'open');
  }
 }
}

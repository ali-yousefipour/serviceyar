<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;

/** Contract is the authority for one student-year tuition; existing receipts are untouched. */
final class ContractBilling {
 public static function year(string $raw): string {
  $year=strtr(trim($raw),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','/'=>'-']);
  $year=preg_replace('/\s+/u','',strtr($year,['–'=>'-','—'=>'-','−'=>'-']))??$year;
  if(preg_match('/^(1[34]\d{2})-(\d{2})$/D',$year,$short))$year=$short[1].'-'.substr($short[1],0,2).$short[2];
  if(!preg_match('/^(1[34]\d{2})-(1[34]\d{2})$/D',$year,$m)||(int)$m[2] !== (int)$m[1]+1)
   throw new AuthException(422,'سال تحصیلی دانش‌آموز معتبر نیست.','INVALID_CONTRACT_YEAR');
  return $year;
 }
 private static function jalaliToGregorian(int $jy,int $jm,int $jd): string
    {
        // Integer arithmetic conversion (Jalali 33-year cycle).
        $jy+=1595;
        $days=-355668+365*$jy+intdiv($jy,33)*8+intdiv(($jy%33)+3,4)
             +$jd+($jm<7?($jm-1)*31:($jm-7)*30+186);
        $gy=400*intdiv($days,146097);$days%=146097;
        if($days>36524){$gy+=100*intdiv(--$days,36524);$days%=36524;if($days>=365)$days++;}
        $gy+=4*intdiv($days,1461);$days%=1461;
        if($days>365){$gy+=intdiv($days-1,365);$days=($days-1)%365;}
        $gd=$days+1;$leap=($gy%4===0&&($gy%100!==0||$gy%400===0));
        foreach([31,$leap?29:28,31,30,31,30,31,31,30,31,30,31] as $m=>$length){
            if($gd<=$length)return sprintf('%04d-%02d-%02d',$gy,$m+1,$gd);
            $gd-=$length;
        }
        throw new \LogicException('Invalid Jalali conversion');
    }
 public static function defaults(string $year,bool $primary): array {
  $y=(int)substr(self::year($year),0,4);
  return ['startDate'=>self::jalaliToGregorian($y,7,1),'endDate'=>self::jalaliToGregorian($y+1,3,$primary?15:31)];
 }
 public static function quote(string $year,string $start,string $end,int $monthly): array {
  $y=(int)substr(self::year($year),0,4);
  if($monthly<0||!OperationsService::validDate($start)||!OperationsService::validDate($end)||$end<$start||
   $start<self::jalaliToGregorian($y,7,1)||$end>=self::jalaliToGregorian($y+1,7,1))
   throw new AuthException(422,'تاریخ قرارداد باید در همان سال تحصیلی و به ترتیب صحیح باشد.','INVALID_CONTRACT_DATES');
  $days=0;
  for($i=0;$i<12;$i++){
   $offset=6+$i;$jy=$y+intdiv($offset,12);$jm=$offset%12+1;
   $from=self::jalaliToGregorian($jy,$jm,1);
   $next=self::jalaliToGregorian($jm===12?$jy+1:$jy,$jm===12?1:$jm+1,1);
   $to=(new \DateTimeImmutable($next))->modify('-1 day')->format('Y-m-d');
   $lo=max($start,$from);$hi=min($end,$to);
   if($lo>$hi)continue;
   $days+=($lo===$from&&$hi===$to)?30:min(30,(int)(new \DateTimeImmutable($lo))->diff(new \DateTimeImmutable($hi))->days+1);
  }
  return ['days'=>$days,'months'=>$days/30,'amount'=>(int)round($monthly*$days/30),'startDate'=>$start,'endDate'=>$end];
 }
 public static function lockStudent(PDO $pdo,int $aid,int $student): array {
  $q=$pdo->prepare('SELECT id,academic_year,monthly_fee FROM students WHERE id=? AND agency_id=? AND is_active=1 FOR UPDATE');
  $q->execute([$student,$aid]);$row=$q->fetch(PDO::FETCH_ASSOC);
  if(!$row)throw new AuthException(404,'دانش‌آموز فعال یافت نشد.','STUDENT_NOT_FOUND');
  return $row;
 }
 /** Do not silently combine old manual invoices, installments or contracts with a new tuition. */
 public static function existing(PDO $pdo,int $aid,int $student,string $year,string $start,string $end,int $amount): ?array {
  $q=$pdo->prepare('SELECT c.id,c.contract_no,c.title,c.start_date,c.end_date,c.amount,m.invoice_id FROM student_contract_invoices m JOIN contracts c ON c.id=m.contract_id AND c.agency_id=m.agency_id WHERE m.agency_id=? AND m.student_id=? AND m.academic_year=? FOR UPDATE');
  $q->execute([$aid,$student,$year]);$old=$q->fetch(PDO::FETCH_ASSOC);
  if($old){
   if($old['start_date']===$start&&$old['end_date']===$end&&(int)$old['amount']===$amount)
    return ['id'=>(int)$old['id'],'contractNo'=>$old['contract_no'],'title'=>$old['title'],'amount'=>$amount,'invoiceId'=>(int)$old['invoice_id'],'alreadyIssued'=>true];
   throw new AuthException(409,'برای این سال قرارداد و فاکتور صادر شده است؛ اصلاح مالی مستقل لازم است.','CONTRACT_ALREADY_BILLED');
  }
  $q=$pdo->prepare("SELECT id FROM contracts WHERE agency_id=? AND party_type='student' AND party_id=? AND status='active' AND start_date<=? AND end_date>=? LIMIT 1 FOR UPDATE");
  $q->execute([$aid,$student,$end,$start]);
  if($q->fetchColumn())throw new AuthException(409,'قرارداد قبلی هم‌پوشان وجود دارد؛ ابتدا سوابق مالی آن بررسی شود.','LEGACY_CONTRACT_EXISTS');
  return null;
 }
 public static function invoice(PDO $pdo,int $aid,int $student,string $year,int $contract,string $number,int $amount,string $end): int {
  $q=$pdo->prepare('SELECT invoice_id FROM student_service_debt_invoices WHERE agency_id=? AND student_id=? AND academic_year=? FOR UPDATE');
  $q->execute([$aid,$student,$year]);$legacy=(int)$q->fetchColumn();
  // Unknown invoices could be installments or manual tuition. Require explicit reconciliation.
  $q=$pdo->prepare("SELECT i.id FROM invoices i WHERE i.agency_id=? AND i.party_type='student' AND i.party_id=? AND i.status<>'cancelled' AND i.id<>? AND NOT EXISTS(SELECT 1 FROM student_contract_invoices m WHERE m.invoice_id=i.id AND m.academic_year<>?) AND NOT EXISTS(SELECT 1 FROM student_service_debt_invoices m WHERE m.invoice_id=i.id AND m.academic_year<>?) LIMIT 1 FOR UPDATE");
  $q->execute([$aid,$student,$legacy,$year,$year]);
  if($q->fetchColumn())throw new AuthException(409,'فاکتور یا اقساط قبلی وجود دارد؛ ابتدا برای جلوگیری از بدهی تکراری تطبیق مالی انجام دهید.','EXISTING_STUDENT_INVOICES');
  $title='شهریه قرارداد '.$number.' · '.$year;
  if($legacy){
   $q=$pdo->prepare("SELECT id,status FROM invoices WHERE id=? AND agency_id=? AND party_type='student' AND party_id=? FOR UPDATE");
   $q->execute([$legacy,$aid,$student]);$invoice=$q->fetch(PDO::FETCH_ASSOC);
   if(!$invoice||$invoice['status']==='cancelled')throw new AuthException(409,'فاکتور قبلی نیازمند بررسی است.','LEGACY_INVOICE_CONFLICT');
   $pdo->prepare("UPDATE invoices SET title=?,amount=?,due_date=?,status=CASE WHEN (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.invoice_id=invoices.id AND p.agency_id=invoices.agency_id)>=? THEN 'paid' ELSE 'open' END WHERE id=? AND agency_id=?")->execute([$title,$amount,$end,$amount,$legacy,$aid]);
   $id=$legacy;
  }else{
   $pdo->prepare("INSERT INTO invoices(agency_id,title,party_type,party_id,amount,status,issue_date,due_date) VALUES(?,?,'student',?,?,'open',CURRENT_DATE(),?)")->execute([$aid,$title,$student,$amount,$end]);
   $id=(int)$pdo->lastInsertId();
  }
  $pdo->prepare('INSERT INTO student_contract_invoices(agency_id,student_id,academic_year,contract_id,invoice_id) VALUES(?,?,?,?,?)')->execute([$aid,$student,$year,$contract,$id]);
  return $id;
 }
 public static function assertInvoiceEditable(PDO $pdo,int $aid,int $id): void {
  $q=$pdo->prepare('SELECT contract_id FROM student_contract_invoices WHERE agency_id=? AND invoice_id=? FOR UPDATE');
  $q->execute([$aid,$id]);if($q->fetchColumn())throw new AuthException(409,'فاکتور متصل به قرارداد قطعی است و از این مسیر قابل تغییر نیست.','CONTRACT_INVOICE_LOCKED');
 }
}

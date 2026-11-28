<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

/** Read-only reconciliation: never infer consent to reprice an issued contract. */
final class FinancialReconciliation {
 public static function report(int $uid,int $aid,array $filter): array {
  Authorization::requireAgencyAccess($uid,$aid);
  Authorization::requirePermission($uid,'finance.view');
  Authorization::requirePermission($uid,'students.view');
  $year=ContractBilling::year((string)($filter['academicYear']??''));
  $pdo=Connection::get();
  $where='s.agency_id=? AND '.StudentBalance::yearSql('s.academic_year').'=?';
  $args=[$aid,$year];
  if(!empty($filter['schoolId'])){
   $id=filter_var($filter['schoolId'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if(!$id)throw new AuthException(422,'مدرسه نامعتبر است.','VALIDATION_ERROR');
   $where.=' AND s.school_id=?';$args[]=$id;
  }
  $studentFilter=$filter['student']??'';
  if(!is_string($studentFilter)&&!is_numeric($studentFilter))
   throw new AuthException(422,'عبارت جستجوی دانش‌آموز معتبر نیست.','VALIDATION_ERROR');
  $studentFilter=trim((string)$studentFilter);
  if(strlen($studentFilter)>240)
   throw new AuthException(422,'عبارت جستجو بیش از حد طولانی است.','VALIDATION_ERROR');
  if($studentFilter!==''){
   // Treat SQL LIKE metacharacters as literal search text.
   $term=str_replace(['!','%','_'],['!!','!%','!_'],$studentFilter);
   $where.=" AND CONCAT_WS(' ',s.first_name,s.last_name) LIKE ? ESCAPE '!'";$args[]='%'.$term.'%';
  }
  $q=$pdo->prepare("SELECT s.id,s.first_name,s.last_name,s.monthly_fee,s.grade,sc.name school,sc.school_type,sc.education_levels FROM students s LEFT JOIN schools sc ON sc.id=s.school_id AND sc.agency_id=s.agency_id WHERE $where ORDER BY sc.name,s.id");
  $q->execute($args);$students=$q->fetchAll(PDO::FETCH_ASSOC);
  // Fetch documents in bounded batches rather than issuing two queries per student.
  // Every query is tenant-scoped and still retains the original document ordering.
  $bounds=ContractBilling::defaults($year,false);$items=[];
  foreach(array_chunk($students,300) as $studentBatch){
   // Do not retain all agency invoices/contracts until the entire report ends.
   $contractsByStudent=[];$invoicesByStudent=[];$adjustments=[];$periods=[];
   $studentDiscountContract=[];$studentDiscountInvoice=[];$studentDiscountStudent=[];
   $ids=array_map('intval',array_column($studentBatch,'id'));
   $marks=implode(',',array_fill(0,count($ids),'?'));
   if(VehicleClassSchema::exists($pdo)){
    $q=$pdo->prepare("SELECT student_id,SUM(amount_irr) delta FROM vehicle_class_debt_adjustments
      WHERE agency_id=? AND student_id IN ($marks) AND ".StudentBalance::yearSql('academic_year')."=?
      AND effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE) GROUP BY student_id");
    $q->execute([$aid,...$ids,$year]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$adjustments[(int)$r['student_id']]=(int)$r['delta'];
    $q=$pdo->prepare("SELECT p.* FROM vehicle_class_rate_periods p
      JOIN vehicle_class_changes ch ON ch.id=p.change_id AND ch.agency_id=p.agency_id AND ch.update_student_debt=1
      WHERE p.agency_id=? AND p.subject_type='student_debt' AND p.subject_id IN ($marks)
      AND p.effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE) ORDER BY p.effective_date,p.id");
    $q->execute([$aid,...$ids]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$periods[(int)$r['subject_id']][]=$r;
   }
   if(StudentDiscountSchema::exists($pdo)){
    $dq=$pdo->prepare("SELECT student_id,contract_id,invoice_id,
        SUM(CASE WHEN event_type='create' THEN student_discount_irr ELSE -student_discount_irr END) discount_irr
        FROM student_discount_financial_effects
        WHERE agency_id=? AND student_id IN ($marks)
          AND ".StudentBalance::yearSql('academic_year')."=?
          AND effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE)
        GROUP BY student_id,contract_id,invoice_id");
    $dq->execute([$aid,...$ids,$year]);
    foreach($dq->fetchAll(PDO::FETCH_ASSOC) as $entry){
     $delta=(int)$entry['discount_irr'];
     $cid=(int)$entry['contract_id'];$iid=(int)$entry['invoice_id'];$sid=(int)$entry['student_id'];
     $studentDiscountContract[$cid]=($studentDiscountContract[$cid]??0)+$delta;
     $studentDiscountInvoice[$iid]=($studentDiscountInvoice[$iid]??0)+$delta;
     $studentDiscountStudent[$sid]=($studentDiscountStudent[$sid]??0)+$delta;
    }
   }
   $c=$pdo->prepare("SELECT c.party_id,c.id,c.amount,c.start_date,c.end_date,c.status,m.invoice_id,m.academic_year FROM contracts c LEFT JOIN student_contract_invoices m ON m.contract_id=c.id AND m.agency_id=c.agency_id AND m.student_id=c.party_id WHERE c.agency_id=? AND c.party_type='student' AND c.party_id IN ($marks) AND c.status<>'cancelled' AND (c.start_date IS NULL OR c.end_date IS NULL OR (c.start_date<=? AND c.end_date>=?)) ORDER BY c.id");
   $c->execute([$aid,...$ids,$bounds['endDate'],$bounds['startDate']]);
   foreach($c->fetchAll(PDO::FETCH_ASSOC) as $contract){
    $sid=(int)$contract['party_id'];unset($contract['party_id']);
    $contract['class_adjustment_amount']=0;
    try{if(ContractBilling::year((string)($contract['academic_year']??''))===$year)$contract['class_adjustment_amount']=$adjustments[$sid]??0;}catch(AuthException $e){}
    $contract['student_discount_amount']=$studentDiscountContract[(int)$contract['id']]??0;
    $contract['effective_amount']=(int)$contract['amount']+$contract['class_adjustment_amount']-$contract['student_discount_amount'];
    $contractsByStudent[$sid][]=$contract;
   }
   $i=$pdo->prepare("SELECT i.party_id,i.id,i.amount,i.status,i.title,i.issue_date,m.academic_year contract_year,l.academic_year legacy_year FROM invoices i LEFT JOIN student_contract_invoices m ON m.invoice_id=i.id AND m.agency_id=i.agency_id AND m.student_id=i.party_id LEFT JOIN student_service_debt_invoices l ON l.invoice_id=i.id AND l.agency_id=i.agency_id AND l.student_id=i.party_id WHERE i.agency_id=? AND i.party_type='student' AND i.party_id IN ($marks) ORDER BY i.id");
   $i->execute([$aid,...$ids]);
   foreach($i->fetchAll(PDO::FETCH_ASSOC) as $invoice){
    $sid=(int)$invoice['party_id'];unset($invoice['party_id']);
    $invoice['class_adjustment_amount']=0;
    try{if(ContractBilling::year((string)($invoice['contract_year']??''))===$year)$invoice['class_adjustment_amount']=$adjustments[$sid]??0;}catch(AuthException $e){}
    $invoice['student_discount_amount']=$studentDiscountInvoice[(int)$invoice['id']]??0;
    $invoice['effective_amount']=(int)$invoice['amount']+$invoice['class_adjustment_amount']-$invoice['student_discount_amount'];
    $invoicesByStudent[$sid][]=$invoice;
   }
   foreach($studentBatch as $st){
   $flags=[];$expected=null;$months=null;
   $sid=(int)$st['id'];

   try{$months=ServiceFinancialBasis::monthsOfStudent($st);$expected=VehicleClassEffectiveDates::annualEntitlement($year,$months===8.5,(int)$st['monthly_fee'],$periods[$sid]??[]);}catch(AuthException $e){$flags[]='مقطع یا تاریخچه نرخ نامعتبر';}
   if($expected!==null)$expected-=$studentDiscountStudent[$sid]??0;
   $contracts=$contractsByStudent[$sid]??[];
   $invoices=$invoicesByStudent[$sid]??[];
   $valid=[];$unknown=[];
   foreach($invoices as $inv){
    if($inv['status']==='cancelled')continue;
    $linked=$inv['contract_year']??$inv['legacy_year'];
    if($linked===null){$unknown[]=$inv;continue;}
    try{if(ContractBilling::year($linked)===$year)$valid[]=$inv;}catch(AuthException $e){$unknown[]=$inv;}
   }
   if($unknown)$flags[]='فاکتور فاقد تخصیص معتبر سال یا قرارداد';
   if(count($contracts)>1)$flags[]='قراردادهای متعدد در بازه';
   if(count($valid)>1)$flags[]='فاکتورهای متعدد؛ بررسی اقساط';
   if(!$contracts&&($valid||$unknown))$flags[]='فاکتور بدون قرارداد';
   $byId=array_column($valid,null,'id');
   foreach($contracts as $contract){
    if(!isset($byId[$contract['invoice_id']]))$flags[]='قرارداد فاقد فاکتور معتبر';
    elseif((int)$byId[$contract['invoice_id']]['effective_amount']!==(int)$contract['effective_amount'])$flags[]='اختلاف مبلغ قرارداد و فاکتور';
    if($months!==null){
     $defaults=ContractBilling::defaults($year,$months===8.5);
     if($contract['start_date']===$defaults['startDate']&&$contract['end_date']!==$defaults['endDate'])$flags[]='مدت متفاوت از دوره کامل؛ بررسی توافق';
     if($expected!==(int)$contract['effective_amount'])$flags[]='اختلاف تعرفه و مبلغ قرارداد؛ بررسی توافق';
    }
   }
   if(!$flags)continue;
   $total=(int)array_sum(array_column(array_merge($valid,$unknown),'effective_amount'));
   $items[]=['student_id'=>(int)$st['id'],'student'=>trim($st['first_name'].' '.$st['last_name']),'school'=>$st['school'],'monthly_fee'=>(int)$st['monthly_fee'],'annual_months'=>$months,'expected'=>$expected,'invoice_total'=>$total,'difference'=>$expected===null?null:$expected-$total,'contracts'=>$contracts,'invoices'=>array_merge($valid,$unknown),'issues'=>array_values(array_unique($flags))];
   }
  }
  $schools=$pdo->prepare("SELECT id,name FROM schools WHERE agency_id=? ORDER BY name,id");
  $schools->execute([$aid]);
  return ['academicYear'=>$year,'scanned'=>count($students),'items'=>$items,'count'=>count($items),'schools'=>$schools->fetchAll(PDO::FETCH_ASSOC),'readOnly'=>true];
 }
 public static function linkBatch(int $uid,int $aid,array $body): array {
  Authorization::requireAgencyAccess($uid,$aid);Authorization::requirePermission($uid,'finance.manage');Authorization::requirePermission($uid,'students.view');
  $year=ContractBilling::year((string)($body['academicYear']??''));
  $items=$body['items']??null;
  if(($body['confirmation']??'')!=='LINK_EXISTING_DOCUMENTS'||!is_array($items)||!$items||count($items)>100)throw new AuthException(422,'تأیید اتصال و انتخاب حداکثر ۱۰۰ پرونده لازم است.','VALIDATION_ERROR');
  foreach($items as $item){
   if(!is_array($item))throw new AuthException(422,'پرونده نامعتبر است.','VALIDATION_ERROR');
   foreach(['studentId','contractId','invoiceId','amount'] as $k)if(!isset($item[$k])||!is_scalar($item[$k])||!ctype_digit((string)$item[$k])||(int)$item[$k]<=0)throw new AuthException(422,'اطلاعات پیش‌نمایش نامعتبر است.','VALIDATION_ERROR');
  }
  usort($items,static fn($a,$b)=>(int)$a['studentId']<=>(int)$b['studentId']);
  return OperationsService::transaction(function(PDO $p)use($uid,$aid,$year,$items){
   $linked=0;$existing=0;
   foreach($items as $item){
    $sid=(int)$item['studentId'];$cid=(int)$item['contractId'];$iid=(int)$item['invoiceId'];$amount=(int)$item['amount'];
    $st=ContractBilling::lockStudent($p,$aid,$sid);
    if(ContractBilling::year((string)$st['academic_year'])!==$year)throw new AuthException(409,'سال پرونده تغییر کرده است.','RECONCILIATION_CHANGED');
    $q=$p->prepare("SELECT amount,start_date,end_date,status FROM contracts WHERE id=? AND agency_id=? AND party_type='student' AND party_id=? FOR UPDATE");$q->execute([$cid,$aid,$sid]);$c=$q->fetch(PDO::FETCH_ASSOC);
    $q=$p->prepare("SELECT amount,status FROM invoices WHERE id=? AND agency_id=? AND party_type='student' AND party_id=? FOR UPDATE");$q->execute([$iid,$aid,$sid]);$inv=$q->fetch(PDO::FETCH_ASSOC);
    if(!$c||!$inv||$c['status']!=='active'||$inv['status']==='cancelled'||(float)$c['amount']!==(float)$amount||(float)$inv['amount']!==(float)$amount)throw new AuthException(409,'اسناد تغییر کرده‌اند یا هم‌مبلغ نیستند.','RECONCILIATION_CHANGED');
    ContractBilling::quote($year,(string)$c['start_date'],(string)$c['end_date'],0);
    $q=$p->prepare('SELECT * FROM student_contract_invoices WHERE contract_id=? OR invoice_id=? OR (agency_id=? AND student_id=? AND academic_year=?) FOR UPDATE');$q->execute([$cid,$iid,$aid,$sid,$year]);$m=$q->fetchAll(PDO::FETCH_ASSOC);
    if($m){
     if(count($m)===1&&(int)$m[0]['agency_id']===$aid&&(int)$m[0]['student_id']===$sid&&(int)$m[0]['contract_id']===$cid&&(int)$m[0]['invoice_id']===$iid&&$m[0]['academic_year']===$year){++$existing;continue;}
     throw new AuthException(409,'ارتباط مالی دیگری وجود دارد.','RECONCILIATION_CONFLICT');
    }
    $q=$p->prepare('SELECT * FROM student_service_debt_invoices WHERE invoice_id=? FOR UPDATE');$q->execute([$iid]);$legacy=$q->fetch(PDO::FETCH_ASSOC);
    if($legacy&&((int)$legacy['agency_id']!==$aid||(int)$legacy['student_id']!==$sid||ContractBilling::year($legacy['academic_year'])!==$year))throw new AuthException(409,'فاکتور متعلق به سال دیگری است.','RECONCILIATION_CONFLICT');
    $q=$p->prepare("SELECT id FROM contracts WHERE agency_id=? AND party_type='student' AND party_id=? AND status='active' AND id<>? AND start_date<=? AND end_date>=? LIMIT 1 FOR UPDATE");$q->execute([$aid,$sid,$cid,$c['end_date'],$c['start_date']]);
    if($q->fetchColumn())throw new AuthException(409,'قرارداد هم‌پوشان وجود دارد.','RECONCILIATION_CONFLICT');
    $q=$p->prepare("SELECT id FROM invoices WHERE agency_id=? AND party_type='student' AND party_id=? AND status<>'cancelled' AND id<>? LIMIT 1 FOR UPDATE");$q->execute([$aid,$sid,$iid]);
    if($q->fetchColumn())throw new AuthException(409,'فاکتور دیگری وجود دارد؛ بررسی دستی لازم است.','RECONCILIATION_CONFLICT');
    $p->prepare('INSERT INTO student_contract_invoices(agency_id,student_id,academic_year,contract_id,invoice_id) VALUES(?,?,?,?,?)')->execute([$aid,$sid,$year,$cid,$iid]);
    $p->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id,metadata) VALUES(?,?,?,?,?,?)')->execute([$aid,$uid,'financial_reconciliation_link','student',$sid,json_encode(['academicYear'=>$year,'contractId'=>$cid,'invoiceId'=>$iid,'amount'=>$amount],JSON_THROW_ON_ERROR)]);
    ++$linked;
   }
   return ['linked'=>$linked,'alreadyLinked'=>$existing];
  });
 }

}

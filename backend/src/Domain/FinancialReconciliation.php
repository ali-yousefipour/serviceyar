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
  if(trim((string)($filter['student']??''))!==''){
   $where.=" AND CONCAT_WS(' ',s.first_name,s.last_name) LIKE ?";$args[]='%'.trim((string)$filter['student']).'%';
  }
  $q=$pdo->prepare("SELECT s.id,s.first_name,s.last_name,s.monthly_fee,s.grade,sc.name school,sc.school_type,sc.education_levels FROM students s LEFT JOIN schools sc ON sc.id=s.school_id AND sc.agency_id=s.agency_id WHERE $where ORDER BY sc.name,s.id");
  $q->execute($args);$students=$q->fetchAll(PDO::FETCH_ASSOC);
  $c=$pdo->prepare("SELECT c.id,c.amount,c.start_date,c.end_date,c.status,m.invoice_id FROM contracts c LEFT JOIN student_contract_invoices m ON m.contract_id=c.id AND m.agency_id=c.agency_id AND m.student_id=c.party_id WHERE c.agency_id=? AND c.party_type='student' AND c.party_id=? AND c.status<>'cancelled' AND (c.start_date IS NULL OR c.end_date IS NULL OR (c.start_date<=? AND c.end_date>=?)) ORDER BY c.id");
  $i=$pdo->prepare("SELECT i.id,i.amount,i.status,i.title,i.issue_date,m.academic_year contract_year,l.academic_year legacy_year FROM invoices i LEFT JOIN student_contract_invoices m ON m.invoice_id=i.id AND m.agency_id=i.agency_id AND m.student_id=i.party_id LEFT JOIN student_service_debt_invoices l ON l.invoice_id=i.id AND l.agency_id=i.agency_id AND l.student_id=i.party_id WHERE i.agency_id=? AND i.party_type='student' AND i.party_id=? ORDER BY i.id");
  $bounds=ContractBilling::defaults($year,false);$items=[];
  foreach($students as $st){
   $flags=[];$expected=null;$months=null;
   try{$months=ServiceFinancialBasis::monthsOfStudent($st);$expected=(int)round((float)$st['monthly_fee']*$months);}catch(AuthException $e){$flags[]='مقطع نامشخص';}
   $c->execute([$aid,$st['id'],$bounds['endDate'],$bounds['startDate']]);$contracts=$c->fetchAll(PDO::FETCH_ASSOC);
   $i->execute([$aid,$st['id']]);$invoices=$i->fetchAll(PDO::FETCH_ASSOC);
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
    elseif((int)$byId[$contract['invoice_id']]['amount']!==(int)$contract['amount'])$flags[]='اختلاف مبلغ قرارداد و فاکتور';
    if($months!==null){
     $defaults=ContractBilling::defaults($year,$months===8.5);
     if($contract['start_date']===$defaults['startDate']&&$contract['end_date']!==$defaults['endDate'])$flags[]='مدت متفاوت از دوره کامل؛ بررسی توافق';
     if($expected!==(int)$contract['amount'])$flags[]='اختلاف تعرفه و مبلغ قرارداد؛ بررسی توافق';
    }
   }
   if(!$flags)continue;
   $total=(int)array_sum(array_column(array_merge($valid,$unknown),'amount'));
   $items[]=['student_id'=>(int)$st['id'],'student'=>trim($st['first_name'].' '.$st['last_name']),'school'=>$st['school'],'monthly_fee'=>(int)$st['monthly_fee'],'annual_months'=>$months,'expected'=>$expected,'invoice_total'=>$total,'difference'=>$expected===null?null:$expected-$total,'contracts'=>$contracts,'invoices'=>array_merge($valid,$unknown),'issues'=>array_values(array_unique($flags))];
  }
  return ['academicYear'=>$year,'scanned'=>count($students),'items'=>$items,'count'=>count($items),'readOnly'=>true];
 }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\AuthException;
final class InstallmentPlan {
 /** Bind approval to the exact student, issue date, and instalment amounts. */
 private static function approvalDigest(array $input,array $plan): string {
  $student=filter_var($input['studentId']??null,FILTER_VALIDATE_INT);
  $issue=$input['issueDate']??'';
  $data=['studentId'=>$student?:null,'issueDate'=>is_string($issue)?$issue:'',
    'total'=>$plan['total'],'advance'=>$plan['advance'],
    'remaining'=>$plan['remaining'],'items'=>$plan['items']];
  return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 }
 public static function preview(array $b):array {
  foreach(['total','advance'] as $key){$v=(string)($b[$key]??'');if(!preg_match('/^[0-9]{1,12}$/D',$v))throw new AuthException(422,'مبلغ را به ریال صحیح وارد کنید.','VALIDATION_ERROR');}
  $total=(int)$b['total'];$advance=(int)$b['advance'];$count=filter_var($b['count']??null,FILTER_VALIDATE_INT);
  if($total<1||$advance>$total||$count===false||$count<1||$count>24)throw new AuthException(422,'مبلغ، پیش‌پرداخت یا تعداد اقساط نامعتبر است.','VALIDATION_ERROR');
  $dates=$b['dates']??[];if(!is_array($dates)||count($dates)!==$count)throw new AuthException(422,'برای هر قسط یک سررسید تعیین کنید.','VALIDATION_ERROR');
  $remaining=$total-$advance;if($remaining<$count)throw new AuthException(422,'مانده باید حداقل به تعداد اقساط ریال باشد.','VALIDATION_ERROR');
  $base=intdiv($remaining,$count);$extra=$remaining%$count;$rows=[];$previous='';
  foreach($dates as $i=>$date){if(!is_string($date)||!OperationsService::validDate($date)||($previous!==''&&$date<=$previous))throw new AuthException(422,'سررسیدها باید معتبر و به ترتیب افزایشی باشند.','VALIDATION_ERROR');$rows[]=['number'=>$i+1,'dueDate'=>$date,'amount'=>$base+($i<$extra?1:0)];$previous=$date;}
  $plan=['total'=>$total,'advance'=>$advance,'remaining'=>$remaining,'items'=>$rows,'preview'=>true];
  $plan['approvalDigest']=self::approvalDigest($b,$plan);
  return $plan;
 }
 public static function create(int $uid,int $aid,array $b):array {
  \ServiceYar\Auth\Authorization::requirePermission($uid,'finance.manage');
  \ServiceYar\Auth\Authorization::requirePermission($uid,'students.view');
  \ServiceYar\Auth\Authorization::requireAgencyAccess($uid,$aid);
  $plan=self::preview($b);
  $accepted=$b['approvalDigest']??null;
  if(!is_string($accepted)||!preg_match('/^[a-f0-9]{64}$/D',$accepted))
   throw new AuthException(422,'برای ثبت اقساط ابتدا پیش‌نمایش همان اطلاعات را تهیه و تأیید کنید.','INSTALLMENT_PREVIEW_REQUIRED');
  if(!hash_equals($plan['approvalDigest'],$accepted))
   throw new AuthException(409,'اطلاعات اقساط نسبت به پیش‌نمایش تغییر کرده است؛ پیش‌نمایش جدید تهیه کنید.','INSTALLMENT_PREVIEW_CHANGED');
  $student=filter_var($b['studentId']??null,FILTER_VALIDATE_INT);$key=$b['requestKey']??'';$date=$b['issueDate']??'';
  if(!$student||!is_string($key)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)||!is_string($date)||!OperationsService::validDate($date)||$date>$plan['items'][0]['dueDate'])throw new AuthException(422,'دانش‌آموز، تاریخ صدور یا شناسه درخواست نامعتبر است.','VALIDATION_ERROR');
  $hash=hash('sha256',json_encode([$student,$date,$plan]));
  return OperationsService::transaction(function()use($uid,$aid,$student,$key,$hash,$date,$plan){
   $p=\ServiceYar\Database\Connection::get();
   $q=$p->prepare('SELECT id FROM students WHERE id=? AND agency_id=? AND is_active=1 FOR UPDATE');$q->execute([$student,$aid]);if(!$q->fetchColumn())throw new AuthException(404,'دانش‌آموز فعال در این شرکت پیدا نشد.','NOT_FOUND');
   $q=$p->prepare('INSERT INTO installment_plans(agency_id,student_id,request_key,payload_hash) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$q->execute([$aid,$student,$key,$hash]);
   $q=$p->prepare('SELECT id,payload_hash,result_json FROM installment_plans WHERE agency_id=? AND request_key=? FOR UPDATE');$q->execute([$aid,$key]);$row=$q->fetch();
   if(!hash_equals($row['payload_hash'],$hash))throw new AuthException(409,'این درخواست قبلاً با اطلاعات دیگری ثبت شده است.','REQUEST_CONFLICT');
   if($row['result_json']!==null)return json_decode($row['result_json'],true,512,JSON_THROW_ON_ERROR);
   $items=$plan['items'];if($plan['advance']>0)array_unshift($items,['number'=>0,'amount'=>$plan['advance'],'dueDate'=>$date]);$ids=[];
   foreach($items as $item){$invoice=DomainService::save($uid,$aid,'invoices',['title'=>'برنامه '.$row['id'].' · '.($item['number']===0?'پیش‌پرداخت':'قسط '.$item['number']),'partyType'=>'student','partyId'=>$student,'amount'=>$item['amount'],'issueDate'=>$date,'dueDate'=>$item['dueDate']]);$ids[]=$invoice['id'];}
   $result=['planId'=>(int)$row['id'],'invoiceIds'=>$ids,'total'=>$plan['total'],'created'=>true];$p->prepare('UPDATE installment_plans SET result_json=? WHERE id=?')->execute([json_encode($result),$row['id']]);OperationsService::audit($uid,$aid,'installment_plan_created','student',$student);return $result;
  });
 }

}

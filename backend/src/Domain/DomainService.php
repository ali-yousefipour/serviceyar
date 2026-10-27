<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
use ServiceYar\Support\Uuid;
final class DomainService {
 private const MAP=[
  'schools'=>['table'=>'schools','permission'=>'schools','fields'=>['name','code','school_type','manager_name','phone','address','latitude','longitude','region','is_active'],'search'=>['name','code','manager_name']],
  'students'=>['table'=>'students','permission'=>'students','fields'=>['school_id','first_name','last_name','national_code','grade','mobile','address','latitude','longitude','is_active'],'search'=>['first_name','last_name','national_code']],
  'drivers'=>['table'=>'drivers','permission'=>'drivers','fields'=>['first_name','last_name','national_code','mobile','status','rating','vehicle_id','address','is_active'],'search'=>['first_name','last_name','national_code','mobile']],
  'vehicles'=>['table'=>'vehicles','permission'=>'drivers','fields'=>['plate','model','capacity','is_active'],'search'=>['plate','model']],
  'services'=>['table'=>'services','permission'=>'services','fields'=>['title','code','school_id','driver_id','capacity','status','start_address','end_address','is_active'],'search'=>['title','code']],
  'packs'=>['table'=>'packs','permission'=>'services','fields'=>['title','status','capacity'],'search'=>['title']],
  'contracts'=>['table'=>'contracts','permission'=>'services','fields'=>['title','contract_no','party_type','party_id','start_date','end_date','amount','status'],'search'=>['title','contract_no']],
  'driver-work'=>['table'=>'driver_work','permission'=>'finance','fields'=>['driver_id','work_date','service_id','amount','status','note'],'search'=>['note']],
  'driver-payments'=>['table'=>'driver_payments','permission'=>'finance','fields'=>['driver_id','payment_date','amount','reference_no','note'],'search'=>['reference_no','note']],
  'school-charges'=>['table'=>'school_charges','permission'=>'finance','fields'=>['school_id','title','amount','due_date','status'],'search'=>['title']],
  'service-charges'=>['table'=>'service_charges','permission'=>'finance','fields'=>['service_id','title','amount','due_date','status'],'search'=>['title']],
  'invoices'=>['table'=>'invoices','permission'=>'finance','fields'=>['title','party_type','party_id','amount','status','issue_date','due_date'],'search'=>['title']],
  'payments'=>['table'=>'payments','permission'=>'finance','fields'=>['invoice_id','amount','payment_date','method','reference_no','note'],'search'=>['reference_no','note']],
  'accounts'=>['table'=>'accounting_accounts','permission'=>'finance','fields'=>['code','title','parent_id','is_active'],'search'=>['code','title']],
  'accounting-documents'=>['table'=>'accounting_documents','permission'=>'finance','fields'=>['document_no','document_date','description','status','total_amount'],'search'=>['document_no','description']],
  'wallets'=>['table'=>'wallets','permission'=>'finance','fields'=>['owner_type','owner_id','balance'],'search'=>['owner_type']],
  'messages'=>['table'=>'messages','permission'=>'messages','fields'=>['channel','recipient_type','recipient_id','subject','body','status'],'search'=>['subject','body']],
  'driver-changes'=>['table'=>'driver_changes','permission'=>'services','fields'=>['service_id','old_driver_id','new_driver_id','status','reason'],'search'=>['reason']]
 ];
 private static function cfg(string $r): array {
  $extra=[
   'ledger'=>['table'=>'ledger_entries','permission'=>'finance','fields'=>['party_type','party_id','direction','amount','entry_date','method','reference_no','note'],'search'=>['reference_no','note']],
   'driver-ratings'=>['table'=>'driver_ratings','permission'=>'drivers','fields'=>['driver_id','score','note'],'search'=>['note']],
   'parent-reports'=>['table'=>'parent_reports','permission'=>'messages','fields'=>['student_id','subject','body','status','response'],'search'=>['subject','body']]
  ];
  $c=self::MAP[$r]??$extra[$r]??null;
  if(!$c)throw new AuthException(404,'ماژول مورد نظر پیدا نشد.','RESOURCE_NOT_FOUND');
  $fields=[
   'schools'=>['gender','shift','education_levels'],
   'students'=>['guardian_first_name','guardian_last_name','guardian_national_code','guardian_relation','father_name','gender','nationality','health_status','emergency_phone','return_address','academic_year','shift','service_status','monthly_fee'],
   'drivers'=>['is_representative','bank_name','bank_account','iban'],
   'services'=>['pack_id','shift','monthly_fee','agreed_amount']
  ];
  $c['fields']=array_merge($c['fields'],$fields[$r]??[],isset($extra[$r])?['is_active']:[]);
  return $c;
 }
 private static function scope(int $uid,int $aid,string $permission): void { Authorization::requirePermission($uid,$permission.'.view'); Authorization::requireAgencyAccess($uid,$aid); }
 public static function list(int $uid,int $aid,string $resource,array $q): array {
  $c=self::cfg($resource); self::scope($uid,$aid,$c['permission']); $where='agency_id=:agency';$params=['agency'=>$aid];
  if(isset($q['search'])&&trim((string)$q['search'])!==''){ $parts=[];foreach($c['search'] as $i=>$f){$k='s'.$i;$parts[]="$f LIKE :$k";$params[$k]='%'.trim((string)$q['search']).'%';}if(in_array($resource,['students','drivers'],true)){$parts[]="CONCAT(first_name,' ',last_name) LIKE :full_name";$params['full_name']='%'.trim((string)$q['search']).'%';}$where.=' AND ('.implode(' OR ',$parts).')';}
  foreach(['schoolId'=>'school_id','driverId'=>'driver_id','packId'=>'pack_id','status'=>'status','serviceStatus'=>'service_status','academicYear'=>'academic_year','schoolType'=>'school_type','active'=>'is_active','mobile'=>'mobile'] as $key=>$column){
   if(isset($q[$key]) && $q[$key]!=='' && in_array($column,$c['fields'],true)){$where.=" AND $column=:filter_$column";$params['filter_'.$column]=(string)$q[$key];}
  }
  if($resource==='students' && ($q['unassigned']??'')==='1')$where.=' AND NOT EXISTS (SELECT 1 FROM service_students ss JOIN services sv ON sv.id=ss.service_id WHERE ss.student_id=students.id AND sv.agency_id=students.agency_id AND sv.is_active=1)';
  if($resource==='students'&&!empty($q['eligibleServiceId'])){self::get($uid,$aid,'services',(int)$q['eligibleServiceId']);$where.=' AND EXISTS(SELECT 1 FROM services sv LEFT JOIN service_schools ss ON ss.service_id=sv.id WHERE sv.id=:eligible_service AND sv.agency_id=students.agency_id AND (sv.school_id=students.school_id OR ss.school_id=students.school_id))';$params['eligible_service']=(int)$q['eligibleServiceId'];}
  $limit=min(max((int)($q['limit']??50),1),200);$page=max((int)($q['page']??1),1);$off=($page-1)*$limit;
  $pdo=Connection::get();$count=$pdo->prepare("SELECT COUNT(*) FROM {$c['table']} WHERE $where");$count->execute($params);
  $select='*';
  if($resource==='students')$select="students.*,
   (SELECT CONCAT(d.first_name,' ',d.last_name) FROM service_students ss JOIN services sv ON sv.id=ss.service_id JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=students.agency_id WHERE ss.student_id=students.id AND sv.agency_id=students.agency_id AND sv.is_active=1 LIMIT 1) driver_name,
   (SELECT COALESCE(SUM(i.amount),0) FROM invoices i WHERE i.party_type='student' AND i.party_id=students.id AND i.agency_id=students.agency_id AND i.status<>'cancelled') invoice_total,
   (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id WHERE i.party_type='student' AND i.party_id=students.id AND p.agency_id=students.agency_id) paid_total";
  $s=$pdo->prepare("SELECT $select FROM {$c['table']} WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $off");$s->execute($params);
   $total=(int)$count->fetchColumn(); return ['items'=>$s->fetchAll(),'pagination'=>['page'=>$page,'limit'=>$limit,'total'=>$total,'pages'=>(int)ceil($total/$limit)]];
 }
 public static function get(int $uid,int $aid,string $resource,int $id): array {
  $c=self::cfg($resource);self::scope($uid,$aid,$c['permission']);
  $s=Connection::get()->prepare('SELECT * FROM '.$c['table'].' WHERE id=:id AND agency_id=:a');$s->execute(['id'=>$id,'a'=>$aid]);
  $row=$s->fetch();if(!$row)throw new AuthException(404,'رکورد پیدا نشد.','NOT_FOUND');return $row;
 }
 public static function save(int $uid,int $aid,string $resource,array $b,?int $id=null): array {
  return OperationsService::transaction(fn()=>self::saveRecord($uid,$aid,$resource,$b,$id));
 }
 private static function saveRecord(int $uid,int $aid,string $resource,array $b,?int $id=null): array {
  $c=self::cfg($resource); Authorization::requirePermission($uid,$c['permission'].'.manage'); Authorization::requireAgencyAccess($uid,$aid);
  $pdo=Connection::get();$data=[];foreach($c['fields'] as $f){$camel=preg_replace_callback('/_([a-z])/',fn($m)=>strtoupper($m[1]),$f);if(array_key_exists($camel,$b))$data[$f]=$b[$camel];elseif(array_key_exists($f,$b))$data[$f]=$b[$f];}
  foreach($data as $f=>$v){if(is_array($v)||is_object($v))throw new AuthException(422,'مقدار ورودی نامعتبر است.','VALIDATION_ERROR');}
  $existing=$id?self::get($uid,$aid,$resource,$id):[];
  if($id&&$resource==='messages'&&$existing['status']!=='queued')throw new AuthException(409,'پیام پردازش‌شده قابل ویرایش یا ارسال مجدد از این فرم نیست.','MESSAGE_LOCKED');
  if($id&&$resource==='payments'&&$existing['method']==='bale')throw new AuthException(409,'پرداخت استعلام‌شده بله قابل ویرایش نیست.','VERIFIED_PAYMENT_LOCKED');
  foreach($data as $f=>&$v){if($v==='')$v=null;}unset($v);
  $defaults=['students'=>['nationality'=>'iranian','service_status'=>'no_request','monthly_fee'=>0],'drivers'=>['status'=>'pending','is_representative'=>0],'services'=>['capacity'=>4,'status'=>'draft','monthly_fee'=>0,'agreed_amount'=>0],'packs'=>['status'=>'draft','capacity'=>0],'contracts'=>['status'=>'draft','amount'=>0],'invoices'=>['status'=>'open','amount'=>0],'driver-work'=>['status'=>'pending','amount'=>0],'school-charges'=>['status'=>'open','amount'=>0],'service-charges'=>['status'=>'open','amount'=>0],'messages'=>['status'=>'queued','channel'=>'internal'],'driver-changes'=>['status'=>'pending'],'wallets'=>['balance'=>0],'accounting-documents'=>['status'=>'draft','total_amount'=>0]];
  foreach($defaults[$resource]??[] as $f=>$default)if(array_key_exists($f,$data)&&$data[$f]===null)$data[$f]=$default;
  $required=['schools'=>['name'],'students'=>['school_id','first_name','last_name','mobile'],'drivers'=>['first_name','last_name'],'vehicles'=>['plate'],'services'=>['title'],'packs'=>['title'],'contracts'=>['title','party_type'],'driver-work'=>['driver_id','work_date'],'driver-payments'=>['driver_id','payment_date','amount'],'school-charges'=>['school_id','title'],'service-charges'=>['service_id','title'],'invoices'=>['title'],'payments'=>['payment_date','amount'],'accounts'=>['code','title'],'accounting-documents'=>['document_no','document_date'],'wallets'=>['owner_type'],'messages'=>['body'],'driver-changes'=>['service_id','new_driver_id'],'ledger'=>['party_type','party_id','direction','amount','entry_date'],'driver-ratings'=>['driver_id','score'],'parent-reports'=>['student_id','subject','body']];
  $merged=array_merge($existing,$data);
  if($resource==='payments'&&($data['method']??'')==='bale')throw new AuthException(422,'پرداخت بله فقط پس از استعلام سرویس ثبت می‌شود.','WORKFLOW_REQUIRED');
  if($resource==='services'&&($data['status']??'')==='cancelled')$data['is_active']=0;
  if($resource==='services'&&($data['is_active']??null)==1&&($existing['status']??'')==='cancelled'&&!isset($data['status']))$data['status']='draft';
  if($resource==='driver-changes'){
   if($id)throw new AuthException(422,'درخواست تغییر راننده فقط از عملیات تأیید یا رد قابل بررسی است.','WORKFLOW_REQUIRED');
   if(($merged['status']??'pending')!=='pending')throw new AuthException(422,'درخواست جدید باید در انتظار بررسی باشد.','WORKFLOW_REQUIRED');
   $sv=self::get($uid,$aid,'services',(int)($merged['service_id']??0));$data['old_driver_id']=$sv['driver_id'];
  }
  if($resource==='messages'&&($merged['status']??'queued')!=='queued')throw new AuthException(422,'وضعیت تحویل فقط توسط سرویس ارسال پیام ثبت می‌شود.','WORKFLOW_REQUIRED');
  foreach($required[$resource]??[] as $f)if(!isset($merged[$f])||trim((string)$merged[$f])==='')throw new AuthException(422,'فیلدهای الزامی را کامل کنید: '.$f,'VALIDATION_ERROR');
  foreach(['amount','monthly_fee','agreed_amount','balance','total_amount'] as $f)if(isset($data[$f])&&(!is_numeric($data[$f])||($f!=='balance'&&(float)$data[$f]<0)||abs((float)$data[$f])>999999999999999))throw new AuthException(422,'مبلغ نامعتبر است.','VALIDATION_ERROR');
  foreach(['is_active','is_representative'] as $f)if(isset($data[$f])&&!in_array((string)$data[$f],['0','1'],true))throw new AuthException(422,'وضعیت فعال نامعتبر است.','VALIDATION_ERROR');
  foreach(['latitude'=>90,'longitude'=>180] as $f=>$max)if(isset($data[$f])&&(!is_numeric($data[$f])||abs((float)$data[$f])>$max))throw new AuthException(422,'مختصات نامعتبر است.','VALIDATION_ERROR');
  foreach(['work_date','payment_date','entry_date','document_date','issue_date','due_date','start_date','end_date'] as $f)if(isset($data[$f])&&!OperationsService::validDate((string)$data[$f]))throw new AuthException(422,'تاریخ نامعتبر است.','VALIDATION_ERROR');
  if(isset($data['mobile'])&&!preg_match('/^09[0-9]{9}$/',(string)$data['mobile']))throw new AuthException(422,'شماره همراه باید ۱۱ رقمی و با ۰۹ شروع شود.','VALIDATION_ERROR');
  if(isset($data['score'])&&(!is_numeric($data['score'])||(float)$data['score']<0||(float)$data['score']>5))throw new AuthException(422,'امتیاز باید بین صفر و پنج باشد.','VALIDATION_ERROR');
  if(isset($data['service_status'])&&!in_array($data['service_status'],['no_request','pending','prepayment','waiting','assigned','cancelled'],true))throw new AuthException(422,'وضعیت سرویس نامعتبر است.','VALIDATION_ERROR');
  if($resource==='ledger'){
   if(!in_array($merged['direction'],['receipt','payment'],true)||(float)$merged['amount']<=0)throw new AuthException(422,'نوع دریافت/پرداخت یا مبلغ نامعتبر است.','VALIDATION_ERROR');
   self::party($pdo,$aid,(string)$merged['party_type'],(int)$merged['party_id']);
   if(!$id)$data['created_by']=$uid;
  }
  if($resource==='messages'&&!empty($merged['recipient_id']))self::party($pdo,$aid,(string)($merged['recipient_type']??''),(int)$merged['recipient_id']);
  if($resource==='messages'&&($merged['channel']??'internal')==='sms'){
   $target=['student'=>'students','driver'=>'drivers'][$merged['recipient_type']??'']??null;
   if(!$target||empty($merged['recipient_id']))throw new AuthException(422,'گیرنده پیامک باید دانش‌آموز یا راننده مشخص باشد.','VALIDATION_ERROR');
   $recipient=self::get($uid,$aid,$target,(int)$merged['recipient_id']);$mobile=(string)($recipient['mobile']??'');if(!preg_match('/^09\d{9}$/',$mobile))throw new AuthException(422,'شماره همراه گیرنده معتبر نیست.','VALIDATION_ERROR');$data['recipient_mobile']=$mobile;
  }
  if(in_array($resource,['wallets','contracts','invoices'],true)){
   $type=$merged[$resource==='wallets'?'owner_type':'party_type']??null;$party=$merged[$resource==='wallets'?'owner_id':'party_id']??null;
   if($party!==null)self::party($pdo,$aid,(string)$type,(int)$party);
  }
  self::validateRelations($pdo,$aid,$c['table'],$data);
  if(isset($data['amount']) && (!is_numeric($data['amount']) || (float)$data['amount']<0))throw new AuthException(422,'مبلغ نامعتبر است.','VALIDATION_ERROR');
  if(isset($data['capacity']) && (!ctype_digit((string)$data['capacity']) || (int)$data['capacity']<1 || (int)$data['capacity']>1000))throw new AuthException(422,'ظرفیت نامعتبر است.','VALIDATION_ERROR');
  if($id&&$resource==='services'){
   $p=$pdo->prepare('SELECT id FROM services WHERE id=:id AND agency_id=:a FOR UPDATE');$p->execute(['id'=>$id,'a'=>$aid]);
   $p=$pdo->prepare('SELECT COUNT(*) FROM service_students WHERE service_id=:s');$p->execute(['s'=>$id]);$count=(int)$p->fetchColumn();
   if(isset($data['capacity'])&&(int)$data['capacity']<$count)throw new AuthException(409,'ظرفیت از دانش‌آموزان تخصیص‌یافته کمتر است.','CAPACITY_EXCEEDED');
   if($count&&isset($data['school_id'])&&(int)$data['school_id']!==(int)$existing['school_id'])throw new AuthException(409,'ابتدا تخصیص دانش‌آموزان سرویس را حذف کنید.','SCHOOL_MISMATCH');
   if(isset($data['is_active'])&&(int)$data['is_active']===1&&(int)$existing['is_active']===0){
    $q=$pdo->prepare('SELECT COUNT(*) FROM service_students old JOIN service_students other ON other.student_id=old.student_id AND other.service_id<>old.service_id JOIN services sv ON sv.id=other.service_id WHERE old.service_id=:id AND sv.agency_id=:a AND sv.is_active=1');$q->execute(['id'=>$id,'a'=>$aid]);if((int)$q->fetchColumn()>0)throw new AuthException(409,'برخی دانش‌آموزان سرویس فعال دیگری دارند.','ALREADY_ASSIGNED');
   }
  }
  if($id&&$resource==='students'&&(isset($data['school_id'])||isset($data['service_status']))){
   $p=$pdo->prepare('SELECT COUNT(*) FROM service_students ss JOIN services s ON s.id=ss.service_id WHERE ss.student_id=:id AND s.agency_id=:a AND s.is_active=1');$p->execute(['id'=>$id,'a'=>$aid]);$assigned=(int)$p->fetchColumn()>0;
   if(($assigned&&isset($data['school_id'])&&(int)$data['school_id']!==(int)$existing['school_id'])||($assigned&&isset($data['service_status'])&&$data['service_status']!=='assigned')||(!$assigned&&($data['service_status']??'')==='assigned'))throw new AuthException(409,'وضعیت و مدرسه دانش‌آموز باید با تخصیص سرویس سازگار باشد.','ASSIGNMENT_CONFLICT');
  }
  if(!$id&&$resource==='students'&&($data['service_status']??'')==='assigned')throw new AuthException(422,'ابتدا دانش‌آموز را ثبت و سپس به سرویس تخصیص دهید.','ASSIGNMENT_CONFLICT');
  $wasCreate=!$id;
  if(!$id){$data['agency_id']=$aid;if(in_array('uuid',thisColumns($c['table'],$pdo),true))$data['uuid']=Uuid::v4();$cols=array_keys($data);$sql='INSERT INTO '.$c['table'].' ('.implode(',',$cols).') VALUES ('.implode(',',array_map(fn($x)=>':'.$x,$cols)).')';$st=$pdo->prepare($sql);$st->execute($data);$id=(int)$pdo->lastInsertId();}
  else{$sets=[];$params=['id'=>$id,'agency'=>$aid];foreach($data as $f=>$v){if($f==='agency_id'||$f==='uuid')continue;$sets[]="$f=:$f";$params[$f]=$v;}if(!$sets)throw new AuthException(422,'تغییری ارسال نشده است.','VALIDATION_ERROR');$pdo->prepare('UPDATE '.$c['table'].' SET '.implode(',',$sets).' WHERE id=:id AND agency_id=:agency')->execute($params);}
  if($resource==='services'&&!empty($data['school_id']))$pdo->prepare('INSERT IGNORE INTO service_schools(service_id,school_id) VALUES(?,?)')->execute([$id,$data['school_id']]);
  if($resource==='services'&&isset($data['is_active']))self::syncStudentStatus($pdo,$aid,$id,(int)$data['is_active']===1);
  if($resource==='students'&&isset($data['is_active'])&&(int)$data['is_active']===0)self::releaseStudent($pdo,$uid,$aid,$id);
  $s=$pdo->prepare('SELECT * FROM '.$c['table'].' WHERE id=:id AND agency_id=:agency');$s->execute(['id'=>$id,'agency'=>$aid]);$row=$s->fetch();if(!$row)throw new AuthException(404,'رکورد پیدا نشد.','NOT_FOUND');self::activity($pdo,$aid,$uid,$wasCreate?'create':'update',$c['table'],$id);return $row;
 }
 private static function validateRelations(\PDO $pdo,int $aid,string $table,array $data): void {
  $relations=[
   'students'=>['school_id'=>'schools'],'drivers'=>['vehicle_id'=>'vehicles'],'services'=>['school_id'=>'schools','driver_id'=>'drivers','pack_id'=>'packs'],
   'driver_work'=>['driver_id'=>'drivers','service_id'=>'services'],'driver_payments'=>['driver_id'=>'drivers'],
   'school_charges'=>['school_id'=>'schools'],'service_charges'=>['service_id'=>'services'],'payments'=>['invoice_id'=>'invoices'],
   'driver_changes'=>['service_id'=>'services','old_driver_id'=>'drivers','new_driver_id'=>'drivers'],
   'driver_ratings'=>['driver_id'=>'drivers'],'parent_reports'=>['student_id'=>'students'],'accounting_accounts'=>['parent_id'=>'accounting_accounts']
  ];
  foreach($relations[$table]??[] as $field=>$parent){
   if(!isset($data[$field])||$data[$field]===''||$data[$field]===null)continue;
   if(!ctype_digit((string)$data[$field])||(int)$data[$field]<1)throw new AuthException(422,'شناسه ارتباط نامعتبر است.','VALIDATION_ERROR');
   $active=in_array('is_active',thisColumns($parent,$pdo),true)?' AND is_active=1':'';
   $q=$pdo->prepare("SELECT 1 FROM {$parent} WHERE id=:id AND agency_id=:a$active LIMIT 1");$q->execute(['id'=>(int)$data[$field],'a'=>$aid]);
   if(!$q->fetchColumn())throw new AuthException(422,'ارتباط انتخاب‌شده خارج از محدوده سازمان است.','CROSS_SCOPE_REFERENCE');
  }
 }
 public static function party(\PDO $pdo,int $aid,string $type,int $id): void {
  $table=['student'=>'students','driver'=>'drivers','school'=>'schools','agency'=>'agencies'][$type]??null;
  if(!$table||$id<1)throw new AuthException(422,'طرف حساب نامعتبر است.','VALIDATION_ERROR');
  $sql=$table==='agencies'?'SELECT 1 FROM agencies WHERE id=:id AND id=:a':'SELECT 1 FROM '.$table.' WHERE id=:id AND agency_id=:a';
  $q=$pdo->prepare($sql);$q->execute(['id'=>$id,'a'=>$aid]);if(!$q->fetchColumn())throw new AuthException(422,'طرف حساب خارج از محدوده شرکت است.','CROSS_SCOPE_REFERENCE');
 }

 private static function activity(\PDO $pdo,int $aid,int $uid,string $action,string $entity,int $entityId): void { try{$q=$pdo->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id) VALUES(:a,:u,:x,:t,:i)');$q->execute(['a'=>$aid,'u'=>$uid,'x'=>$action,'t'=>$entity,'i'=>$entityId]);}catch(\Throwable $e){error_log($e->getMessage());} }
 public static function assignStudent(int $uid,int $aid,int $serviceId,int $studentId,?string $pickupAddress=null,?int $order=null): void {
  OperationsService::assign($uid,$aid,$serviceId,$studentId,$pickupAddress,$order);
 }
 public static function removeStudent(int $uid,int $aid,int $serviceId,int $studentId): void {
  Authorization::requirePermission($uid,'services.manage');Authorization::requireAgencyAccess($uid,$aid);$pdo=Connection::get();
  OperationsService::transaction(function(\PDO $pdo)use($aid,$serviceId,$studentId){$q=$pdo->prepare('DELETE ss FROM service_students ss JOIN services s ON s.id=ss.service_id WHERE ss.service_id=:s AND ss.student_id=:st AND s.agency_id=:a');$q->execute(['s'=>$serviceId,'st'=>$studentId,'a'=>$aid]);if($q->rowCount()<1)throw new AuthException(404,'تخصیص سرویس پیدا نشد.','NOT_FOUND');$q=$pdo->prepare("UPDATE students SET service_status='waiting' WHERE id=:id AND agency_id=:a AND NOT EXISTS(SELECT 1 FROM service_students ss JOIN services sv ON sv.id=ss.service_id WHERE ss.student_id=:st AND sv.agency_id=:agency AND sv.is_active=1)");$q->execute(['id'=>$studentId,'a'=>$aid,'st'=>$studentId,'agency'=>$aid]);});self::activity($pdo,$aid,$uid,'remove_student','services',$serviceId);
 }
 public static function serviceStudents(int $uid,int $aid,int $serviceId): array {
  Authorization::requirePermission($uid,'services.view');Authorization::requireAgencyAccess($uid,$aid);$q=Connection::get()->prepare('SELECT st.id,st.uuid,st.first_name,st.last_name,st.grade,st.national_code,ss.pickup_address,ss.pickup_order FROM service_students ss JOIN students st ON st.id=ss.student_id JOIN services s ON s.id=ss.service_id WHERE ss.service_id=:s AND s.agency_id=:a ORDER BY ss.pickup_order,st.last_name,st.first_name');$q->execute(['s'=>$serviceId,'a'=>$aid]);return $q->fetchAll();
 }
 public static function delete(int $uid,int $aid,string $resource,int $id): void {
  $c=self::cfg($resource);Authorization::requirePermission($uid,$c['permission'].'.manage');Authorization::requireAgencyAccess($uid,$aid);self::get($uid,$aid,$resource,$id);$pdo=Connection::get();$columns=thisColumns($c['table'],$pdo);
  if(!in_array('is_active',$columns,true))throw new AuthException(422,'این سابقه قابل غیرفعال‌سازی نیست؛ وضعیت آن را ویرایش کنید.','DELETE_UNSUPPORTED');
  OperationsService::transaction(function(\PDO $pdo)use($c,$id,$aid,$resource,$uid){$pdo->prepare("UPDATE {$c['table']} SET is_active=0 WHERE id=:id AND agency_id=:agency")->execute(['id'=>$id,'agency'=>$aid]);if($resource==='services')self::syncStudentStatus($pdo,$aid,$id,false);if($resource==='students')self::releaseStudent($pdo,$uid,$aid,$id);});self::activity($pdo,$aid,$uid,'deactivate',$c['table'],$id);
 }
 private static function releaseStudent(\PDO $pdo,int $uid,int $aid,int $id): void {
  $q=$pdo->prepare('SELECT ss.service_id FROM service_students ss JOIN services s ON s.id=ss.service_id WHERE ss.student_id=:id AND s.agency_id=:a');$q->execute(['id'=>$id,'a'=>$aid]);$services=$q->fetchAll(\PDO::FETCH_COLUMN);
  $pdo->prepare('DELETE ss FROM service_students ss JOIN services s ON s.id=ss.service_id WHERE ss.student_id=:id AND s.agency_id=:a')->execute(['id'=>$id,'a'=>$aid]);
  $pdo->prepare("UPDATE students SET service_status='cancelled' WHERE id=:id AND agency_id=:a")->execute(['id'=>$id,'a'=>$aid]);
  if($services)$pdo->prepare("INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id,metadata) VALUES(:a,:u,'release_student','students',:id,:m)")->execute(['a'=>$aid,'u'=>$uid,'id'=>$id,'m'=>json_encode(['serviceIds'=>$services],JSON_THROW_ON_ERROR)]);
 }
 private static function syncStudentStatus(\PDO $pdo,int $aid,int $serviceId,bool $active): void {
  if($active){$q=$pdo->prepare("UPDATE students st JOIN service_students ss ON ss.student_id=st.id SET st.service_status='assigned' WHERE ss.service_id=:s AND st.agency_id=:a");$q->execute(['s'=>$serviceId,'a'=>$aid]);}
  else{$q=$pdo->prepare("UPDATE students st JOIN service_students ss ON ss.student_id=st.id SET st.service_status='waiting' WHERE ss.service_id=:s AND st.agency_id=:a AND NOT EXISTS(SELECT 1 FROM service_students s2 JOIN services sv ON sv.id=s2.service_id WHERE s2.student_id=st.id AND sv.agency_id=:agency AND sv.is_active=1)");$q->execute(['s'=>$serviceId,'a'=>$aid,'agency'=>$aid]);}
 }
}
function thisColumns(string $table,\PDO $pdo): array { static $cache=[];if(isset($cache[$table]))return $cache[$table];$s=$pdo->query("SHOW COLUMNS FROM $table");return $cache[$table]=$s->fetchAll(\PDO::FETCH_COLUMN); }

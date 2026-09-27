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
 private static function cfg(string $r): array { if(!isset(self::MAP[$r]))throw new AuthException(404,'ماژول مورد نظر پیدا نشد.','RESOURCE_NOT_FOUND'); return self::MAP[$r]; }
 private static function scope(int $uid,int $aid,string $permission): void { Authorization::requirePermission($uid,$permission.'.view'); Authorization::requireAgencyAccess($uid,$aid); }
 public static function list(int $uid,int $aid,string $resource,array $q): array {
  $c=self::cfg($resource); self::scope($uid,$aid,$c['permission']); $where='agency_id=:agency';$params=['agency'=>$aid];
  if(isset($q['search'])&&trim((string)$q['search'])!==''){ $parts=[];foreach($c['search'] as $i=>$f){$k='s'.$i;$parts[]="$f LIKE :$k";$params[$k]='%'.trim((string)$q['search']).'%';}$where.=' AND ('.implode(' OR ',$parts).')';}
  $limit=min(max((int)($q['limit']??50),1),200);$page=max((int)($q['page']??1),1);$off=($page-1)*$limit;
  $pdo=Connection::get();$count=$pdo->prepare("SELECT COUNT(*) FROM {$c['table']} WHERE $where");$count->execute($params);
  $s=$pdo->prepare("SELECT * FROM {$c['table']} WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $off");$s->execute($params);
   $total=(int)$count->fetchColumn(); return ['items'=>$s->fetchAll(),'pagination'=>['page'=>$page,'limit'=>$limit,'total'=>$total,'pages'=>(int)ceil($total/$limit)]];
 }
 public static function save(int $uid,int $aid,string $resource,array $b,?int $id=null): array {
  $c=self::cfg($resource); Authorization::requirePermission($uid,$c['permission'].'.manage'); Authorization::requireAgencyAccess($uid,$aid);
  $pdo=Connection::get();$data=[];foreach($c['fields'] as $f){$camel=preg_replace_callback('/_([a-z])/',fn($m)=>strtoupper($m[1]),$f);if(array_key_exists($camel,$b))$data[$f]=$b[$camel];elseif(array_key_exists($f,$b))$data[$f]=$b[$f];}
  foreach($data as $f=>$v){if(is_array($v)||is_object($v))throw new AuthException(422,'مقدار ورودی نامعتبر است.','VALIDATION_ERROR');}
  self::validateRelations($pdo,$aid,$c['table'],$data);
  if(isset($data['amount']) && (!is_numeric($data['amount']) || (float)$data['amount']<0))throw new AuthException(422,'مبلغ نامعتبر است.','VALIDATION_ERROR');
  if(isset($data['capacity']) && ((int)$data['capacity']<0 || (int)$data['capacity']>1000))throw new AuthException(422,'ظرفیت نامعتبر است.','VALIDATION_ERROR');
  $wasCreate=!$id;
  if(!$id){$data['agency_id']=$aid;if(in_array('uuid',$thisColumns($c['table'],$pdo),true))$data['uuid']=Uuid::v4();$cols=array_keys($data);$sql='INSERT INTO '.$c['table'].' ('.implode(',',$cols).') VALUES ('.implode(',',array_map(fn($x)=>':'.$x,$cols)).')';$st=$pdo->prepare($sql);$st->execute($data);$id=(int)$pdo->lastInsertId();}
  else{$sets=[];$params=['id'=>$id,'agency'=>$aid];foreach($data as $f=>$v){if($f==='agency_id'||$f==='uuid')continue;$sets[]="$f=:$f";$params[$f]=$v;}if(!$sets)throw new AuthException(422,'تغییری ارسال نشده است.','VALIDATION_ERROR');$pdo->prepare('UPDATE '.$c['table'].' SET '.implode(',',$sets).' WHERE id=:id AND agency_id=:agency')->execute($params);}
  $s=$pdo->prepare('SELECT * FROM '.$c['table'].' WHERE id=:id AND agency_id=:agency');$s->execute(['id'=>$id,'agency'=>$aid]);$row=$s->fetch();if(!$row)throw new AuthException(404,'رکورد پیدا نشد.','NOT_FOUND');self::activity($pdo,$aid,$uid,$wasCreate?'create':'update',$c['table'],$id);return $row;
 }
 private static function validateRelations(\PDO $pdo,int $aid,string $table,array $data): void {
  $relations=[
   'students'=>['school_id'=>'schools'],'drivers'=>['vehicle_id'=>'vehicles'],'services'=>['school_id'=>'schools','driver_id'=>'drivers'],
   'driver_work'=>['driver_id'=>'drivers','service_id'=>'services'],'driver_payments'=>['driver_id'=>'drivers'],
   'school_charges'=>['school_id'=>'schools'],'service_charges'=>['service_id'=>'services'],'payments'=>['invoice_id'=>'invoices']
  ];
  foreach($relations[$table]??[] as $field=>$parent){
   if(!isset($data[$field])||$data[$field]===''||$data[$field]===null)continue;
   $q=$pdo->prepare("SELECT 1 FROM {$parent} WHERE id=:id AND agency_id=:a AND is_active=1 LIMIT 1");
   try{$q->execute(['id'=>(int)$data[$field],'a'=>$aid]);}catch(\PDOException $e){$q=$pdo->prepare("SELECT 1 FROM {$parent} WHERE id=:id AND agency_id=:a LIMIT 1");$q->execute(['id'=>(int)$data[$field],'a'=>$aid]);}
   if(!$q->fetchColumn())throw new AuthException(422,'ارتباط انتخاب‌شده خارج از محدوده سازمان است.','CROSS_SCOPE_REFERENCE');
  }
 }

 private static function activity(\PDO $pdo,int $aid,int $uid,string $action,string $entity,int $entityId): void { try{$q=$pdo->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id) VALUES(:a,:u,:x,:t,:i)');$q->execute(['a'=>$aid,'u'=>$uid,'x'=>$action,'t'=>$entity,'i'=>$entityId]);}catch(\Throwable $e){error_log($e->getMessage());} }
 public static function assignStudent(int $uid,int $aid,int $serviceId,int $studentId,?string $pickupAddress=null,?int $order=null): void {
  Authorization::requirePermission($uid,'services.manage');Authorization::requireAgencyAccess($uid,$aid);$pdo=Connection::get();
  $q=$pdo->prepare('SELECT 1 FROM services WHERE id=:s AND agency_id=:a AND is_active=1');$q->execute(['s'=>$serviceId,'a'=>$aid]);if(!$q->fetchColumn())throw new AuthException(404,'سرویس پیدا نشد.','NOT_FOUND');
  $q=$pdo->prepare('SELECT 1 FROM students WHERE id=:s AND agency_id=:a AND is_active=1');$q->execute(['s'=>$studentId,'a'=>$aid]);if(!$q->fetchColumn())throw new AuthException(404,'دانش‌آموز پیدا نشد.','NOT_FOUND');
  $q=$pdo->prepare('INSERT INTO service_students(service_id,student_id,pickup_address,pickup_order) VALUES(:s,:st,:p,:o) ON DUPLICATE KEY UPDATE pickup_address=VALUES(pickup_address),pickup_order=VALUES(pickup_order)');$q->execute(['s'=>$serviceId,'st'=>$studentId,'p'=>$pickupAddress,'o'=>$order]);self::activity($pdo,$aid,$uid,'assign_student','services',$serviceId);
 }
 public static function removeStudent(int $uid,int $aid,int $serviceId,int $studentId): void {
  Authorization::requirePermission($uid,'services.manage');Authorization::requireAgencyAccess($uid,$aid);$q=Connection::get()->prepare('DELETE ss FROM service_students ss JOIN services s ON s.id=ss.service_id WHERE ss.service_id=:s AND ss.student_id=:st AND s.agency_id=:a');$q->execute(['s'=>$serviceId,'st'=>$studentId,'a'=>$aid]);
 }
 public static function serviceStudents(int $uid,int $aid,int $serviceId): array {
  Authorization::requirePermission($uid,'services.view');Authorization::requireAgencyAccess($uid,$aid);$q=Connection::get()->prepare('SELECT st.id,st.uuid,st.first_name,st.last_name,st.grade,st.national_code,ss.pickup_address,ss.pickup_order FROM service_students ss JOIN students st ON st.id=ss.student_id JOIN services s ON s.id=ss.service_id WHERE ss.service_id=:s AND s.agency_id=:a ORDER BY ss.pickup_order,st.last_name,st.first_name');$q->execute(['s'=>$serviceId,'a'=>$aid]);return $q->fetchAll();
 }
 public static function delete(int $uid,int $aid,string $resource,int $id): void { $c=self::cfg($resource);Authorization::requirePermission($uid,$c['permission'].'.manage');Authorization::requireAgencyAccess($uid,$aid);$pdo=Connection::get();$s=$pdo->prepare("UPDATE {$c['table']} SET is_active=0 WHERE id=:id AND agency_id=:agency");try{$s->execute(['id'=>$id,'agency'=>$aid]);}catch(\PDOException){$pdo->prepare("DELETE FROM {$c['table']} WHERE id=:id AND agency_id=:agency")->execute(['id'=>$id,'agency'=>$aid]);} }
}
function thisColumns(string $table,\PDO $pdo): array { static $cache=[];if(isset($cache[$table]))return $cache[$table];$s=$pdo->query("SHOW COLUMNS FROM $table");return $cache[$table]=$s->fetchAll(\PDO::FETCH_COLUMN); }

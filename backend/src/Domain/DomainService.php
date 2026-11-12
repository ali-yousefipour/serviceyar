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
  'vehicles'=>['table'=>'vehicles','permission'=>'drivers','fields'=>['plate','model','capacity','owner_name','is_active'],'search'=>['plate','model','owner_name']],
  'services'=>['table'=>'services','permission'=>'services','fields'=>['title','code','school_id','driver_id','capacity','status','start_address','end_address','pack_id','shift','monthly_fee','agreed_amount','is_active'],'search'=>['title','code']],
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
   'students'=>['guardian_first_name','guardian_last_name','guardian_national_code','guardian_relation','father_name','gender','nationality','health_status','emergency_phone','return_address','academic_year','shift','service_status','monthly_fee','tariff_distance_km','tariff_vehicle_class'],
   'drivers'=>['is_representative','bank_name','bank_account','iban'],
   'services'=>['pack_id','shift','monthly_fee','agreed_amount']
  ];
  $c['fields']=array_merge($c['fields'],$fields[$r]??[],isset($extra[$r])?['is_active']:[]);
  return $c;
 }
 private static function scope(int $uid,int $aid,string $permission): void { Authorization::requirePermission($uid,$permission.'.view'); Authorization::requireAgencyAccess($uid,$aid); }
 /**
  * Allowlisted, stable ordering: never concatenate user-provided SQL names.
  * The primary key guarantees consistent pagination for equal values.
  */
 private static function listOrdering(string $resource,array $query): string {
  $allowed=[
   'schools'=>['id','name','code','region','school_type'],
   'students'=>['id','first_name','last_name','national_code','monthly_fee','school_id','academic_year'],
   'drivers'=>['id','first_name','last_name','national_code','status','mobile'],
   'vehicles'=>['id','plate','model','capacity'],
   'services'=>['id','title','code','capacity','status'],
   'packs'=>['id','title','capacity','status'],
   'contracts'=>['id','title','start_date','end_date','amount','status'],
   'driver-work'=>['id','work_date','amount','status'],
   'driver-payments'=>['id','payment_date','amount'],
   'school-charges'=>['id','due_date','amount','title'],
   'service-charges'=>['id','due_date','amount','title'],
   'invoices'=>['id','issue_date','amount','status'],
   'payments'=>['id','payment_date','amount'],
   'accounts'=>['id','code','title'],
   'accounting-documents'=>['id','document_date','total_amount','status'],
   'wallets'=>['id','balance'],
   'messages'=>['id','status','subject'],
   'driver-changes'=>['id','status'],
   'ledger'=>['id','entry_date','amount','direction','method'],
   'driver-ratings'=>['id','score'],
   'parent-reports'=>['id','status','subject']
  ];
  $column=$query['sortBy']??'id';
  $direction=$query['sortDir']??'desc';
  if(!is_string($column)||!in_array($column,$allowed[$resource]??['id'],true)
     ||!is_string($direction)||!in_array(strtolower($direction),['asc','desc'],true)){
   throw new AuthException(422,'ستون یا جهت مرتب‌سازی معتبر نیست.','INVALID_SORT');
  }
  $dir=strtoupper($direction);
  // Prefix prevents ambiguity in subqueries, and the column was allowlisted.
  $table=self::cfg($resource)['table'];
  return $table.'.'.$column.' '.$dir.($column==='id'?'':', '.$table.'.id DESC');
 }
 public static function list(int $uid,int $aid,string $resource,array $q): array {
  $c=self::cfg($resource); self::scope($uid,$aid,$c['permission']); $where='agency_id=:agency';$params=['agency'=>$aid];
  if($resource==='services'){
   [$clauses,$serviceParams]=self::serviceFilters($q,$uid);
   foreach($clauses as $clause)$where.=' AND '.$clause;
   $params+=$serviceParams;
  }elseif($resource==='students'&&!empty($q['eligibleServiceId'])&&is_string($q['search']??null)){
   $term=preg_replace('/[\s\x{200c}\x{200d}]+/u','',strtr($q['search'],['ي'=>'ی','ى'=>'ی','ك'=>'ک']));
   if($term!==null&&$term!==''){
    $where.=" AND (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT_WS('',first_name,last_name),'ي','ی'),'ى','ی'),'ك','ک'),' ',''),'‌',''),'‍','') LIKE :member_name ESCAPE '!' OR national_code LIKE :member_code ESCAPE '!')";
    $params['member_name']=$params['member_code']='%'.strtr($term,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
   }
  }elseif(isset($q['search'])&&trim((string)$q['search'])!==''){ $parts=[];foreach($c['search'] as $i=>$f){if($resource==='vehicles'&&$f==='owner_name'&&!in_array('owner_name',thisColumns('vehicles',Connection::get()),true))continue;$k='s'.$i;$parts[]="$f LIKE :$k";$params[$k]='%'.trim((string)$q['search']).'%';}if(in_array($resource,['students','drivers'],true)){$parts[]="CONCAT(first_name,' ',last_name) LIKE :full_name";$params['full_name']='%'.trim((string)$q['search']).'%';}$where.=' AND ('.implode(' OR ',$parts).')';}
  foreach($resource==='services'?[]:['schoolId'=>'school_id','driverId'=>'driver_id','packId'=>'pack_id','status'=>'status','serviceStatus'=>'service_status','academicYear'=>'academic_year','schoolType'=>'school_type','active'=>'is_active','mobile'=>'mobile'] as $key=>$column){
   if(isset($q[$key]) && $q[$key]!=='' && in_array($column,$c['fields'],true)){$where.=" AND $column=:filter_$column";$params['filter_'.$column]=(string)$q[$key];}
  }
  if($resource==='vehicles'){
   $driverSearch=trim((string)($q['driverSearch']??''));
   $driverSearch=preg_replace('/[\s\x{200c}\x{200d}]+/u',' ',$driverSearch)??$driverSearch;
   $driverSearch=strtr($driverSearch,['ي'=>'ی','ك'=>'ک']);
   if($driverSearch!==''){
    $where.=" AND EXISTS (SELECT 1 FROM drivers vd WHERE vd.vehicle_id=vehicles.id AND vd.agency_id=vehicles.agency_id AND REPLACE(REPLACE(CONCAT_WS(' ',vd.first_name,vd.last_name),'ي','ی'),'ك','ک') LIKE :vehicle_driver_name)";
    $params['vehicle_driver_name']='%'.$driverSearch.'%';
   }
   $parts=['plateFirst'=>2,'plateLetter'=>1,'plateMiddle'=>3,'plateRegion'=>2];
   $patterns=[];$any=false;
   foreach($parts as $field=>$length){
    $raw=(string)($q[$field]??'');
    $raw=strtr($raw,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','ي'=>'ی','ك'=>'ک']);
    $raw=trim($raw);
    if($raw!=='' && ($field==='plateLetter'? !preg_match('/^[آ-یA-Za-z]$/u',$raw) : !preg_match('/^[0-9]{1,'.$length.'}$/',$raw)))
     throw new AuthException(422,'بخش‌های پلاک ایران را به‌درستی وارد کنید.','INVALID_PLATE_FILTER');
    if($raw!=='')$any=true;
    $patterns[]=$raw!==''?preg_quote($raw,'/').($field==='plateLetter'?'':'[0-9]{0,'.($length-strlen($raw)).'}'):($field==='plateLetter'?'[آ-یA-Za-z]':'[0-9]{'.$length.'}');
   }
   if($any){
    $normalized='vehicles.plate';
    foreach([' ','-','/','ـ','ایران',chr(9)] as $separator)$normalized='REPLACE('.$normalized.",'".str_replace("'","''",$separator)."','')";
    foreach(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'] as $i=>$digit)$normalized='REPLACE('.$normalized.",'".$digit."','".($i%10)."')";
    $normalized="REPLACE(REPLACE($normalized,'ي','ی'),'ك','ک')";
    $where.=" AND $normalized REGEXP :vehicle_plate_pattern";
    $params['vehicle_plate_pattern']='^'.implode('',$patterns).'$';
   }
  }
  if($resource==='ledger'){
   $types=['student','driver','school','agency'];
   $partyType=$q['partyType']??'';
   if($partyType!==''&&(!is_string($partyType)||!in_array($partyType,$types,true)))
    throw new AuthException(422,'نوع طرف حساب معتبر نیست.','VALIDATION_ERROR');
   if($partyType!==''){$where.=' AND party_type=:ledger_party_type';$params['ledger_party_type']=$partyType;}
   $direction=$q['direction']??'';
   if($direction!==''&&(!is_string($direction)||!in_array($direction,['receipt','payment'],true)))
    throw new AuthException(422,'نوع عملیات معتبر نیست.','VALIDATION_ERROR');
   if($direction!==''){$where.=' AND direction=:ledger_direction';$params['ledger_direction']=$direction;}
   $method=$q['method']??'';
   if($method!==''&&(!is_string($method)||!in_array($method,['cash','card','bank','transfer','cheque','wallet'],true)))
    throw new AuthException(422,'روش پرداخت معتبر نیست.','VALIDATION_ERROR');
   if($method!==''){$where.=' AND method=:ledger_method';$params['ledger_method']=$method;}
   foreach(['dateFrom'=>'>=','dateTo'=>'<='] as $name=>$operator){
    $date=$q[$name]??'';
    if($date==='')continue;
    if(!is_string($date)||!OperationsService::validDate($date))
     throw new AuthException(422,'تاریخ دریافت یا پرداخت معتبر نیست.','VALIDATION_ERROR');
    $where.=" AND entry_date $operator :ledger_$name";$params['ledger_'.$name]=$date;
   }
   if(isset($params['ledger_dateFrom'],$params['ledger_dateTo'])&&$params['ledger_dateFrom']>$params['ledger_dateTo'])
    throw new AuthException(422,'تاریخ شروع باید قبل از تاریخ پایان باشد.','VALIDATION_ERROR');
   foreach(['amountMin'=>'>=','amountMax'=>'<='] as $name=>$operator){
    $amount=$q[$name]??'';
    if($amount==='')continue;
    if(!is_scalar($amount)||!is_numeric((string)$amount)||(float)$amount<0)
     throw new AuthException(422,'محدوده مبلغ معتبر نیست.','VALIDATION_ERROR');
    $where.=" AND amount $operator :ledger_$name";$params['ledger_'.$name]=(string)$amount;
   }
   if(isset($params['ledger_amountMin'],$params['ledger_amountMax'])&&(float)$params['ledger_amountMin']>(float)$params['ledger_amountMax'])
    throw new AuthException(422,'حداقل مبلغ نمی‌تواند بیشتر از حداکثر باشد.','VALIDATION_ERROR');
   $partySearch=$q['partySearch']??'';
   if($partySearch!==''&&(!is_string($partySearch)||mb_strlen($partySearch)>200))
    throw new AuthException(422,'عبارت جست‌وجوی طرف حساب معتبر نیست.','VALIDATION_ERROR');
   if(is_string($partySearch)&&trim($partySearch)!==''){
    $normalized=preg_replace('/[\\s\\x{200c}\\x{200d}]+/u','',strtr(trim($partySearch),['ي'=>'ی','ى'=>'ی','ك'=>'ک']));
    $pattern='%'.strtr($normalized??'',['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
    $where.=" AND (
     (party_type='student' AND EXISTS(SELECT 1 FROM students ls WHERE ls.id=ledger_entries.party_id AND ls.agency_id=ledger_entries.agency_id AND REPLACE(REPLACE(REPLACE(REPLACE(CONCAT_WS('',ls.first_name,ls.last_name),'ي','ی'),'ك','ک'),' ',''),'‌','') LIKE :ledger_student_name ESCAPE '!'))
     OR (party_type='driver' AND EXISTS(SELECT 1 FROM drivers ld WHERE ld.id=ledger_entries.party_id AND ld.agency_id=ledger_entries.agency_id AND REPLACE(REPLACE(REPLACE(REPLACE(CONCAT_WS('',ld.first_name,ld.last_name),'ي','ی'),'ك','ک'),' ',''),'‌','') LIKE :ledger_driver_name ESCAPE '!'))
     OR (party_type='school' AND EXISTS(SELECT 1 FROM schools lsc WHERE lsc.id=ledger_entries.party_id AND lsc.agency_id=ledger_entries.agency_id AND REPLACE(REPLACE(REPLACE(lsc.name,'ي','ی'),'ك','ک'),' ','') LIKE :ledger_school_name ESCAPE '!'))
     OR (party_type='agency' AND EXISTS(SELECT 1 FROM agencies la WHERE la.id=ledger_entries.party_id AND la.id=ledger_entries.agency_id AND REPLACE(REPLACE(REPLACE(la.name,'ي','ی'),'ك','ک'),' ','') LIKE :ledger_agency_name ESCAPE '!'))
    )";
    foreach(['student','driver','school','agency'] as $type)$params['ledger_'.$type.'_name']=$pattern;
   }
  }
  if($resource==='driver-work'){
   [$workWhere,$workParams]=self::workDateFilters($q);
   foreach($workWhere as $clause)$where.=' AND '.$clause;
   $params+=$workParams;
  }
  if($resource==='students' && ($q['unassigned']??'')==='1')$where.=' AND NOT EXISTS (SELECT 1 FROM service_students ss JOIN services sv ON sv.id=ss.service_id WHERE ss.student_id=students.id AND sv.agency_id=students.agency_id AND sv.is_active=1)';
  if($resource==='students'&&!empty($q['eligibleServiceId'])){self::get($uid,$aid,'services',(int)$q['eligibleServiceId']);$where.=" AND students.service_status<>'cancelled' AND EXISTS(SELECT 1 FROM services sv LEFT JOIN service_schools ss ON ss.service_id=sv.id WHERE sv.id=:eligible_service AND sv.agency_id=students.agency_id AND (sv.school_id=students.school_id OR ss.school_id=students.school_id))";$params['eligible_service']=(int)$q['eligibleServiceId'];}
  $limit=min(max((int)($q['limit']??50),1),200);$page=max((int)($q['page']??1),1);$off=($page-1)*$limit;
  $pdo=Connection::get();$count=$pdo->prepare("SELECT COUNT(*) FROM {$c['table']} WHERE $where");$count->execute($params);
  $select='*';
  if($resource==='ledger')$select="ledger_entries.*,
   CASE ledger_entries.party_type
    WHEN 'student' THEN (SELECT CONCAT_WS(' ',s.first_name,s.last_name) FROM students s WHERE s.id=ledger_entries.party_id AND s.agency_id=ledger_entries.agency_id)
    WHEN 'driver' THEN (SELECT CONCAT_WS(' ',d.first_name,d.last_name) FROM drivers d WHERE d.id=ledger_entries.party_id AND d.agency_id=ledger_entries.agency_id)
    WHEN 'school' THEN (SELECT sc.name FROM schools sc WHERE sc.id=ledger_entries.party_id AND sc.agency_id=ledger_entries.agency_id)
    WHEN 'agency' THEN (SELECT a.name FROM agencies a WHERE a.id=ledger_entries.party_id AND a.id=ledger_entries.agency_id)
   END AS party_name";
  if($resource==='services')$select="services.*,
   (SELECT COALESCE(SUM(st.monthly_fee),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=services.agency_id WHERE ss.service_id=services.id) AS students_monthly_total,
   (SELECT COALESCE(SUM(ROUND(COALESCE(st.monthly_fee,0) *
     (CASE WHEN LOWER(COALESCE(sc.school_type,'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9
       WHEN LOWER(COALESCE(sc.school_type,'')) REGEXP 'ابتدایی|دبستان|primary|elementary' THEN 8.5
       WHEN LOWER(COALESCE(st.grade,'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9
       WHEN LOWER(COALESCE(st.grade,'')) REGEXP 'ابتدایی|دبستان|primary|elementary' OR TRIM(COALESCE(st.grade,'')) IN ('1','2','3','4','5','6','۱','۲','۳','۴','۵','۶') THEN 8.5 ELSE 9 END),0)),0)
    FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=services.agency_id
    LEFT JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
    WHERE ss.service_id=services.id) AS students_annual_total,
   (SELECT COUNT(*) FROM service_students ss WHERE ss.service_id=services.id) AS student_count,
   ROUND((SELECT COALESCE(SUM(st.monthly_fee),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=services.agency_id WHERE ss.service_id=services.id)*0.03,0) AS supervision_deduction,
   ROUND((SELECT COALESCE(SUM(st.monthly_fee),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=services.agency_id WHERE ss.service_id=services.id)*0.01,0) AS coordination_deduction,
   ROUND((SELECT COALESCE(SUM(st.monthly_fee),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=services.agency_id WHERE ss.service_id=services.id)*0.07,0) AS overhead_deduction,
   ROUND((SELECT COALESCE(SUM(st.monthly_fee),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=services.agency_id WHERE ss.service_id=services.id)*0.078,0) AS insurance_deduction";
  if($resource==='vehicles')$select="vehicles.*, (SELECT GROUP_CONCAT(CONCAT_WS(' ',vd.first_name,vd.last_name) ORDER BY vd.id DESC SEPARATOR '، ') FROM drivers vd WHERE vd.vehicle_id=vehicles.id AND vd.agency_id=vehicles.agency_id AND vd.is_active=1) AS driver_name";
  if($resource==='students')$select="students.*,
   (CASE WHEN LOWER(COALESCE((SELECT sc.school_type FROM schools sc WHERE sc.id=students.school_id AND sc.agency_id=students.agency_id LIMIT 1),'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9 WHEN LOWER(COALESCE((SELECT sc.school_type FROM schools sc WHERE sc.id=students.school_id AND sc.agency_id=students.agency_id LIMIT 1),'')) REGEXP 'ابتدایی|دبستان|primary|elementary' THEN 8.5 WHEN LOWER(COALESCE(students.grade,'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9 WHEN LOWER(COALESCE(students.grade,'')) REGEXP 'ابتدایی|دبستان|primary|elementary' OR TRIM(COALESCE(students.grade,'')) IN ('1','2','3','4','5','6','۱','۲','۳','۴','۵','۶') THEN 8.5 ELSE 9 END) AS annual_months,
   ROUND(COALESCE(students.monthly_fee,0) * (CASE WHEN LOWER(COALESCE((SELECT sc.school_type FROM schools sc WHERE sc.id=students.school_id AND sc.agency_id=students.agency_id LIMIT 1),'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9 WHEN LOWER(COALESCE((SELECT sc.school_type FROM schools sc WHERE sc.id=students.school_id AND sc.agency_id=students.agency_id LIMIT 1),'')) REGEXP 'ابتدایی|دبستان|primary|elementary' THEN 8.5 WHEN LOWER(COALESCE(students.grade,'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9 WHEN LOWER(COALESCE(students.grade,'')) REGEXP 'ابتدایی|دبستان|primary|elementary' OR TRIM(COALESCE(students.grade,'')) IN ('1','2','3','4','5','6','۱','۲','۳','۴','۵','۶') THEN 8.5 ELSE 9 END),0) AS annual_fee,
   (SELECT CONCAT(d.first_name,' ',d.last_name) FROM service_students ss JOIN services sv ON sv.id=ss.service_id JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=students.agency_id WHERE ss.student_id=students.id AND sv.agency_id=students.agency_id AND sv.is_active=1 LIMIT 1) driver_name,
   (SELECT CONCAT(d.first_name,' ',d.last_name) FROM school_service_import_fees f JOIN drivers d ON d.id=f.driver_id AND d.agency_id=f.agency_id
    WHERE f.agency_id=students.agency_id AND f.student_national_code=students.national_code AND f.period='Mehr'
      AND f.academic_year=COALESCE(NULLIF(REPLACE(students.academic_year,'/','-'),''),'1405-1406') LIMIT 1) mehr_driver_name,
   (SELECT f.driver_id FROM school_service_import_fees f
    WHERE f.agency_id=students.agency_id AND f.student_national_code=students.national_code AND f.period='Mehr'
      AND f.academic_year=COALESCE(NULLIF(REPLACE(students.academic_year,'/','-'),''),'1405-1406') LIMIT 1) mehr_driver_id,
   (SELECT COALESCE(SUM(i.amount),0) FROM invoices i WHERE i.party_type='student' AND i.party_id=students.id AND i.agency_id=students.agency_id AND i.status<>'cancelled') invoice_total,
   (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id WHERE i.party_type='student' AND i.party_id=students.id AND p.agency_id=students.agency_id) paid_total";
  $ordering=self::listOrdering($resource,$q);
  $s=$pdo->prepare("SELECT $select FROM {$c['table']} WHERE $where ORDER BY $ordering LIMIT $limit OFFSET $off");$s->execute($params);
   $total=(int)$count->fetchColumn();$items=$s->fetchAll();if($resource==='services')foreach($items as &$item)self::attachServicePayable($item);unset($item);return ['items'=>$items,'pagination'=>['page'=>$page,'limit'=>$limit,'total'=>$total,'pages'=>(int)ceil($total/$limit)]];
 }
 private static function attachServicePayable(array &$row): void {
  $gross=max(0,(float)($row['students_monthly_total']??0));
  $parts=OperationsService::driverDeductions($gross,(int)($row['agency_id']??0));
  $row['driver_gross_monthly']=$gross;
  $row['driver_net_monthly']=$parts['net_earned'];
  $row['driver_deduction_total']=$parts['total_deductions'];
  $row['driver_deduction_rate']=$parts['deduction_rate'];
  foreach(['supervision_deduction','coordination_deduction','overhead_deduction','insurance_deduction','supervision_rate','coordination_rate','overhead_rate','insurance_rate'] as $field)$row[$field]=$parts[$field];
 }
 public static function serviceFilters(array $query,int $uid): array {
  $where=[];$params=[];
  foreach(['driverId'=>'driver_id','packId'=>'pack_id','status'=>'status','active'=>'is_active','shift'=>'shift'] as $key=>$column){
   $value=$query[$key]??'';
   if(!is_scalar($value)||trim((string)$value)==='')continue;
   $where[]="services.$column=:service_$column";$params["service_$column"]=trim((string)$value);
  }
  $school=$query['schoolId']??'';
  if(is_scalar($school)&&ctype_digit((string)$school)&&(int)$school>0){
   $where[]='(services.school_id=:main_school OR EXISTS (SELECT 1 FROM service_schools ss WHERE ss.service_id=services.id AND ss.school_id=:linked_school))';
   $params['main_school']=$params['linked_school']=(int)$school;
  }
  $search=$query['search']??'';
  if(is_scalar($search)&&trim((string)$search)!==''){
   $term='%'.strtr(trim((string)$search),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
   $where[]="(services.title LIKE :service_title ESCAPE '!' OR services.code LIKE :service_code ESCAPE '!'
    OR EXISTS (SELECT 1 FROM drivers d WHERE d.id=services.driver_id AND d.agency_id=services.agency_id AND CONCAT_WS(' ',d.first_name,d.last_name) LIKE :service_driver ESCAPE '!')
    OR EXISTS (SELECT 1 FROM schools sc WHERE sc.agency_id=services.agency_id AND sc.name LIKE :service_school ESCAPE '!'
     AND (sc.id=services.school_id OR EXISTS (SELECT 1 FROM service_schools ss WHERE ss.service_id=services.id AND ss.school_id=sc.id))))";
   foreach(['service_title','service_code','service_driver','service_school'] as $name)$params[$name]=$term;
  }
  $student=$query['studentSearch']??'';
  if(is_string($student)&&trim($student)!==''){
   Authorization::requirePermission($uid,'students.view');
   $term='%'.strtr(trim($student),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
   $where[]="EXISTS (SELECT 1 FROM service_students sm JOIN students st ON st.id=sm.student_id
    WHERE sm.service_id=services.id AND st.agency_id=services.agency_id
     AND (CONCAT_WS(' ',st.first_name,st.last_name) LIKE :student_name ESCAPE '!'
          OR st.national_code LIKE :student_code ESCAPE '!'))";
   $params['student_name']=$params['student_code']=$term;
  }
  return [$where,$params];
 }
 public static function workDateFilters(array $query): array {
  $where=[];$params=[];
  foreach(['dateFrom'=>'>=','dateTo'=>'<='] as $key=>$operator){
   $date=$query[$key]??'';
   if($date==='')continue;
   if(!is_string($date)||!OperationsService::validDate($date))
    throw new AuthException(422,'تاریخ کارکرد معتبر نیست.','VALIDATION_ERROR');
   $where[]="work_date $operator :work_$key";$params["work_$key"]=$date;
  }
  if(isset($params['work_dateFrom'],$params['work_dateTo'])&&$params['work_dateFrom']>$params['work_dateTo'])
   throw new AuthException(422,'ابتدای بازهٔ کارکرد باید پیش از پایان آن باشد.','VALIDATION_ERROR');
  return [$where,$params];
 }
 public static function get(int $uid,int $aid,string $resource,int $id): array {
  $c=self::cfg($resource);self::scope($uid,$aid,$c['permission']);
  $s=Connection::get()->prepare('SELECT * FROM '.$c['table'].' WHERE id=:id AND agency_id=:a');$s->execute(['id'=>$id,'a'=>$aid]);
  $row=$s->fetch();if(!$row)throw new AuthException(404,'رکورد پیدا نشد.','NOT_FOUND');
  if($resource==='services'){
   $summary=Connection::get()->prepare("SELECT COALESCE(SUM(st.monthly_fee),0) AS students_monthly_total FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=:agency WHERE ss.service_id=:service");
   $summary->execute(['agency'=>$aid,'service'=>$id]);$row=array_merge($row,$summary->fetch()?:[]);
   self::attachServicePayable($row);
  }
  if($resource==='students'){
   $annual=Connection::get()->prepare("SELECT (CASE WHEN LOWER(COALESCE((SELECT sc.school_type FROM schools sc WHERE sc.id=students.school_id AND sc.agency_id=students.agency_id LIMIT 1),'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9 WHEN LOWER(COALESCE((SELECT sc.school_type FROM schools sc WHERE sc.id=students.school_id AND sc.agency_id=students.agency_id LIMIT 1),'')) REGEXP 'ابتدایی|دبستان|primary|elementary' THEN 8.5 WHEN LOWER(COALESCE(students.grade,'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9 WHEN LOWER(COALESCE(students.grade,'')) REGEXP 'ابتدایی|دبستان|primary|elementary' OR TRIM(COALESCE(students.grade,'')) IN ('1','2','3','4','5','6','۱','۲','۳','۴','۵','۶') THEN 8.5 ELSE 9 END) AS annual_months, ROUND(COALESCE(students.monthly_fee,0) * (CASE WHEN LOWER(COALESCE((SELECT sc.school_type FROM schools sc WHERE sc.id=students.school_id AND sc.agency_id=students.agency_id LIMIT 1),'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9 WHEN LOWER(COALESCE((SELECT sc.school_type FROM schools sc WHERE sc.id=students.school_id AND sc.agency_id=students.agency_id LIMIT 1),'')) REGEXP 'ابتدایی|دبستان|primary|elementary' THEN 8.5 WHEN LOWER(COALESCE(students.grade,'')) REGEXP 'متوسطه|دبیرستان|secondary|high.school' THEN 9 WHEN LOWER(COALESCE(students.grade,'')) REGEXP 'ابتدایی|دبستان|primary|elementary' OR TRIM(COALESCE(students.grade,'')) IN ('1','2','3','4','5','6','۱','۲','۳','۴','۵','۶') THEN 8.5 ELSE 9 END),0) AS annual_fee FROM students WHERE students.id=:id AND students.agency_id=:a");
   $annual->execute(['id'=>$id,'a'=>$aid]);$row=array_merge($row,$annual->fetch()?:[]);
  }
  if($resource==='ledger'){
   $p=Connection::get()->prepare("SELECT
    CASE le.party_type
     WHEN 'student' THEN (SELECT NULLIF(TRIM(CONCAT_WS(' ',st.first_name,st.last_name)),'') FROM students st WHERE st.id=le.party_id AND st.agency_id=le.agency_id)
     WHEN 'driver' THEN (SELECT NULLIF(TRIM(CONCAT_WS(' ',dr.first_name,dr.last_name)),'') FROM drivers dr WHERE dr.id=le.party_id AND dr.agency_id=le.agency_id)
     WHEN 'school' THEN (SELECT sc.name FROM schools sc WHERE sc.id=le.party_id AND sc.agency_id=le.agency_id)
     WHEN 'agency' THEN (SELECT ag.name FROM agencies ag WHERE ag.id=le.party_id AND ag.id=le.agency_id)
    END AS party_name,
    NULLIF(TRIM(CONCAT_WS(' ',u.first_name,u.last_name)),'') AS creator_name,
    u.username AS creator_username
    FROM ledger_entries le LEFT JOIN users u ON u.id=le.created_by
    WHERE le.id=:id AND le.agency_id=:a");
   $p->execute(['id'=>$id,'a'=>$aid]);
   $row=array_merge($row,$p->fetch()?:[]);
  }
  return $row;
 }
 public static function save(int $uid,int $aid,string $resource,array $b,?int $id=null): array {
  if($resource==='accounting-documents')return AccountingJournal::save($uid,$aid,$b,$id);
  return OperationsService::transaction(function()use($uid,$aid,$resource,$b,$id){
   if($resource!=='services'||$id!==null||!array_key_exists('schoolIds',$b))return self::saveRecord($uid,$aid,$resource,$b,$id);
   Authorization::requirePermission($uid,'students.view');
   Authorization::requirePermission($uid,'services.manage');
   Authorization::requireAgencyAccess($uid,$aid);
   $schools=OperationsService::ids($b['schoolIds']);$students=OperationsService::ids($b['studentIds']??[]);
   if(!$schools)throw new AuthException(422,'حداقل یک مدرسه برای سرویس انتخاب کنید.','SCHOOL_REQUIRED');
   if(!$students)throw new AuthException(422,'حداقل یک دانش‌آموز برای سرویس انتخاب کنید.','STUDENTS_REQUIRED');
   $capacity=(int)($b['capacity']??4);
   if($capacity<1||$capacity>200||count($students)>$capacity)throw new AuthException(422,'تعداد دانش‌آموزان از ظرفیت معتبر سرویس بیشتر است.','CAPACITY_EXCEEDED');
   $driverIds=OperationsService::ids([$b['driverId']??null]);
   if(count($driverIds)!==1)throw new AuthException(422,'راننده سرویس را انتخاب کنید.','DRIVER_REQUIRED');
   $driverId=$driverIds[0];
   $pdo=Connection::get();
   $driver=$pdo->prepare("SELECT v.capacity FROM drivers d LEFT JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=d.agency_id AND v.is_active=1
    WHERE d.id=? AND d.agency_id=? AND d.is_active=1 AND d.status='active' FOR UPDATE");
   $driver->execute([$driverId,$aid]);
   $vehicleCapacity=$driver->fetchColumn();
   if($vehicleCapacity===false)throw new AuthException(422,'راننده تأییدشده و فعال را انتخاب کنید.','DRIVER_NOT_ELIGIBLE');
   if($vehicleCapacity!==null&&(int)($b['capacity']??4)>(int)$vehicleCapacity)
    throw new AuthException(422,'ظرفیت سرویس از ظرفیت ثبت‌شده خودرو بیشتر است.','CAPACITY_EXCEEDED');
   $b['schoolId']=$schools[0];$b['status']='active';
   $service=self::saveRecord($uid,$aid,$resource,$b,null);
   OperationsService::setServiceSchools($uid,$aid,(int)$service['id'],['schoolIds'=>$schools]);
   sort($students);foreach($students as $student)self::assignStudent($uid,$aid,(int)$service['id'],$student);
   if ($students && !array_key_exists('monthlyFee', $b)) {
    $grossQuery=$pdo->prepare('SELECT COALESCE(SUM(st.monthly_fee),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=? WHERE ss.service_id=?');
    $grossQuery->execute([$aid,$service['id']]);
    $net=OperationsService::driverDeductions((float)$grossQuery->fetchColumn(),$aid)['net_earned'];
    $pdo->prepare('UPDATE services SET monthly_fee=? WHERE id=? AND agency_id=?')->execute([$net,$service['id'],$aid]);
    $service=self::get($uid,$aid,'services',(int)$service['id']);
   }
   return $service;
  });
 }
 private static function saveRecord(int $uid,int $aid,string $resource,array $b,?int $id=null): array {
  $c=self::cfg($resource); Authorization::requirePermission($uid,$c['permission'].'.manage'); Authorization::requireAgencyAccess($uid,$aid);
  $pdo=Connection::get();$data=[];foreach($c['fields'] as $f){$camel=preg_replace_callback('/_([a-z])/',fn($m)=>strtoupper($m[1]),$f);if(array_key_exists($camel,$b))$data[$f]=$b[$camel];elseif(array_key_exists($f,$b))$data[$f]=$b[$f];}
  foreach($data as $f=>$v){if(is_array($v)||is_object($v))throw new AuthException(422,'مقدار ورودی نامعتبر است.','VALIDATION_ERROR');}
  if($resource==='vehicles'&&!in_array('owner_name',thisColumns('vehicles',$pdo),true))
   throw new AuthException(503,'برای ثبت یا ویرایش مالک خودرو، مهاجرت دیتابیس 0029_vehicle_owner_name.sql باید اجرا شود.','VEHICLE_SCHEMA_UPDATE_REQUIRED');
  if($id)FinancialPosting::assertEditable($aid,$resource,$id);
  $existing=$id?self::get($uid,$aid,$resource,$id):[];
  if($id&&$resource==='messages'&&$existing['status']!=='queued')throw new AuthException(409,'پیام پردازش‌شده قابل ویرایش یا ارسال مجدد از این فرم نیست.','MESSAGE_LOCKED');
  if($id&&$resource==='payments'&&$existing['method']==='bale')throw new AuthException(409,'پرداخت استعلام‌شده بله قابل ویرایش نیست.','VERIFIED_PAYMENT_LOCKED');
  foreach($data as $f=>&$v){if($v==='')$v=null;}unset($v);
  if($resource==='services'){
   foreach(['agreed_amount','monthly_fee'] as $moneyField){
    if(!array_key_exists($moneyField,$data)||$data[$moneyField]===null)continue;
    $value=(string)$data[$moneyField];
    // Decimal SQL columns return strings such as "1250000.00" on edit.
    // Accept only zero fractional parts; never silently round actual fractions.
    if(!preg_match('/^[0-9]{1,15}(?:\\.0{1,2})?$/D',$value))
     throw new AuthException(422,'مبلغ سرویس باید به ریال صحیح و بدون اعشار وارد شود.','INVALID_SERVICE_AMOUNT');
    $data[$moneyField]=str_contains($value,'.')?substr($value,0,strpos($value,'.')):$value;
   }
  }
  if($resource==='students'&&array_key_exists('tariff_distance_km',$data)&&$data['tariff_distance_km']!==null){
   $raw=(string)$data['tariff_distance_km'];
   $raw=strtr($raw,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.']);
   if(!is_numeric($raw)||!is_finite((float)$raw)||(float)$raw<=0||(float)$raw>1000)throw new AuthException(422,'مسافت تعرفه باید کیلومتر معتبر و مثبت باشد.','VALIDATION_ERROR');
   $data['tariff_distance_km']=max(1,(int)round((float)$raw));
  }
  if($resource==='drivers'&&array_key_exists('iban',$data)&&$data['iban']!==null){
   $data['iban']=IranBankDetails::normalize((string)$data['iban']);
   if(!PayrollDocuments::validIban($data['iban']))throw new AuthException(422,'شماره شبای راننده معتبر نیست.','INVALID_BANK_DETAILS');
   $bank=IranBankDetails::bankName($data['iban']);
   if($bank!==null)$data['bank_name']=$bank;
   // The 19-digit BBAN is bank-specific; do not fabricate a deposit account number.
  }
  $defaults=['students'=>['nationality'=>'iranian','service_status'=>'no_request','monthly_fee'=>0],'drivers'=>['status'=>'pending','is_representative'=>0],'services'=>['capacity'=>4,'status'=>'draft','monthly_fee'=>0,'agreed_amount'=>0],'packs'=>['status'=>'draft','capacity'=>0],'contracts'=>['status'=>'draft','amount'=>0],'invoices'=>['status'=>'open','amount'=>0],'driver-work'=>['status'=>'pending','amount'=>0],'school-charges'=>['status'=>'open','amount'=>0],'service-charges'=>['status'=>'open','amount'=>0],'messages'=>['status'=>'queued','channel'=>'internal'],'driver-changes'=>['status'=>'pending'],'wallets'=>['balance'=>0],'accounting-documents'=>['status'=>'draft','total_amount'=>0]];
  foreach($defaults[$resource]??[] as $f=>$default)if(array_key_exists($f,$data)&&$data[$f]===null)$data[$f]=$default;
  $required=['schools'=>['name'],'students'=>['school_id','first_name','last_name','mobile'],'drivers'=>['first_name','last_name'],'vehicles'=>['plate'],'services'=>['title'],'packs'=>['title'],'contracts'=>['title','party_type'],'driver-work'=>['driver_id','work_date'],'driver-payments'=>['driver_id','payment_date','amount'],'school-charges'=>['school_id','title'],'service-charges'=>['service_id','title'],'invoices'=>['title'],'payments'=>['payment_date','amount'],'accounts'=>['code','title'],'accounting-documents'=>['document_no','document_date'],'wallets'=>['owner_type'],'messages'=>['body'],'driver-changes'=>['service_id','new_driver_id'],'ledger'=>['party_type','party_id','direction','amount','entry_date'],'driver-ratings'=>['driver_id','score'],'parent-reports'=>['student_id','subject','body']];
  $merged=array_merge($existing,$data);
  if($resource==='contracts'){
   $start=$merged['start_date']??null;$end=$merged['end_date']??null;
   foreach([$start,$end] as $contractDate)
    if($contractDate!==null&&$contractDate!==''&&(!is_string($contractDate)||!OperationsService::validDate($contractDate)))
     throw new AuthException(422,'تاریخ شروع و پایان قرارداد باید معتبر باشند.','INVALID_CONTRACT_DATES');
   if($start!==null&&$end!==null&&$start!==''&&$end!==''&&$end<$start)
    throw new AuthException(422,'تاریخ پایان قرارداد نباید قبل از تاریخ شروع باشد.','INVALID_CONTRACT_DATES');
   if(array_key_exists('amount',$data)&&$data['amount']!==null){
    $amount=(string)$data['amount'];
    if(!preg_match('/^[0-9]{1,15}(?:\\.0{1,2})?$/D',$amount))
     throw new AuthException(422,'مبلغ قرارداد باید به ریال صحیح و بدون اعشار باشد.','INVALID_CONTRACT_AMOUNT');
    $data['amount']=str_contains($amount,'.')?substr($amount,0,strpos($amount,'.')):$amount;
   }
  }
  if($resource==='students' && (array_key_exists('tariff_distance_km',$data)||array_key_exists('tariff_vehicle_class',$data)||array_key_exists('school_id',$data)||array_key_exists('academic_year',$data))){
   $distance=$merged['tariff_distance_km']??null;$class=$merged['tariff_vehicle_class']??null;
   if($distance!==null&&(!is_numeric($distance)||(float)$distance<=0||(float)$distance>1000))throw new AuthException(422,'مسافت تعرفه نامعتبر است.','VALIDATION_ERROR');
   if($class!==null&&!in_array((string)$class,['1','2','3'],true))throw new AuthException(422,'کلاس تعرفه نامعتبر است.','VALIDATION_ERROR');
   $fee=AgencySchoolTariffs::quote($pdo,$aid,(int)($merged['school_id']??0),(string)(($merged['academic_year']??null) ?: AgencySchoolTariffs::YEAR),$distance,$class);
   if($fee!==null){$data['monthly_fee']=$fee;$data['tariff_calculated_at']=date('Y-m-d H:i:s');}
  }
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
    if(str_starts_with((string)$existing['code'],'mehr1405-d')){
     $q=$pdo->prepare("SELECT v.capacity FROM drivers d JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=d.agency_id AND v.is_active=1
      WHERE d.id=? AND d.agency_id=? AND d.is_active=1 AND d.status='active' FOR UPDATE");
     $q->execute([(int)$merged['driver_id'],$aid]);$vehicleCapacity=$q->fetchColumn();
     if($vehicleCapacity===false)throw new AuthException(422,'برای فعال‌سازی پیش‌نویس مهر، راننده تأییدشده و خودروی فعال لازم است.','DRIVER_NOT_ELIGIBLE');
     if((int)$merged['capacity']>(int)$vehicleCapacity||$count>(int)$merged['capacity'])
      throw new AuthException(409,'ظرفیت سرویس یا تعداد اعضا با ظرفیت خودرو سازگار نیست.','CAPACITY_EXCEEDED');
     $q=$pdo->prepare('SELECT s.agency_id,s.school_id,s.is_active,s.service_status FROM service_students ss JOIN students s ON s.id=ss.student_id WHERE ss.service_id=? ORDER BY s.id FOR UPDATE');
     $q->execute([$id]);
     foreach($q->fetchAll() as $student){
      if((int)$student['agency_id']!==$aid||(int)$student['school_id']!==(int)$merged['school_id']||!(int)$student['is_active']||$student['service_status']==='cancelled')
       throw new AuthException(409,'مدرسه یا وضعیت یکی از دانش‌آموزان پیش‌نویس تغییر کرده است.','ASSIGNMENT_CONFLICT');
     }
     $data['status']='active';
    }
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
  Authorization::requirePermission($uid,'services.view');Authorization::requireAgencyAccess($uid,$aid);$q=Connection::get()->prepare('SELECT st.id,st.uuid,st.first_name,st.last_name,st.grade,st.national_code,st.tariff_distance_km,st.tariff_vehicle_class,st.monthly_fee,st.grade AS student_grade,sc.school_type AS school_type,ss.pickup_address,ss.pickup_order FROM service_students ss JOIN students st ON st.id=ss.student_id LEFT JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id JOIN services s ON s.id=ss.service_id WHERE ss.service_id=:s AND s.agency_id=:a ORDER BY ss.pickup_order,st.last_name,st.first_name');$q->execute(['s'=>$serviceId,'a'=>$aid]);return $q->fetchAll();
 }
 public static function delete(int $uid,int $aid,string $resource,int $id): void {
  $c=self::cfg($resource);Authorization::requirePermission($uid,$c['permission'].'.manage');Authorization::requireAgencyAccess($uid,$aid);self::get($uid,$aid,$resource,$id);$pdo=Connection::get();$columns=thisColumns($c['table'],$pdo);
  if(!in_array('is_active',$columns,true))throw new AuthException(422,'این سابقه قابل غیرفعال‌سازی نیست؛ وضعیت آن را ویرایش کنید.','DELETE_UNSUPPORTED');
  OperationsService::transaction(function(\PDO $pdo)use($c,$id,$aid,$resource,$uid){FinancialPosting::assertEditable($aid,$resource,$id);$pdo->prepare("UPDATE {$c['table']} SET is_active=0 WHERE id=:id AND agency_id=:agency")->execute(['id'=>$id,'agency'=>$aid]);if($resource==='services')self::syncStudentStatus($pdo,$aid,$id,false);if($resource==='students')self::releaseStudent($pdo,$uid,$aid,$id);});self::activity($pdo,$aid,$uid,'deactivate',$c['table'],$id);
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

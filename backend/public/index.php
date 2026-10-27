<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
use ServiceYar\Auth\AuthException; use ServiceYar\Auth\AuthService; use ServiceYar\Auth\Authorization; use ServiceYar\Http\Request; use ServiceYar\Http\Response; use ServiceYar\Agency\AgencyService; use ServiceYar\Settings\SettingsService; use ServiceYar\Holiday\HolidayService; use ServiceYar\Users\UserService; use ServiceYar\Domain\DomainService; use ServiceYar\Database\Connection;
use ServiceYar\Domain\OperationsService;
use ServiceYar\Domain\StudentImport;
use ServiceYar\Domain\PayrollDocuments;
use ServiceYar\Domain\AccountingJournal;
use ServiceYar\Domain\FinancialRules;
use ServiceYar\Reports\ExportService;
use ServiceYar\Reports\ReportService;
use ServiceYar\Monitoring\MonitoringService;
use ServiceYar\AI\AiService;
use ServiceYar\Integrations\BalePaymentService;
use ServiceYar\Integrations\MapService;

$method=$_SERVER['REQUEST_METHOD']??'GET';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$requestId=bin2hex(random_bytes(8));header('X-Request-ID: '.$requestId);
function agencyId(array $session): int { $raw=$_GET['agencyId']??null; $id=$raw!==null?(int)$raw:AgencyService::currentId($session['id']); if($id<1)throw new AuthException(422,'شناسه شرکت نامعتبر است.','VALIDATION_ERROR'); Authorization::requireAgencyAccess($session['id'],$id); return $id; }
try {
 if($method==='OPTIONS'){http_response_code(204);exit;}
 if($method==='POST'&&preg_match('#^/api/v1/integrations/bale/webhook/([a-zA-Z0-9_-]+)$#',$path,$m)){
  $secret=(string)config('BALE_WEBHOOK_SECRET','');if(strlen($secret)<32||!hash_equals($secret,$m[1]))throw new AuthException(403,'وب‌هوک مجاز نیست.','FORBIDDEN');
  Response::json(['ok'=>true,'data'=>BalePaymentService::webhook(Request::json())]);
 }
 if($method==='GET'&&($path==='/api/health'||$path==='/api/v1/health'))Response::json(['ok'=>true,'service'=>config('APP_NAME','ServiceYar'),'environment'=>config('APP_ENV','local'),'version'=>'0.3.0','time'=>gmdate('c')]);
 if($method==='GET'&&$path==='/api/v1/health/ready'){try{Connection::get()->query('SELECT 1');Response::json(['ok'=>true,'data'=>['api'=>true,'database'=>true,'time'=>gmdate('c')]]);}catch(\Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'NOT_READY','message'=>'پایگاه داده آماده نیست.']],503);}}
 $auth=new AuthService();
 if($method==='POST'&&$path==='/api/v1/auth/login'){ $b=Request::json();Response::json(['ok'=>true,'data'=>$auth->login((string)($b['username']??''),(string)($b['password']??''))]); }
 if($method==='POST'&&$path==='/api/v1/auth/logout'){ $s=$auth->current();$auth->requireCsrf($s);$auth->logout();Response::json(['ok'=>true,'data'=>['loggedOut'=>true]]); }
 if($method==='GET'&&$path==='/api/v1/auth/me'){ $s=$auth->current();$rs=Connection::get()->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=:u ORDER BY r.id');$rs->execute(['u'=>$s['id']]);Response::json(['ok'=>true,'data'=>['user'=>['id'=>$s['id'],'uuid'=>$s['uuid'],'username'=>$s['username'],'firstName'=>$s['firstName'],'lastName'=>$s['lastName']],'roles'=>$rs->fetchAll(\PDO::FETCH_COLUMN),'permissions'=>Authorization::permissions($s['id']),'agencies'=>AgencyService::sessionList($s['id']),'csrfToken'=>$s['csrfToken']]]); }
 if($method==='GET'&&$path==='/api/v1/auth/csrf'){ $s=$auth->current();Response::json(['ok'=>true,'data'=>['csrfToken'=>$s['csrfToken']]]); }
 $s=$auth->current(); $aid=null;

 if($method==='GET'&&$path==='/api/v1/agencies')Response::json(['ok'=>true,'data'=>AgencyService::list($s['id'])]);
 if($method==='POST'&&$path==='/api/v1/agencies'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AgencyService::create($s['id'],Request::json())],201); }
 if(preg_match('#^/api/v1/agencies/(\d+)$#',$path,$m)){ $id=(int)$m[1];if($method==='PUT'||$method==='PATCH'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AgencyService::update($s['id'],$id,Request::json())]);} }
 if(preg_match('#^/api/v1/agencies/(\d+)/users$#',$path,$m)){ $id=(int)$m[1];if($method==='GET')Response::json(['ok'=>true,'data'=>AgencyService::users($s['id'],$id)]);if($method==='PUT'){ $auth->requireCsrf($s);$b=Request::json();Response::json(['ok'=>true,'data'=>AgencyService::assignUsers($s['id'],$id,is_array($b['userIds']??null)?$b['userIds']:[])]);} }
 if(preg_match('#^/api/v1/agencies/(\d+)$#',$path,$m)&&$method==='DELETE'){ $auth->requireCsrf($s);AgencyService::deactivate($s['id'],(int)$m[1]);Response::json(['ok'=>true,'data'=>['deactivated'=>true]]);}

 if($method==='GET'&&$path==='/api/v1/admin/overview'){
  if(!Authorization::isSuperAdmin($s['id'])) throw new AuthException(403,'این بخش فقط برای مدیر کل سامانه است.','SUPER_ADMIN_REQUIRED');
  $pdo=Connection::get();$counts=[];
  foreach(['agencies','users','schools','students','drivers','vehicles','services'] as $table){$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
  $roles=$pdo->query('SELECT r.id,r.name,r.title,COUNT(DISTINCT ur.user_id) user_count,COUNT(DISTINCT rp.permission_id) permission_count FROM roles r LEFT JOIN user_roles ur ON ur.role_id=r.id LEFT JOIN role_permissions rp ON rp.role_id=r.id GROUP BY r.id,r.name,r.title ORDER BY r.id')->fetchAll();
  $permissions=$pdo->query('SELECT id,name,title FROM permissions ORDER BY name')->fetchAll();
  $users=$pdo->query('SELECT u.id,u.username,u.first_name,u.last_name,u.is_active,u.last_login_at,GROUP_CONCAT(DISTINCT r.title ORDER BY r.id SEPARATOR ", ") roles,GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") agencies FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id LEFT JOIN agency_users au ON au.user_id=u.id LEFT JOIN agencies a ON a.id=au.agency_id GROUP BY u.id ORDER BY u.id DESC LIMIT 100')->fetchAll();
  Response::json(['ok'=>true,'data'=>['counts'=>$counts,'roles'=>$roles,'permissions'=>$permissions,'agencies'=>AgencyService::list($s['id']),'users'=>$users]]);
 }
 $aid=agencyId($s);
 if($method==='POST'&&$path==='/api/v1/operations/financial-preview'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>FinancialRules::preview($s['id'],$aid,Request::json())]);}
 if(preg_match('#^/api/v1/accounting/journal/(\d+)(?:/(approve))?$#',$path,$m)){
  if($method==='GET'&&empty($m[2]))Response::json(['ok'=>true,'data'=>AccountingJournal::get($s['id'],$aid,(int)$m[1])]);
  if($method==='PUT'&&empty($m[2])){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AccountingJournal::save($s['id'],$aid,Request::json(),(int)$m[1])]);}
  if($method==='POST'&&($m[2]??'')==='approve'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AccountingJournal::approve($s['id'],$aid,(int)$m[1])]);}
 }
 if($method==='POST'&&$path==='/api/v1/accounting/journal'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AccountingJournal::save($s['id'],$aid,Request::json())],201);}
 if(preg_match('#^/api/v1/services/(\d+)/schools$#',$path,$m)){
  if($method==='GET')Response::json(['ok'=>true,'data'=>OperationsService::serviceSchools($s['id'],$aid,(int)$m[1])]);
  if($method==='PUT'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>OperationsService::setServiceSchools($s['id'],$aid,(int)$m[1],Request::json())]);}
 }
 if($method==='GET'&&$path==='/api/v1/operations/payroll-documents')Response::json(['ok'=>true,'data'=>PayrollDocuments::list($s['id'],$aid,$_GET)]);
 if($method==='POST'&&$path==='/api/v1/operations/payroll-documents'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>PayrollDocuments::create($s['id'],$aid,Request::json())],201);}
 if(preg_match('#^/api/v1/operations/payroll-documents/(\d+)(?:/(approve|bank))?$#',$path,$m)){
  if($method==='GET'&&($m[2]??'')==='bank')PayrollDocuments::bankCsv($s['id'],$aid,(int)$m[1]);
  if($method==='GET')Response::json(['ok'=>true,'data'=>PayrollDocuments::get($s['id'],$aid,(int)$m[1])]);
  if($method==='PUT'&&empty($m[2])){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>PayrollDocuments::update($s['id'],$aid,(int)$m[1],Request::json())]);}
  if($method==='POST'&&($m[2]??'')==='approve'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>PayrollDocuments::approve($s['id'],$aid,(int)$m[1])]);}
 }
 if($method==='GET'&&$path==='/api/v1/integrations/status'){Authorization::requirePermission($s['id'],'settings.view');Response::json(['ok'=>true,'data'=>['sms'=>\ServiceYar\Integrations\NeginSms::configured(),'bale'=>\ServiceYar\Integrations\BaleClient::configured(),'google'=>config('GOOGLE_MAPS_SERVER_KEY','')!=='','osm'=>true]]);}
 if($method==='POST'&&$path==='/api/v1/maps/route'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>MapService::route($s['id'],$aid,Request::json())]);}
 if($method==='GET'&&preg_match('#^/api/v1/services/(\d+)/map$#',$path,$m))Response::json(['ok'=>true,'data'=>MapService::points($s['id'],$aid,(int)$m[1])]);
 if($method==='POST'&&$path==='/api/v1/integrations/bale/invoices'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>BalePaymentService::create($s['id'],$aid,Request::json())],201);}
 if($method==='GET'&&$path==='/api/v1/integrations/bale/invoices')Response::json(['ok'=>true,'data'=>BalePaymentService::status($s['id'],$aid)]);
 if(preg_match('#^/api/v1/drivers/(\d+)/documents(?:/(\d+))?$#',$path,$m)){
  if($method==='GET')Response::json(['ok'=>true,'data'=>OperationsService::documents($s['id'],$aid,(int)$m[1])]);
  if($method==='POST'||$method==='PUT'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>OperationsService::saveDocument($s['id'],$aid,(int)$m[1],Request::json(),isset($m[2])?(int)$m[2]:null)]);}
 }
 if($method==='GET'&&$path==='/api/v1/monitoring/users')Response::json(['ok'=>true,'data'=>MonitoringService::users($s['id'],$aid)]);
 if($method==='GET'&&$path==='/api/v1/operations/dashboard')Response::json(['ok'=>true,'data'=>OperationsService::summary($s['id'],$aid)]);
 if($method==='GET'&&$path==='/api/v1/operations/schools')Response::json(['ok'=>true,'data'=>OperationsService::schoolReport($s['id'],$aid)]);
 if($method==='GET'&&$path==='/api/v1/operations/payroll')Response::json(['ok'=>true,'data'=>OperationsService::payroll($s['id'],$aid,$_GET)]);
 if($method==='GET'&&$path==='/api/v1/operations/ledger')Response::json(['ok'=>true,'data'=>OperationsService::ledgerReport($s['id'],$aid,$_GET)]);
 if($method==='GET'&&$path==='/api/v1/operations/grouping')Response::json(['ok'=>true,'data'=>OperationsService::grouping($s['id'],$aid,$_GET)]);
 if($method==='GET'&&$path==='/api/v1/operations/distances')Response::json(['ok'=>true,'data'=>OperationsService::distances($s['id'],$aid,$_GET)]);
 if($method==='POST'&&$path==='/api/v1/operations/fees'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>OperationsService::bulkFees($s['id'],$aid,Request::json())]);}
 if($method==='POST'&&$path==='/api/v1/operations/groups'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>OperationsService::createGroup($s['id'],$aid,Request::json())],201);}
 if($method==='POST'&&$path==='/api/v1/operations/messages'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>OperationsService::queueMessages($s['id'],$aid,Request::json())],201);}
 if($method==='POST'&&$path==='/api/v1/operations/import/preview'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>StudentImport::preview($s['id'],$aid,$_FILES['file']??[])]);}
 if($method==='POST'&&$path==='/api/v1/operations/import'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>StudentImport::commit($s['id'],$aid,Request::json())],201);}
 if($method==='POST'&&preg_match('#^/api/v1/operations/driver-changes/(\d+)/review$#',$path,$m)){$auth->requireCsrf($s);$b=Request::json();Response::json(['ok'=>true,'data'=>OperationsService::changeDriver($s['id'],$aid,(int)$m[1],(string)($b['status']??''))]);}
 if($method==='GET'&&$path==='/api/v1/settings')Response::json(['ok'=>true,'data'=>SettingsService::get($s['id'],$aid)]);
 if(($method==='PUT'||$method==='PATCH')&&$path==='/api/v1/settings'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>SettingsService::put($s['id'],$aid,Request::json())]); }
 if($method==='GET'&&$path==='/api/v1/holidays')Response::json(['ok'=>true,'data'=>HolidayService::list($s['id'],$aid)]);
 if($method==='POST'&&$path==='/api/v1/holidays'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>HolidayService::save($s['id'],$aid,Request::json())],201); }
 if(preg_match('#^/api/v1/holidays/(\d+)$#',$path,$m)){ $id=(int)$m[1];if($method==='PUT'||$method==='PATCH'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>HolidayService::save($s['id'],$aid,Request::json(),$id)]);}if($method==='DELETE'){ $auth->requireCsrf($s);HolidayService::delete($s['id'],$aid,$id);Response::json(['ok'=>true,'data'=>['deleted'=>true]]);} }

 if($method==='GET'&&$path==='/api/v1/users')Response::json(['ok'=>true,'data'=>UserService::list($s['id'],$aid)]);
 if($method==='POST'&&$path==='/api/v1/users'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>UserService::save($s['id'],$aid,Request::json())],201); }
 if(preg_match('#^/api/v1/users/(\d+)$#',$path,$m)&&($method==='PUT'||$method==='PATCH')){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>UserService::save($s['id'],$aid,Request::json(),(int)$m[1])]);}

 if($method==='GET'&&$path==='/api/v1/reports/export'){ExportService::xlsx($s['id'],$aid,(string)($_GET['resource']??'schools'));}
 if($method==='GET'&&$path==='/api/v1/reports/summary')Response::json(['ok'=>true,'data'=>ReportService::summary($s['id'],$aid)]);
 if($method==='GET'&&$path==='/api/v1/monitoring/activity')Response::json(['ok'=>true,'data'=>MonitoringService::activity($s['id'],$aid)]);
 if($method==='GET'&&preg_match('#^/api/v1/monitoring/services/(\\d+)/locations$#',$path,$mm))Response::json(['ok'=>true,'data'=>MonitoringService::locations($s['id'],$aid,(int)$mm[1])]);
 if($method==='GET'&&$path==='/api/v1/ai/history')Response::json(['ok'=>true,'data'=>AiService::history($s['id'],$aid)]);
 if($method==='POST'&&$path==='/api/v1/ai/conversations'){ $auth->requireCsrf($s);$b=Request::json();Response::json(['ok'=>true,'data'=>AiService::create($s['id'],$aid,(string)($b['title']??'گفتگو'))],201); }
 if($method==='GET'&&$path==='/api/v1/dashboard/summary'){
  Authorization::requirePermission($s['id'],'dashboard.view');$pdo=Connection::get();$counts=[];
  foreach(['schools','students','drivers','services'] as $t){try{$q=$pdo->prepare("SELECT COUNT(*) FROM $t WHERE agency_id=:a AND is_active=1");$q->execute(['a'=>$aid]);$counts[$t]=(int)$q->fetchColumn();}catch(\Throwable){$counts[$t]=0;}}
  Response::json(['ok'=>true,'data'=>['agencyId'=>$aid,'counts'=>$counts]]);
 }

 if(preg_match('#^/api/v1/services/(\\d+)/students$#',$path,$mm)){ $serviceId=(int)$mm[1]; if($method==='GET')Response::json(['ok'=>true,'data'=>DomainService::serviceStudents($s['id'],$aid,$serviceId)]); if($method==='POST'){ $auth->requireCsrf($s);$b=Request::json();DomainService::assignStudent($s['id'],$aid,$serviceId,(int)($b['studentId']??0),isset($b['pickupAddress'])?(string)$b['pickupAddress']:null,isset($b['pickupOrder'])?(int)$b['pickupOrder']:null);Response::json(['ok'=>true,'data'=>['assigned'=>true]],201);} if($method==='DELETE'){ $auth->requireCsrf($s);$studentId=(int)($_GET['studentId']??0);DomainService::removeStudent($s['id'],$aid,$serviceId,$studentId);Response::json(['ok'=>true,'data'=>['removed'=>true]]);} }
 $domainMap=['schools','students','drivers','vehicles','services','packs','contracts','driver-work','driver-payments','school-charges','service-charges','invoices','payments','accounts','accounting-documents','wallets','messages','driver-changes','ledger','driver-ratings','parent-reports'];
 foreach($domainMap as $resource){
  $base='/api/v1/'.$resource;
  if($path===$base&&$method==='GET')Response::json(['ok'=>true,'data'=>DomainService::list($s['id'],$aid,$resource,$_GET)]);
  if($path===$base&&$method==='POST'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>DomainService::save($s['id'],$aid,$resource,Request::json())],201); }
  if(preg_match('#^'.preg_quote($base,'#').'/(\d+)$#',$path,$m)){
   $id=(int)$m[1];if($method==='PUT'||$method==='PATCH'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>DomainService::save($s['id'],$aid,$resource,Request::json(),$id)]);}
   if($method==='GET')Response::json(['ok'=>true,'data'=>DomainService::get($s['id'],$aid,$resource,$id)]);
   if($method==='DELETE'){ $auth->requireCsrf($s);DomainService::delete($s['id'],$aid,$resource,$id);Response::json(['ok'=>true,'data'=>['deleted'=>true]]);}
  }
 }
 Response::json(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'مسیر مورد نظر پیدا نشد.']],404);
 } catch(AuthException $e){Response::json(['ok'=>false,'error'=>['code'=>$e->codeName,'message'=>$e->getMessage()]],$e->status);}
catch(\PDOException $e){error_log('[ServiceYar]['.$requestId.'] PDOException '.$method.' '.$path.' at '.$e->getFile().':'.$e->getLine().' '.$e->getMessage());$message='خطای پایگاه داده. شناسه پیگیری: '.$requestId;$code='DATABASE_ERROR';$status=500;if($e->getCode()==='23000' && str_contains($e->getMessage(),"Duplicate entry") && str_contains($e->getMessage(),"users.username")){$message='این نام کاربری قبلاً ثبت شده است. نام کاربری دیگری انتخاب کنید.';$code='USERNAME_ALREADY_EXISTS';$status=409;}elseif(str_contains($e->getMessage(),'auth_login_attempts')||str_contains($e->getMessage(),'user_sessions')){$message='ساختار ورود در دیتابیس کامل نیست؛ مهاجرت‌های 0006 و 0007 باید با پشتیبان‌گیری و به‌صورت کنترل‌شده اجرا شوند. شناسه پیگیری: '.$requestId;$code='AUTH_SCHEMA_MISSING';}Response::json(['ok'=>false,'error'=>['code'=>$code,'message'=>$message,'requestId'=>$requestId]],$status);}
catch(\Throwable $e){$previous=$e->getPrevious();$dbConnectionFailure=$e instanceof \RuntimeException && $previous instanceof \PDOException;error_log('[ServiceYar]['.$requestId.'] '.get_class($e).' '.$method.' '.$path.' at '.$e->getFile().':'.$e->getLine().' '.$e->getMessage().($previous?' | previous: '.$previous->getMessage():'').' TRACE '.$e->getTraceAsString());if($dbConnectionFailure){Response::json(['ok'=>false,'error'=>['code'=>'DATABASE_CONNECTION_FAILED','message'=>'اتصال به پایگاه داده برقرار نشد؛ نام دیتابیس و نام کاربری و رمز عبور تنظیمات DB را بررسی کنید. شناسه پیگیری: '.$requestId,'requestId'=>$requestId]],500);}Response::json(['ok'=>false,'error'=>['code'=>'INTERNAL_ERROR','message'=>'خطای داخلی سامانه. شناسه پیگیری: '.$requestId,'requestId'=>$requestId]],500);}

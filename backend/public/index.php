<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
use ServiceYar\Auth\AuthException; use ServiceYar\Auth\AuthService; use ServiceYar\Auth\Authorization; use ServiceYar\Http\Request; use ServiceYar\Http\Response; use ServiceYar\Agency\AgencyService; use ServiceYar\Settings\SettingsService; use ServiceYar\Holiday\HolidayService; use ServiceYar\Users\UserService; use ServiceYar\Domain\DomainService; use ServiceYar\Database\Connection;

$method=$_SERVER['REQUEST_METHOD']??'GET';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$requestId=bin2hex(random_bytes(8));header('X-Request-ID: '.$requestId);
function agencyId(array $session): int { $raw=$_GET['agencyId']??null; $id=$raw!==null?(int)$raw:AgencyService::currentId($session['id']); if($id<1)throw new AuthException(422,'شناسه سازمان نامعتبر است.','VALIDATION_ERROR'); Authorization::requireAgencyAccess($session['id'],$id); return $id; }
try {
 if($method==='OPTIONS'){http_response_code(204);exit;}
 if($method==='GET'&&($path==='/api/health'||$path==='/api/v1/health'))Response::json(['ok'=>true,'service'=>config('APP_NAME','ServiceYar'),'environment'=>config('APP_ENV','local'),'version'=>'0.3.0','time'=>gmdate('c')]);
 if($method==='GET'&&$path==='/api/v1/health/ready'){try{Connection::get()->query('SELECT 1');Response::json(['ok'=>true,'data'=>['api'=>true,'database'=>true,'time'=>gmdate('c')]]);}catch(\Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'NOT_READY','message'=>'پایگاه داده آماده نیست.']],503);}}
 $auth=new AuthService();
 if($method==='POST'&&$path==='/api/v1/auth/login'){ $b=Request::json();Response::json(['ok'=>true,'data'=>$auth->login((string)($b['username']??''),(string)($b['password']??''))]); }
 if($method==='POST'&&$path==='/api/v1/auth/logout'){ $s=$auth->current();$auth->requireCsrf($s);$auth->logout();Response::json(['ok'=>true,'data'=>['loggedOut'=>true]]); }
 if($method==='GET'&&$path==='/api/v1/auth/me'){ $s=$auth->current();$rs=Connection::get()->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=:u ORDER BY r.id');$rs->execute(['u'=>$s['id']]);Response::json(['ok'=>true,'data'=>['user'=>['id'=>$s['id'],'uuid'=>$s['uuid'],'username'=>$s['username'],'firstName'=>$s['firstName'],'lastName'=>$s['lastName']],'roles'=>$rs->fetchAll(\PDO::FETCH_COLUMN),'permissions'=>Authorization::permissions($s['id']),'agencies'=>AgencyService::list($s['id']),'csrfToken'=>$s['csrfToken']]]); }
 if($method==='GET'&&$path==='/api/v1/auth/csrf'){ $s=$auth->current();Response::json(['ok'=>true,'data'=>['csrfToken'=>$s['csrfToken']]]); }
 $s=$auth->current(); $aid=null;

 if($method==='GET'&&$path==='/api/v1/agencies')Response::json(['ok'=>true,'data'=>AgencyService::list($s['id'])]);
 if($method==='POST'&&$path==='/api/v1/agencies'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AgencyService::create($s['id'],Request::json())],201); }
 if(preg_match('#^/api/v1/agencies/(\d+)$#',$path,$m)){ $id=(int)$m[1];if($method==='PUT'||$method==='PATCH'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AgencyService::update($s['id'],$id,Request::json())]);} }

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
 $domainMap=['schools','students','drivers','vehicles','services','packs','contracts','driver-work','driver-payments','school-charges','service-charges','invoices','payments','accounts','accounting-documents','wallets','messages','driver-changes'];
 foreach($domainMap as $resource){
  $base='/api/v1/'.$resource;
  if($path===$base&&$method==='GET')Response::json(['ok'=>true,'data'=>DomainService::list($s['id'],$aid,$resource,$_GET)]);
  if($path===$base&&$method==='POST'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>DomainService::save($s['id'],$aid,$resource,Request::json())],201); }
  if(preg_match('#^'.preg_quote($base,'#').'/(\d+)$#',$path,$m)){
   $id=(int)$m[1];if($method==='PUT'||$method==='PATCH'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>DomainService::save($s['id'],$aid,$resource,Request::json(),$id)]);}
   if($method==='DELETE'){ $auth->requireCsrf($s);DomainService::delete($s['id'],$aid,$resource,$id);Response::json(['ok'=>true,'data'=>['deleted'=>true]]);}
  }
 }
 Response::json(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'مسیر مورد نظر پیدا نشد.']],404);
 } catch(AuthException $e){Response::json(['ok'=>false,'error'=>['code'=>$e->codeName,'message'=>$e->getMessage()]],$e->status);}
catch(\PDOException $e){error_log('[ServiceYar]['.$requestId.'] PDOException '.$method.' '.$path.' at '.$e->getFile().':'.$e->getLine().' '.$e->getMessage());$message='خطای پایگاه داده. شناسه پیگیری: '.$requestId;$code='DATABASE_ERROR';if(str_contains($e->getMessage(),'auth_login_attempts')||str_contains($e->getMessage(),'user_sessions')){$message='ساختار ورود در دیتابیس کامل نیست؛ مهاجرت‌های 0006 و 0007 باید با پشتیبان‌گیری و به‌صورت کنترل‌شده اجرا شوند. شناسه پیگیری: '.$requestId;$code='AUTH_SCHEMA_MISSING';}Response::json(['ok'=>false,'error'=>['code'=>$code,'message'=>$message,'requestId'=>$requestId]],500);}
catch(\Throwable $e){$previous=$e->getPrevious();$dbConnectionFailure=$e instanceof \RuntimeException && $previous instanceof \PDOException;error_log('[ServiceYar]['.$requestId.'] '.get_class($e).' '.$method.' '.$path.' at '.$e->getFile().':'.$e->getLine().' '.$e->getMessage().($previous?' | previous: '.$previous->getMessage():'').' TRACE '.$e->getTraceAsString());if($dbConnectionFailure){Response::json(['ok'=>false,'error'=>['code'=>'DATABASE_CONNECTION_FAILED','message'=>'اتصال به پایگاه داده برقرار نشد؛ نام دیتابیس و نام کاربری و رمز عبور تنظیمات DB را بررسی کنید. شناسه پیگیری: '.$requestId,'requestId'=>$requestId]],500);}Response::json(['ok'=>false,'error'=>['code'=>'INTERNAL_ERROR','message'=>'خطای داخلی سامانه. شناسه پیگیری: '.$requestId,'requestId'=>$requestId]],500);}

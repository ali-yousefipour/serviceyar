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
 if($method==='GET'&&$path==='/api/v1/public/overview'){
  // Only aggregate, non-personal counts; no agency/user identifiers exposed.
  try{
   $pdo=Connection::get();$counts=[];
   foreach(['agencies','students','drivers','services'] as $table){
    $counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table.' WHERE is_active=1')->fetchColumn();
   }
   // Registered schools: count physical rows, including inactive records. A catalog
   // entry is not a registered school and must not inflate the homepage metric.
   $schoolStats=$pdo->query('SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END),0) AS active_count, COALESCE(SUM(CASE WHEN is_active=1 THEN 0 ELSE 1 END),0) AS inactive_count FROM schools')->fetch(\PDO::FETCH_ASSOC);
   $counts['schools']=(int)$schoolStats['total'];
   $counts['schoolsActive']=(int)$schoolStats['active_count'];
   $counts['schoolsInactive']=(int)$schoolStats['inactive_count'];
   header('Cache-Control: no-store, max-age=0');
   Response::json(['ok'=>true,'data'=>['counts'=>$counts]]);
  }catch(\Throwable $e){
   error_log('[ServiceYar][public-overview] aggregate query failed: '.$e->getMessage());
   Response::json(['ok'=>false,'error'=>['code'=>'PUBLIC_STATS_UNAVAILABLE','message'=>'آمار عمومی موقتاً در دسترس نیست.']],503);
  }
 }
 // Public school directory. No student, driver, contact or private tenant data exposed.

 // A web SDK key is public by design: restrict it to the site's domain in Neshan.
 // Never expose the private service API key through this route.
 if($method==='GET'&&$path==='/api/v1/public/map-config'){
  try{
   $key=\ServiceYar\Integrations\Neshan::webKey([]);
   header('Cache-Control: public, max-age=300');
   Response::json(['ok'=>true,'data'=>['provider'=>$key!==''?'neshan':'osm','webKey'=>$key]]);
  }catch(\Throwable $e){
   error_log('[ServiceYar][public-map-config] '.$e->getMessage());
   Response::json(['ok'=>true,'data'=>['provider'=>'osm','webKey'=>'']]);
  }
 }

 // Initial map: active transport companies with configured coordinates only.
 if($method==='GET'&&$path==='/api/v1/public/companies'){
  try{
   header('Cache-Control: public, max-age=90');
   Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\PublicExplorer::companies()]);
  }catch(\Throwable $e){
   error_log('[ServiceYar][public-companies] '.$e->getMessage());
   Response::json(['ok'=>false,'error'=>['code'=>'COMPANIES_UNAVAILABLE','message'=>'موقعیت شرکت‌ها موقتاً در دسترس نیست.']],503);
  }
 }
 if($method==='GET'&&$path==='/api/v1/public/company'){
  try{
   $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
   $company=$id?\ServiceYar\Domain\PublicExplorer::company((int)$id):null;
   if(!$company)Response::json(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'شرکت یافت نشد.']],404);
   header('Cache-Control: public, max-age=90');
   Response::json(['ok'=>true,'data'=>$company]);
  }catch(\Throwable $e){
   error_log('[ServiceYar][public-company] '.$e->getMessage());
   Response::json(['ok'=>false,'error'=>['code'=>'COMPANY_UNAVAILABLE','message'=>'اطلاعات شرکت در دسترس نیست.']],503);
  }
 }
 // Query-only school search; retain rate limits and no bulk school feed.
 if($method==='GET'&&$path==='/api/v1/public/schools'){
  try{
   $term=trim(str_replace(['ي','ك','‌'],['ی','ک',' '],(string)($_GET['q']??'')));
   if($term===''){header('Cache-Control: no-store');Response::json(['ok'=>true,'data'=>['schools'=>[],'minimumSearchLength'=>3]]);}
   $len=mb_strlen($term,'UTF-8');
   if($len<3||$len>80)Response::json(['ok'=>false,'error'=>['code'=>'INVALID_QUERY','message'=>'حداقل سه حرف وارد کنید.']],422);
   $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
   $rateDir=dirname(__DIR__,2).'/storage/cache/public-school-rate';
   if(!is_dir($rateDir))@mkdir($rateDir,0750,true);
   if(is_dir($rateDir)){
    $handle=@fopen($rateDir.'/'.hash('sha256',$ip),'c+');
    if($handle&&flock($handle,LOCK_EX)){
     $record=json_decode(stream_get_contents($handle)?:'{}',true);
     $now=time();$window=(int)($record['window']??0);$count=(int)($record['count']??0);
     if($now-$window>=60){$window=$now;$count=0;}
     $count++;ftruncate($handle,0);rewind($handle);fwrite($handle,json_encode(['window'=>$window,'count'=>$count]));fflush($handle);flock($handle,LOCK_UN);fclose($handle);
     if($count>35){header('Retry-After: 60');Response::json(['ok'=>false,'error'=>['code'=>'RATE_LIMIT','message'=>'تعداد جستجوها بیش از حد مجاز است.']],429);}
    }elseif($handle)fclose($handle);
   }
   header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow, noarchive');
   Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\PublicExplorer::schools($term)]);
  }catch(\Throwable $e){
   error_log('[ServiceYar][public-schools] '.$e->getMessage());
   Response::json(['ok'=>false,'error'=>['code'=>'PUBLIC_SCHOOLS_UNAVAILABLE','message'=>'جستجوی مدارس موقتاً در دسترس نیست.']],503);
  }
 }
 // Server-side publication setting: never expose internal user mobiles by default.
 if($method==='GET'&&$path==='/api/v1/public/company-logo'){
  $companyId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
  if(!$companyId||!\ServiceYar\Domain\PublicExplorer::company((int)$companyId))
   Response::json(['ok'=>false,'error'=>['code'=>'NOT_FOUND']],404);
  \ServiceYar\Agency\AgencyLogo::publicOutput((int)$companyId);
 }
 // Expiring signed photo endpoint: recompress, watermark and strip metadata.
 if($method==='GET'&&$path==='/api/v1/public/school-photo'){
  $school=(string)($_GET['school']??'');$expiry=(int)($_GET['expires']??0);$signature=(string)($_GET['sig']??'');
  $secret=(string)config('APP_KEY','').(string)config('DB_PASSWORD','');
  if(!preg_match('/^[cs][1-9][0-9]{0,9}$/D',$school)||$expiry<time()||$expiry>time()+660
    ||$secret===''||!hash_equals(hash_hmac('sha256',$school.'|'.$expiry,$secret),$signature)){
   Response::json(['ok'=>false,'error'=>['code'=>'INVALID_PHOTO_LINK','message'=>'پیوند تصویر معتبر نیست یا منقضی شده است.']],403);
  }
  $catalog=$school[0]==='c';$id=(int)substr($school,1);$pdo=Connection::get();
  $sql=$catalog?'SELECT photo_path FROM school_catalog WHERE id=? LIMIT 1':
   'SELECT s.photo_path FROM schools s JOIN agencies a ON a.id=s.agency_id AND a.is_active=1 WHERE s.id=? AND s.is_active=1 LIMIT 1';
  $q=$pdo->prepare($sql);$q->execute([$id]);$path=(string)($q->fetchColumn()?:'');
  // Strict allowlist: never allow arbitrary paths, URLs, or directory traversal.
  $root=dirname(__DIR__,2);$source=null;
  if(preg_match('~^private-schools/([0-9]+)/([0-9]+)/([a-f0-9]{32})\.(jpg|png|webp)$~D',$path,$m))
   $source=$root.'/storage/uploads/school-images/'.$m[1].'/'.$m[2].'/'.$m[3].'.'.$m[4];
  elseif(preg_match('~^(?:uploads/)?schools/((?:[0-9]{4}/[0-9]{2}/)?[a-zA-Z0-9_.-]+\.(?:jpg|jpeg|png|webp))$~iD',$path,$m))
   $source=$root.'/schools/'.$m[1];
  if(!$source||!is_file($source)||!is_readable($source)||!function_exists('imagecreatefromstring'))
   Response::json(['ok'=>false,'error'=>['code'=>'PHOTO_NOT_FOUND','message'=>'تصویر در دسترس نیست.']],404);
  $bytes=@file_get_contents($source,false,null,0,5*1024*1024+1);
  $original=$bytes!==false&&strlen($bytes)<=5*1024*1024?@imagecreatefromstring($bytes):false;
  if(!$original)Response::json(['ok'=>false,'error'=>['code'=>'PHOTO_NOT_FOUND','message'=>'تصویر معتبر نیست.']],404);
  $w=imagesx($original);$h=imagesy($original);
  // Keep the natural portrait / landscape aspect ratio (including panoramic photos).
  $scale=min(1,480/max(1,$w,$h));
  $tw=max(1,(int)round($w*$scale));$th=max(1,(int)round($h*$scale));
  $canvas=imagecreatetruecolor($tw,$th);
  imagecopyresampled($canvas,$original,0,0,0,0,$tw,$th,$w,$h);
  // Subtle semi-transparent watermark overlays the photo rather than adding a footer.
  // The preview image has no original EXIF metadata after recompression.
  $watermark='service-yar.ir';
  $font=5;
  $textWidth=imagefontwidth($font)*strlen($watermark);
  $textHeight=imagefontheight($font);
  $overlay=imagecreatetruecolor($textWidth+30,$textHeight+24);
  imagealphablending($overlay,false);
  imagesavealpha($overlay,true);
  $transparent=imagecolorallocatealpha($overlay,0,0,0,127);
  imagefill($overlay,0,0,$transparent);
  // White with a faint dark edge remains visible on both bright and dark facades.
  // Alpha is 0=opaque, 127=invisible. The former 108 was almost imperceptible.
  $outline=imagecolorallocatealpha($overlay,12,50,43,106);
  $ink=imagecolorallocatealpha($overlay,255,255,255,75);
  imagestring($overlay,$font,16,13,$watermark,$outline);
  imagestring($overlay,$font,15,12,$watermark,$ink);
  $rotated=imagerotate($overlay,18,$transparent);
  imagedestroy($overlay);
  if($rotated!==false){
   imagesavealpha($rotated,true);
   imagealphablending($canvas,true);
   $rx=(int)floor(($tw-imagesx($rotated))/2);
   $ry=(int)floor(($th-imagesy($rotated))/2);
   imagecopy($canvas,$rotated,$rx,$ry,0,0,imagesx($rotated),imagesy($rotated));
   imagedestroy($rotated);
  }
  imagedestroy($original);
  header('Content-Type: image/jpeg');header('Content-Disposition: inline; filename="serviceyar-preview.jpg"');
  header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, noimageindex, noarchive');
  header('X-Content-Type-Options: nosniff');imagejpeg($canvas,null,62);imagedestroy($canvas);exit;
 }
 // Public reference valuation: exactly the same 1405-1406 grid and traffic coefficients as Madares.
 // No tenant/school required; do not confuse this with agency-specific contracted tariffs.
 if($method==='GET'&&$path==='/api/v1/public/service-quote'){
  $district=filter_var($_GET['district']??null,FILTER_VALIDATE_INT);
  $distanceRaw=(string)($_GET['distanceKm']??'');
  $class=(string)($_GET['vehicleClass']??'');
  $allowed=['economy','standard','van'];
  if(!$district||$district<1||$district>8||!in_array($class,$allowed,true)
    ||!preg_match('/^(?:[0-9]{1,3})(?:\\.[0-9]{1,2})?$/D',$distanceRaw)
    ||(float)$distanceRaw<=0||(float)$distanceRaw>200){
   Response::json(['ok'=>false,'error'=>['code'=>'INVALID_QUOTE_INPUT','message'=>'ناحیه، کلاس خودرو و مسافت معتبر (تا ۲۰۰ کیلومتر) را وارد کنید.']],422);
  }
  $reference=json_decode(file_get_contents(__DIR__.'/../src/Domain/public-service-valuation-1405.json'),true,512,JSON_THROW_ON_ERROR);
  $vehicle=null;foreach($reference['classes'] as $item){if($item['key']===$class){$vehicle=$item;break;}}
  $km=max(3,(int)ceil((float)$distanceRaw));
  $entry=null;foreach($vehicle['brackets'] as $item){if((int)$item['distance_km']===min(20,$km)){$entry=$item;break;}}
  if(!$entry)Response::json(['ok'=>false,'error'=>['code'=>'RATE_UNAVAILABLE','message'=>'تعرفه این مسافت موجود نیست.']],422);
  $extra=max(0,$km-20)*(float)$vehicle['extra_driver_riyal_per_km'];
  $driver=(float)$entry['driver_1405_riyal']+$extra;
  $parentBase=(float)$entry['parent_monthly_riyal']+($extra*1.19658);
  $factor=(float)($reference['traffic_by_district'][(string)$district]??0);
  $total=(int)round($parentBase+($driver*$factor*1.19658));
  header('Cache-Control: public, max-age=3600');
  Response::json(['ok'=>true,'data'=>[
   'monthlyFee'=>$total,'baseMonthlyFee'=>(int)round($parentBase),'trafficPercent'=>(int)round($factor*100),
   'driverBase'=>(int)round($driver),'distanceAppliedKm'=>$km,
   'currency'=>'IRR','academicYear'=>$reference['year'],'reference'=>'Madares 1405-1406'
  ]]);
 }
 $auth=new AuthService();
 // Anonymous-safe session check. Avoid expected HTTP 401 errors on the public landing page.
 if($method==='GET'&&$path==='/api/v1/auth/status'){
  $authenticated=false;
  try{$auth->current();$authenticated=true;}
  catch(AuthException $e){if($e->status!==401)throw $e;}
  header('Cache-Control: private, no-store');
  Response::json(['ok'=>true,'data'=>['authenticated'=>$authenticated]]);
 }

 if($method==='POST'&&$path==='/api/v1/auth/login'){ $b=Request::json();Response::json(['ok'=>true,'data'=>$auth->login((string)($b['username']??''),(string)($b['password']??''))]); }
 if($method==='POST'&&$path==='/api/v1/auth/logout'){ $s=$auth->current();$auth->requireCsrf($s);$auth->logout();Response::json(['ok'=>true,'data'=>['loggedOut'=>true]]); }
 if($method==='GET'&&$path==='/api/v1/auth/me'){ $s=$auth->current();$rs=Connection::get()->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=:u ORDER BY r.id');$rs->execute(['u'=>$s['id']]);Response::json(['ok'=>true,'data'=>['user'=>['id'=>$s['id'],'uuid'=>$s['uuid'],'username'=>$s['username'],'firstName'=>$s['firstName'],'lastName'=>$s['lastName']],'roles'=>$rs->fetchAll(\PDO::FETCH_COLUMN),'permissions'=>Authorization::permissions($s['id']),'agencies'=>AgencyService::sessionList($s['id']),'csrfToken'=>$s['csrfToken']]]); }
 if($method==='GET'&&$path==='/api/v1/auth/csrf'){ $s=$auth->current();Response::json(['ok'=>true,'data'=>['csrfToken'=>$s['csrfToken']]]); }
 $s=$auth->current(); $aid=null;

 // Agency-scoped school photo endpoint: no public listing or direct object-store URL.
 if(preg_match('#^/api/v1/schools/(\\d+)/photo$#',$path,$photoMatch)){
  $s=$auth->current();$photoAgency=agencyId($s);$schoolId=(int)$photoMatch[1];
  if($method==='GET')\ServiceYar\Domain\SchoolPhotos::stream($s['id'],$photoAgency,$schoolId);
  if($method==='POST'){
   $auth->requireCsrf($s);
   Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolPhotos::upload($s['id'],$photoAgency,$schoolId,$_FILES)]);
  }
 }


 if($method==='GET'&&$path==='/api/v1/agencies')Response::json(['ok'=>true,'data'=>AgencyService::list($s['id'])]);
 if($method==='POST'&&$path==='/api/v1/agencies'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AgencyService::create($s['id'],Request::json())],201); }
 if(preg_match('#^/api/v1/agencies/(\d+)$#',$path,$m)){ $id=(int)$m[1];if($method==='PUT'||$method==='PATCH'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AgencyService::update($s['id'],$id,Request::json())]);} }
 if(preg_match('#^/api/v1/agencies/(\d+)/users$#',$path,$m)){ $id=(int)$m[1];if($method==='GET')Response::json(['ok'=>true,'data'=>AgencyService::users($s['id'],$id)]);if($method==='PUT'){ $auth->requireCsrf($s);$b=Request::json();Response::json(['ok'=>true,'data'=>AgencyService::assignUsers($s['id'],$id,is_array($b['userIds']??null)?$b['userIds']:[])]);} }
 if(preg_match('#^/api/v1/agencies/(\d+)$#',$path,$m)&&$method==='DELETE'){ $auth->requireCsrf($s);AgencyService::deactivate($s['id'],(int)$m[1]);Response::json(['ok'=>true,'data'=>['deactivated'=>true]]);}

 if($method==='POST'&&preg_match('#^/api/v1/admin/agencies/(\\d+)/activate$#',$path,$m)){
  $auth->requireCsrf($s);
  AgencyService::activate($s['id'],(int)$m[1]);
  Response::json(['ok'=>true,'data'=>['activated'=>true]]);
 }
 if($method==='GET'&&$path==='/api/v1/admin/agencies/export.xlsx'){
  ExportService::companies($s['id'],$_GET);
 }
 if($method==='GET'&&$path==='/api/v1/admin/overview'){
  if(!Authorization::isSuperAdmin($s['id'])) throw new AuthException(403,'این بخش فقط برای مدیر کل سامانه است.','SUPER_ADMIN_REQUIRED');
  $pdo=Connection::get();$counts=[];
  foreach(['agencies','users','schools','students','drivers','vehicles','services'] as $table){$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
  $roles=$pdo->query('SELECT r.id,r.name,r.title,COUNT(DISTINCT ur.user_id) user_count,COUNT(DISTINCT rp.permission_id) permission_count FROM roles r LEFT JOIN user_roles ur ON ur.role_id=r.id LEFT JOIN role_permissions rp ON rp.role_id=r.id GROUP BY r.id,r.name,r.title ORDER BY r.id')->fetchAll();
  $permissions=$pdo->query('SELECT id,name,title FROM permissions ORDER BY name')->fetchAll();
  $users=$pdo->query('SELECT u.id,u.username,u.first_name,u.last_name,u.is_active,u.last_login_at,GROUP_CONCAT(DISTINCT r.title ORDER BY r.id SEPARATOR ", ") roles,GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") agencies FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id LEFT JOIN agency_users au ON au.user_id=u.id LEFT JOIN agencies a ON a.id=au.agency_id GROUP BY u.id ORDER BY u.id DESC LIMIT 100')->fetchAll();
  Response::json(['ok'=>true,'data'=>['counts'=>$counts,'roles'=>$roles,'permissions'=>$permissions,'agencies'=>AgencyService::list($s['id']),'users'=>$users]]);
 }
 if($method==='PUT'&&$path==='/api/v1/auth/profile'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>UserService::updateOwnProfile($s['id'],Request::json())]);}
 if($method==='POST'&&$path==='/api/v1/auth/password'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>UserService::changePassword($s['id'],0,$s['id'],Request::json())]);}
 if(preg_match('#^/api/v1/admin/users(?:/(\d+)(?:/(password))?)?$#',$path,$m)){
  if(!Authorization::isSuperAdmin($s['id']))throw new AuthException(403,'فقط مدیر کل مجاز است.','FORBIDDEN');
  if($method==='GET')Response::json(['ok'=>true,'data'=>empty($m[1])?UserService::list($s['id'],0):UserService::find($s['id'],0,(int)$m[1])]);
  $auth->requireCsrf($s);if(($m[2]??'')==='password'&&$method==='POST')Response::json(['ok'=>true,'data'=>UserService::changePassword($s['id'],0,(int)$m[1],Request::json())]);
  if($method==='PUT'&&!empty($m[1]))Response::json(['ok'=>true,'data'=>UserService::save($s['id'],0,Request::json(),(int)$m[1])]);
 }
 $aid=agencyId($s);
 if(preg_match('#^/api/v1/users/(\d+)/(password)$#',$path,$m)&&$method==='POST'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>UserService::changePassword($s['id'],$aid,(int)$m[1],Request::json())]);}
 if($method==='POST'&&$path==='/api/v1/operations/financial-preview'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>FinancialRules::preview($s['id'],$aid,Request::json())]);}
 if($method==='GET'&&$path==='/api/v1/reports/school-workbook.xlsx')ExportService::schoolWorkbook($s['id'],$aid,(string)($_GET['period']??'Mehr'));
 if($method==='GET'&&$path==='/api/v1/operations/service-finance')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\ServiceFinanceReport::report($s['id'],$aid,$_GET)]);
 if($method==='GET'&&$path==='/api/v1/reports/service-finance.xlsx')ExportService::serviceFinance($s['id'],$aid,$_GET);
 if($method==='GET'&&preg_match('#^/api/v1/students/(\d+)/service-finance$#',$path,$m))Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\ServiceFinanceReport::report($s['id'],$aid,$_GET,(int)$m[1])]);
 if($method==='GET'&&$path==='/api/v1/operations/school-statement/rules'){Authorization::requirePermission($s['id'],'finance.view');Response::json(['ok'=>true,'data'=>json_decode(file_get_contents(__DIR__.'/../src/Domain/school-statement-template-1405.json'),true,512,JSON_THROW_ON_ERROR)]);}
 if($method==='GET'&&preg_match('#^/api/v1/reports/school-statements/(\\d+)\\.xlsx$#',$path,$statementExport))\ServiceYar\Reports\MonthlyStatementWorkbook::download($s['id'],$aid,(int)$statementExport[1]);
 if($method==='GET'&&$path==='/api/v1/operations/school-statements')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\MonthlyStatements::list($s['id'],$aid,($_GET['includeDeleted']??'')==='1')]);
 if($method==='POST'&&$path==='/api/v1/operations/school-statements/preview'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\MonthlyStatements::preview($s['id'],$aid,Request::json())]);}
 if($method==='POST'&&$path==='/api/v1/reports/school-statements/preview.xlsx'){$auth->requireCsrf($s);\ServiceYar\Reports\MonthlyStatementWorkbook::downloadPreview($s['id'],$aid,Request::json());}
 if($method==='POST'&&$path==='/api/v1/operations/school-statements'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\MonthlyStatements::finalize($s['id'],$aid,Request::json())],201);}
 if(preg_match('#^/api/v1/operations/school-statements/(\\d+)(?:/(delete|restore))?$#',$path,$sm)){
  if($method==='GET'&&empty($sm[2]))Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\MonthlyStatements::get($s['id'],$aid,(int)$sm[1])]);
  if($method==='POST'&&in_array($sm[2]??'',['delete','restore'],true)){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\MonthlyStatements::changeStatus($s['id'],$aid,(int)$sm[1],$sm[2]==='restore')]);}
 }
 if($method==='GET'&&$path==='/api/v1/operations/imported-school-statement')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolServiceStatement::importedReport($s['id'],$aid,(string)($_GET['period']??'Mehr'),isset($_GET['from'])?(string)$_GET['from']:null,isset($_GET['to'])?(string)$_GET['to']:null)]);
 if($method==='POST'&&$path==='/api/v1/operations/school-statement/preview'){$auth->requireCsrf($s);Authorization::requirePermission($s['id'],'finance.view');$b=Request::json();$days=filter_var($b['serviceDays']??null,FILTER_VALIDATE_INT);if($days===false||!is_bool($b['certificate']??true)||!is_bool($b['insured']??true))throw new AuthException(422,'پارامترهای صورت‌وضعیت معتبر نیست.','VALIDATION_ERROR');Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolServiceStatement::fromWorkbookRate((string)($b['baseFee']??''),$days,$b['certificate']??true,$b['insured']??true)]);}
 if(preg_match('#^/api/v1/accounting/journal/(\d+)(?:/(approve))?$#',$path,$m)){
  if($method==='GET'&&empty($m[2]))Response::json(['ok'=>true,'data'=>AccountingJournal::get($s['id'],$aid,(int)$m[1])]);
  if($method==='PUT'&&empty($m[2])){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AccountingJournal::save($s['id'],$aid,Request::json(),(int)$m[1])]);}
  if($method==='POST'&&($m[2]??'')==='approve'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AccountingJournal::approve($s['id'],$aid,(int)$m[1])]);}
 }
 if($method==='GET'&&$path==='/api/v1/accounting/statement')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\AccountStatement::report($s['id'],$aid,$_GET)]);
 if($method==='GET'&&$path==='/api/v1/accounting/statement/export')ExportService::accountStatement($s['id'],$aid,$_GET);
 if($path==='/api/v1/accounting/cheques'&&$method==='GET')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\ChequeReceipts::list($s['id'],$aid,$_GET)]);
 if($path==='/api/v1/accounting/cheques'&&$method==='POST'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\ChequeReceipts::save($s['id'],$aid,Request::json())],201);}
 if(preg_match('#^/api/v1/accounting/cheques/(\d+)(?:/(transition))?$#',$path,$m)){
  if($method==='GET'&&empty($m[2]))Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\ChequeReceipts::get($s['id'],$aid,(int)$m[1])]);
  if($method==='PUT'&&empty($m[2])){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\ChequeReceipts::save($s['id'],$aid,Request::json(),(int)$m[1])]);}
  if($method==='POST'&&($m[2]??'')==='transition'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\ChequeReceipts::transition($s['id'],$aid,(int)$m[1],Request::json())]);}
 }
 if($method==='GET'&&$path==='/api/v1/accounting/defaults')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\FinancialAccounts::get($s['id'],$aid)]);
 if($method==='PUT'&&$path==='/api/v1/accounting/defaults'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\FinancialAccounts::save($s['id'],$aid,Request::json())]);}
 if($method==='POST'&&$path==='/api/v1/accounting/quick'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AccountingJournal::quick($s['id'],$aid,Request::json())],201);}
 if($method==='POST'&&$path==='/api/v1/accounting/journal'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>AccountingJournal::save($s['id'],$aid,Request::json())],201);}
 if($method==='POST'&&preg_match('#^/api/v1/accounting/post/(ledger|payments|driver-payments)/(\d+)$#',$path,$m)){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\FinancialPosting::post($s['id'],$aid,$m[1],(int)$m[2],Request::json())]);}
 if($method==='POST'&&$path==='/api/v1/operations/school-statement/preview'){$auth->requireCsrf($s);Authorization::requireAgencyAccess($s['id'],$aid);Authorization::requirePermission($s['id'],'finance.view');Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolStatement::calculate(Request::json())]);}
 if($method==='POST'&&$path==='/api/v1/operations/tariffs/quote'){
  $auth->requireCsrf($s);Authorization::requirePermission($s['id'],'students.view');$b=Request::json();
  if(isset($b['studentId'])){
   $student=DomainService::get($s['id'],$aid,'students',(int)$b['studentId']);
   $saved=(float)$student['monthly_fee'];
   $fee=$saved>0?$saved:\ServiceYar\Domain\AgencySchoolTariffs::quote(Connection::get(),$aid,(int)$student['school_id'],
    (string)($student['academic_year']?:\ServiceYar\Domain\AgencySchoolTariffs::YEAR),$student['tariff_distance_km'],$student['tariff_vehicle_class']);
   Response::json(['ok'=>true,'data'=>['monthlyFee'=>$fee,'saved'=>$saved>0,'currency'=>'IRR']]);
  }
  $fee=\ServiceYar\Domain\AgencySchoolTariffs::quote(Connection::get(),$aid,(int)($b['schoolId']??0),(string)($b['academicYear']??''),$b['distanceKm']??null,$b['vehicleClass']??null);
  Response::json(['ok'=>true,'data'=>['monthlyFee'=>$fee,'currency'=>'IRR']]);
 }
 if($method==='POST'&&$path==='/api/v1/operations/tariffs/seed-arta'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\AgencySchoolTariffs::seedArta($s['id'],$aid)]);}
 if($method==='POST'&&$path==='/api/v1/operations/tariffs/recalculate'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\AgencySchoolTariffs::recalculate($s['id'],$aid)]);}
 if($method==='GET'&&$path==='/api/v1/operations/daily-schedule')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\DailySchedule::report($s['id'],$aid,$_GET)]);
 if($path==='/api/v1/operations/driver-assignment'){
  if($method==='GET')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\DriverAssignment::preview($s['id'],$aid)]);
  if($method==='POST'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\DriverAssignment::apply($s['id'],$aid,Request::json())]);}
 }
 if($method==='GET'&&preg_match('#^/api/v1/schools/(\d+)/shifts/calendar$#',$path,$m))Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolShifts::onDate($s['id'],$aid,(int)$m[1],$_GET)]);
 if(preg_match('#^/api/v1/schools/(\d+)/shifts$#',$path,$m)){
  if($method==='GET')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolShifts::get($s['id'],$aid,(int)$m[1])]);
  if($method==='PUT'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolShifts::save($s['id'],$aid,(int)$m[1],Request::json())]);}
 }
 if(preg_match('#^/api/v1/services/(\d+)/schools$#',$path,$m)){
  if($method==='GET')Response::json(['ok'=>true,'data'=>OperationsService::serviceSchools($s['id'],$aid,(int)$m[1])]);
  if($method==='PUT'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>OperationsService::setServiceSchools($s['id'],$aid,(int)$m[1],Request::json())]);}
 }
 if($method==='GET'&&$path==='/api/v1/operations/payroll-documents')Response::json(['ok'=>true,'data'=>PayrollDocuments::list($s['id'],$aid,$_GET)]);
 if($method==='POST'&&$path==='/api/v1/operations/payroll-documents'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>PayrollDocuments::create($s['id'],$aid,Request::json())],201);}
 if(preg_match('#^/api/v1/operations/payroll-documents/(\d+)(?:/(approve|bank|xlsx))?$#',$path,$m)){
  if($method==='GET'&&($m[2]??'')==='bank')PayrollDocuments::bankCsv($s['id'],$aid,(int)$m[1]);
  if($method==='GET'&&($m[2]??'')==='xlsx')\ServiceYar\Reports\PayrollWorkbook::download($s['id'],$aid,(int)$m[1]);
  if($method==='GET')Response::json(['ok'=>true,'data'=>PayrollDocuments::get($s['id'],$aid,(int)$m[1])]);
  if($method==='PUT'&&empty($m[2])){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>PayrollDocuments::update($s['id'],$aid,(int)$m[1],Request::json())]);}
  if($method==='POST'&&($m[2]??'')==='approve'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>PayrollDocuments::approve($s['id'],$aid,(int)$m[1])]);}
 }
 if($method==='POST'&&$path==='/api/v1/operations/installments'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\InstallmentPlan::create($s['id'],$aid,Request::json())],201);}
 if($method==='POST'&&$path==='/api/v1/operations/installments/preview'){$auth->requireCsrf($s);Authorization::requireAgencyAccess($s['id'],$aid);Authorization::requirePermission($s['id'],'finance.view');Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\InstallmentPlan::preview(Request::json())]);}
 if($method==='GET'&&$path==='/api/v1/maps/config'){Authorization::requireAgencyAccess($s['id'],$aid);Response::json(['ok'=>true,'data'=>\ServiceYar\Integrations\Neshan::config($aid)]);}
 if($method==='GET'&&$path==='/api/v1/integrations/status'){Authorization::requirePermission($s['id'],'settings.view');Response::json(['ok'=>true,'data'=>['sms'=>\ServiceYar\Integrations\NeginSms::configured(),'bale'=>\ServiceYar\Integrations\BaleClient::configured(),'google'=>config('GOOGLE_MAPS_SERVER_KEY','')!=='','osm'=>true]]);}
 if($method==='POST'&&in_array($path,['/api/v1/maps/search','/api/v1/maps/reverse'],true)){$auth->requireCsrf($s);Authorization::requireAgencyAccess($s['id'],$aid);if(!array_intersect(['students.manage','schools.manage'],Authorization::permissions($s['id'])))throw new AuthException(403,'برای انتخاب موقعیت دسترسی ندارید.','FORBIDDEN');$body=Request::json();$result=$path==='/api/v1/maps/search'?\ServiceYar\Integrations\Neshan::search($aid,$body):\ServiceYar\Integrations\Neshan::reverse($aid,$body);Response::json(['ok'=>true,'data'=>$result]);}
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
 if($method==='GET'&&$path==='/api/v1/operations/driver-deduction-rates'){Authorization::requirePermission($s['id'],'services.view');Authorization::requireAgencyAccess($s['id'],$aid);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\DriverDeductionPolicy::rates($aid)]);}
 if($method==='GET'&&$path==='/api/v1/finance/banks'){Authorization::requirePermission($s['id'],'finance.view');Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\IranBankDetails::banks()]);}
 if($method==='GET'&&$path==='/api/v1/finance/payment-methods')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\AgencyPaymentMethods::get($s['id'],$aid)]);
 if($path==='/api/v1/admin/platform-settings'){
   if($method==='GET')Response::json(['ok'=>true,'data'=>\ServiceYar\Settings\PlatformSettings::overview($s['id'])]);
   if($method==='PUT'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Settings\PlatformSettings::save($s['id'],Request::json())]);}
 }
 if($path==='/api/v1/agency/public-profile'){
  Authorization::requireAgencyAccess($s['id'],$aid);
  if($method==='GET'){
   Authorization::requirePermission($s['id'],'settings.view');
   $q=Connection::get()->prepare("SELECT setting_key,setting_value FROM agency_settings
    WHERE agency_id=? AND setting_key IN (
     'public_contact_type','public_office_address','public_manager_name','public_manager_mobile',
     'company_ceo_mobile','company_phone','company_office_phone','company_address','company_manager_name')");
   $q->execute([$aid]);$data=$q->fetchAll(\PDO::FETCH_KEY_PAIR);
   $data['public_contact_type']=($data['public_contact_type']??'mobile')==='landline'?'landline':'mobile';
   $data['effective_mobile']=($data['public_manager_mobile']??'')?:($data['company_ceo_mobile']??'');
   $data['effective_office_phone']=($data['company_office_phone']??'')?:($data['company_phone']??'');
   Response::json(['ok'=>true,'data'=>$data]);
  }
  if($method==='PUT'){
   $auth->requireCsrf($s);Authorization::requirePermission($s['id'],'settings.manage');
   $body=Request::json();
   $keys=['public_contact_type','public_office_address','public_manager_name','public_manager_mobile'];
   if(array_diff(array_keys($body),$keys))throw new AuthException(422,'فیلد ناشناخته مجاز نیست.','VALIDATION_ERROR');
   $type=$body['public_contact_type']??'mobile';
   if(!in_array($type,['mobile','landline'],true))
    throw new AuthException(422,'نوع شماره تماس نامعتبر است.','VALIDATION_ERROR');
   foreach($body as $key=>$raw){
    if(!is_string($raw))throw new AuthException(422,'مشخصات تماس نامعتبر است.','VALIDATION_ERROR');
    $value=trim($raw);
    if(mb_strlen($value,'UTF-8')>($key==='public_office_address'?450:120))
     throw new AuthException(422,'طول اطلاعات تماس بیش از حد مجاز است.','VALIDATION_ERROR');
    if($key==='public_manager_mobile'&&$value!==''&&!preg_match('/^[0-9+۰-۹ -]{7,25}$/uD',$value))
     throw new AuthException(422,'شماره همراه معتبر نیست.','VALIDATION_ERROR');
   }
   $body['public_contact_type']=$type;
   $pdo=Connection::get();
   if($type==='landline'){
    $phone=$pdo->prepare("SELECT setting_value FROM agency_settings WHERE agency_id=? AND setting_key IN ('company_office_phone','company_phone') AND TRIM(COALESCE(setting_value,''))<>'' ORDER BY (setting_key='company_office_phone') DESC LIMIT 1");
    $phone->execute([$aid]);
    if(!$phone->fetchColumn())throw new AuthException(422,'برای نمایش تلفن ثابت ابتدا شماره دفتر شرکت را در تنظیمات ثبت کنید.','OFFICE_PHONE_REQUIRED');
   }
   $q=$pdo->prepare('INSERT INTO agency_settings(agency_id,setting_key,setting_value) VALUES(?,?,?)
     ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
   foreach($body as $key=>$raw)$q->execute([$aid,$key,trim($raw)]);
   Response::json(['ok'=>true,'data'=>['updated'=>true,'contactType'=>$type]]);
  }
 }
 if($path==='/api/v1/agency/logo'){
  if($method==='GET')\ServiceYar\Agency\AgencyLogo::output($s['id'],$aid);
  if($method==='POST'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Agency\AgencyLogo::upload($s['id'],$aid,$_FILES['file']??[])]);}
  if($method==='DELETE'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Agency\AgencyLogo::delete($s['id'],$aid)]);}
 }
 if($method==='GET'&&$path==='/api/v1/settings')Response::json(['ok'=>true,'data'=>SettingsService::get($s['id'],$aid)]);
 if(($method==='PUT'||$method==='PATCH')&&$path==='/api/v1/settings'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>SettingsService::put($s['id'],$aid,Request::json())]); }
 if($method==='GET'&&$path==='/api/v1/holidays')Response::json(['ok'=>true,'data'=>HolidayService::list($s['id'],$aid)]);
 if($method==='POST'&&$path==='/api/v1/holidays'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>HolidayService::save($s['id'],$aid,Request::json())],201); }
 if(preg_match('#^/api/v1/holidays/(\d+)$#',$path,$m)){ $id=(int)$m[1];if($method==='PUT'||$method==='PATCH'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>HolidayService::save($s['id'],$aid,Request::json(),$id)]);}if($method==='DELETE'){ $auth->requireCsrf($s);HolidayService::delete($s['id'],$aid,$id);Response::json(['ok'=>true,'data'=>['deleted'=>true]]);} }

 if($method==='GET'&&$path==='/api/v1/users')Response::json(['ok'=>true,'data'=>UserService::list($s['id'],$aid)]);
 if($method==='POST'&&$path==='/api/v1/users'){ $auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>UserService::save($s['id'],$aid,Request::json())],201); }
 if(preg_match('#^/api/v1/users/(\d+)$#',$path,$m)&&($method==='PUT'||$method==='PATCH')){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>UserService::save($s['id'],$aid,Request::json(),(int)$m[1])]);}

 if($method==='GET'&&$path==='/api/v1/reports/ledger')Response::json(['ok'=>true,'data'=>ReportService::ledger($s['id'],$aid,$_GET)]);
 if($method==='GET'&&$path==='/api/v1/reports/ledger/export')ExportService::ledgerReport($s['id'],$aid,$_GET);
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
 if($method==='POST'&&$path==='/api/v1/services/bulk-status'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\ServiceBulkStatus::apply($s['id'],$aid,Request::json())]);}
 if($method==='POST'&&$path==='/api/v1/drivers/bulk-status'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\DriverBulkStatus::apply($s['id'],$aid,Request::json())]);}
 if($method==='GET'&&$path==='/api/v1/operations/mehr-assignments/preview')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\MehrWorkbookAssignments::run($s['id'],$aid,false)]);
 if($method==='POST'&&$path==='/api/v1/operations/mehr-assignments/drafts'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\MehrWorkbookAssignments::run($s['id'],$aid,true)]);}
 if($path==='/api/v1/school-catalog'&&$method==='GET')Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolCatalog::search($s['id'],$aid,$_GET)]);
 if($path==='/api/v1/school-catalog/select'&&$method==='POST'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\ServiceYar\Domain\SchoolCatalog::select($s['id'],$aid,Request::json())]);}
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

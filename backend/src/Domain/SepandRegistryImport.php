<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use ServiceYar\Support\Uuid;

/**
 * Explicit admin-controlled import of Sepand's TWO independent XLSX reports.
 * Company binding is by national ID, never array position or an internal ID
 * from the files. A preview + digest is mandatory before any mutation.
 */
final class SepandRegistryImport {
 private const DRIVER_HEADERS=['شناسه درخواست','نام','نام خانوادگی','کد ملی','جنسیت','شماره موبایل','شماره VIN','پلاک (دو رقم)','پلاک (حرف)','پلاک (سه رقم)','پلاک (ایران)','نام شرکت','شناسه ملی شرکت','وضعیت'];
 private const CAR_HEADERS=['p1','p2_letter','p3','p4_iran','driver_national_code','vin','type','system','tip','make_year','capacity','fuel_type','color','engine_number','chassis_number','insurance_number','student_national_code','co_national_id'];

 private static function clean(mixed $value): string {
  $s=trim(strtr((string)$value,['ي'=>'ی','ك'=>'ک','‌'=>' ','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
  return preg_replace('/\s+/u',' ',$s)??$s;
 }
 private static function identity(mixed $value): string {return preg_replace('/\D/','',self::clean($value))??'';}
 private static function status(mixed $raw): string {
  return match(self::clean($raw)){
   'تأیید شده','تایید شده'=>'approved','رد شده'=>'rejected','در حال بررسی'=>'pending',
   default=>'unknown'
  };
 }
 private static function upload(array $file,string $label): string {
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??'')))
   throw new AuthException(422,'فایل '.$label.' بارگذاری نشده است.','SEPAND_FILE_REQUIRED');
  $size=(int)($file['size']??0);
  if($size<100||$size>8*1024*1024||strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='xlsx')
   throw new AuthException(422,'فایل '.$label.' باید XLSX و کمتر از ۸ مگابایت باشد.','SEPAND_FILE_INVALID');
  $path=(string)$file['tmp_name'];
  $zip=new \ZipArchive();
  if($zip->open($path)!==true)throw new AuthException(422,'فایل '.$label.' اکسل معتبر نیست.','SEPAND_XLSX_INVALID');
  $stat=$zip->statName('xl/worksheets/sheet1.xml');$zip->close();
  if(!$stat||($stat['size']??0)>45*1024*1024)throw new AuthException(422,'ساختار اکسل '.$label.' معتبر یا قابل پردازش نیست.','SEPAND_XLSX_INVALID');
  return $path;
 }
 /** XLSX row reader: first sheet, inline/shared strings, bounded streaming. */
 private static function rows(string $path,array $required): array {
  $zip=new \ZipArchive();$zip->open($path);
  $shared=[];
  if(($xml=$zip->getFromName('xl/sharedStrings.xml'))!==false){
   $reader=new \XMLReader();$reader->XML($xml,null,LIBXML_NONET);
   while($reader->read())if($reader->nodeType===\XMLReader::ELEMENT&&$reader->localName==='si'){
    $node=simplexml_load_string($reader->readOuterXml(),'SimpleXMLElement',LIBXML_NONET);
    $text='';if($node)foreach($node->xpath('.//*[local-name()="t"]')?:[] as $item)$text.=(string)$item;
    // readOuterXml does not advance the reader. Let read() traverse to
    // the following <si>; calling next('si') here skips every other entry.
    $shared[]=$text;
   }$reader->close();unset($xml);
  }
  $zip->close();
  $uri='zip://'.$path.'#xl/worksheets/sheet1.xml';
  $reader=new \XMLReader();
  if(!$reader->open($uri,null,LIBXML_NONET))throw new AuthException(422,'خواندن شیت اکسل ممکن نیست.','SEPAND_XLSX_INVALID');
  $headers=null;$results=[];$rowNumber=0;
  while($reader->read())if($reader->nodeType===\XMLReader::ELEMENT&&$reader->localName==='row'){
   $rowNumber=(int)$reader->getAttribute('r');
   $element=simplexml_load_string($reader->readOuterXml(),'SimpleXMLElement',LIBXML_NONET);
   $cells=[];if($element)foreach($element->xpath('./*[local-name()="c"]')?:[] as $cell){
    $pos=(string)$cell['r'];preg_match('/^[A-Z]+/',$pos,$m);$col=$m[0]??'';
    $type=(string)$cell['t'];$raw='';
    if($type==='inlineStr'){
     foreach($cell->xpath('./*[local-name()="is"]//*[local-name()="t"]')?:[] as $t)$raw.=(string)$t;
    }else{
     $value=$cell->xpath('./*[local-name()="v"]')?:[];$raw=(string)($value[0]??'');
     if($type==='s')$raw=$shared[(int)$raw]??'';
    }
    $cells[$col]=self::clean($raw);
   }
   if(!$headers){$headers=$cells;foreach($required as $col=>$expected)if(($headers[$col]??null)!==$expected)throw new AuthException(422,'ستون '.$expected.' در فایل اکسل موجود نیست.','SEPAND_HEADERS_INVALID');continue;}
   if(!array_filter($cells,static fn($v)=>$v!==''))continue;
   if(count($results)>=12000)throw new AuthException(422,'تعداد ردیف‌های فایل بیش از حد مجاز است.','SEPAND_FILE_TOO_LARGE');
   $results[]=['line'=>$rowNumber,'fields'=>$cells];
  }$reader->close();
  return $results;
 }
 private static function agency(PDO $pdo): array {
  $all=$pdo->query('SELECT id,name,sepand_national_id FROM agencies')->fetchAll(PDO::FETCH_ASSOC);
  $names=[];$codes=[];$options=[];
  foreach($all as $company){
   $id=(int)$company['id'];$key=self::clean($company['name']);
   $names[$key][]=$id;
   if($company['sepand_national_id'])$codes[self::identity($company['sepand_national_id'])]=$id;
   $options[]=['id'=>$id,'name'=>$company['name'],'nationalId'=>$company['sepand_national_id']];
  }
  return [$names,$codes,$options];
 }
 public static function run(int $user,array $files,bool $apply,array $binding=[],string $expectedDigest=''): array {
  if(!Authorization::isSuperAdmin($user))throw new AuthException(403,'فقط ادمین کل می‌تواند اطلاعات سپند را وارد کند.','SUPER_ADMIN_REQUIRED');
  $driversPath=self::upload($files['drivers']??[],'رانندگان');
  $carsPath=self::upload($files['vehicles']??[],'خودروها');
  $digest=hash('sha256',hash_file('sha256',$driversPath).':'.hash_file('sha256',$carsPath));
  if($apply&&!hash_equals($digest,$expectedDigest))throw new AuthException(409,'فایل‌ها از زمان پیش‌نمایش تغییر کرده‌اند؛ پیش‌نمایش جدید بگیرید.','SEPAND_PREVIEW_STALE');
  $drivers=self::rows($driversPath,array_combine(range('A','N'),self::DRIVER_HEADERS));
  $cars=self::rows($carsPath,array_combine(range('A','R'),self::CAR_HEADERS));
  $pdo=Connection::get();SepandSchemaMaintenance::requireReady($pdo);[$names,$codes,$options]=self::agency($pdo);
  $companyNames=[];
  foreach($drivers as $record){
   $c=$record['fields'];$national=self::identity($c['M']??'');
   if($national!=='')$companyNames[$national]=self::clean($c['L']??'');
  }
  $mapped=[];$unmatched=[];$used=[];
  foreach($companyNames as $national=>$name){
   $id=$codes[$national]??null;
   if(!$id){
    $candidates=$names[$name]??[];
    if(count($candidates)===1)$id=$candidates[0];
   }
   if(isset($binding[$national])){
    $chosen=(int)$binding[$national];
    if(!in_array($chosen,array_column($options,'id'),true))
     throw new AuthException(422,'شناسه شرکت انتخاب‌شده معتبر نیست.','SEPAND_AGENCY_INVALID');
    $already=array_search($chosen,$codes,true);
    if($already!==false && (string)$already!==(string)$national)
     throw new AuthException(409,'این شرکت قبلاً با شناسه ملی دیگری در سپند مرتبط شده است.','SEPAND_AGENCY_IDENTITY_CONFLICT');
    $id=$chosen;
   }
   if($id&&isset($used[$id])&&$used[$id]!==$national)
    throw new AuthException(409,'دو شناسه ملی مختلف به یک شرکت متصل شده‌اند.','SEPAND_AGENCY_CONFLICT');
   if($id){$mapped[$national]=$id;$used[$id]=$national;}
   else $unmatched[]=['nationalId'=>$national,'name'=>$name];
  }
  $counts=['driversSource'=>count($drivers),'vehiclesSource'=>count($cars),'driversCreated'=>0,'driversUpdated'=>0,
    'vehiclesCreated'=>0,'vehiclesUpdated'=>0,'driversSkipped'=>0,'vehiclesSkipped'=>0];
  $issues=[];$addIssue=static function($kind,$line,$message)use(&$issues){
   if(count($issues)<250)$issues[]=['type'=>$kind,'line'=>$line,'message'=>$message];
  };
  $driverIndex=[];$carIndex=[];
  foreach($drivers as $r){
   $c=$r['fields'];$key=self::identity($c['M']??'').':'.self::identity($c['D']??'');
   if(array_key_exists($key,$driverIndex)){$driverIndex[$key]=null;$counts['driversSkipped']++;$addIssue('drivers',$r['line'],'کد ملی راننده در شرکت تکراری است؛ بررسی دستی لازم است.');}
   elseif(!str_ends_with($key,':'))$driverIndex[$key]=$r;
  }
  foreach($cars as $r){
   $c=$r['fields'];$key=self::identity($c['R']??'').':'.self::clean($c['F']??'');
   if(array_key_exists($key,$carIndex)){$carIndex[$key]=null;$counts['vehiclesSkipped']++;$addIssue('vehicles',$r['line'],'VIN در گزارش خودروهای شرکت تکراری است؛ بررسی دستی لازم است.');}
   elseif(!str_ends_with($key,':'))$carIndex[$key]=$r;
  }
  if($apply&&$unmatched)throw new AuthException(422,'ابتدا شرکت‌های بدون تطبیق را در پیش‌نمایش به شرکت‌های سامانه مرتبط کنید.','SEPAND_AGENCIES_UNMATCHED');
  if($apply){
   $existing=$pdo->prepare('SELECT id FROM sepand_import_runs WHERE file_hash=?');$existing->execute([$digest]);
   if($existing->fetchColumn())return ['alreadyImported'=>true,'digest'=>$digest,'counts'=>$counts,'companies'=>$options,'unmatched'=>$unmatched,'issues'=>[]];
   set_time_limit(180);$pdo->beginTransaction();
  }
  try{
   // Match vehicles with VIN first, then plate if the VIN has not been stored yet.
   $findVin=$pdo->prepare('SELECT id FROM vehicles WHERE agency_id=? AND vin=? LIMIT 2');
   $findPlate=$pdo->prepare('SELECT id FROM vehicles WHERE agency_id=? AND plate=? LIMIT 2');
   // Old vehicle plates often use spacing, Persian digits or a different
   // separator. Keep a scoped canonical index so importing Sepand cannot
   // create a blank-class duplicate and reassign the existing driver.
   $registered=$pdo->query('SELECT id,agency_id,plate FROM vehicles')->fetchAll(PDO::FETCH_ASSOC);
   $normalizedPlates=[];
   foreach($registered as $registeredVehicle){
    $key=(int)$registeredVehicle['agency_id'].':'.VehicleTariffClassRecovery::plateKey($registeredVehicle['plate']);
    $normalizedPlates[$key][]=(int)$registeredVehicle['id'];
   }
   $insertVehicle=$pdo->prepare('INSERT INTO vehicles(agency_id,plate,capacity,model,vin,plate_part_1,plate_letter,plate_part_3,plate_iran,vehicle_type,vehicle_system,vehicle_trim,make_year,fuel_type,color,engine_number,chassis_number,insurance_number) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
   $updateVehicle=$pdo->prepare('UPDATE vehicles SET vin=?,plate_part_1=?,plate_letter=?,plate_part_3=?,plate_iran=?,vehicle_type=?,vehicle_system=?,vehicle_trim=?,make_year=?,fuel_type=?,color=?,engine_number=?,chassis_number=?,insurance_number=? WHERE id=? AND agency_id=?');
   $vehicleIds=[];
   foreach($carIndex as $key=>$r){
    if(!$r)continue;$c=$r['fields'];$company=self::identity($c['R']??'');
    if(!isset($mapped[$company])){$counts['vehiclesSkipped']++;continue;}
    $aid=$mapped[$company];$vin=self::clean($c['F']??'');
    $p1=self::identity($c['A']??'');$letter=self::clean($c['B']??'');$p3=self::identity($c['C']??'');$p4=self::identity($c['D']??'');
    if(!preg_match('/^[0-9]{2}$/',$p1)||!preg_match('/^[0-9]{3}$/',$p3)||!preg_match('/^[0-9]{2}$/',$p4)||!$letter||!$vin){$counts['vehiclesSkipped']++;$addIssue('vehicles',$r['line'],'VIN یا اجزای پلاک ناقص است.');continue;}
    $plate=$p1.$letter.$p3.'-'.$p4;
    $findVin->execute([$aid,$vin]);$matches=$findVin->fetchAll(PDO::FETCH_COLUMN);
    if(!$matches){$findPlate->execute([$aid,$plate]);$matches=$findPlate->fetchAll(PDO::FETCH_COLUMN);}
    if(!$matches)$matches=$normalizedPlates[$aid.':'.VehicleTariffClassRecovery::plateKey($plate)]??[];
    if(count($matches)>1){$counts['vehiclesSkipped']++;$addIssue('vehicles',$r['line'],'چند خودروی همسان در شرکت ثبت شده‌اند.');continue;}
    $capacity=(int)($c['K']??4);if($capacity<1||$capacity>200)$capacity=4;
    $year=(int)($c['J']??0);if($year<1300||$year>1600)$year=null;
    $common=[$vin,$p1,$letter,$p3,$p4,$c['G']??null,$c['H']??null,$c['I']??null,$year,$c['L']??null,$c['M']??null,$c['N']??null,$c['O']??null,$c['P']??null];
    if($matches){
     $vid=(int)$matches[0];$counts['vehiclesUpdated']++;
     if($apply)$updateVehicle->execute([...$common,$vid,$aid]);
    }else{
     $counts['vehiclesCreated']++;$vid=0;
     if($apply){$insertVehicle->execute([$aid,$plate,$capacity,trim(($c['H']??'').' '.($c['I']??'')),...$common]);$vid=(int)$pdo->lastInsertId();
      $normalizedPlates[$aid.':'.VehicleTariffClassRecovery::plateKey($plate)][]=$vid;
     }
    }
    $vehicleIds[$company.':'.$vin]=$vid;
   }
   $findDriver=$pdo->prepare('SELECT id FROM drivers WHERE agency_id=? AND national_code=? LIMIT 2');
   $createDriver=$pdo->prepare('INSERT INTO drivers(uuid,agency_id,first_name,last_name,national_code,mobile,status,vehicle_id,gender,sepand_request_id,sepand_status,sepand_status_updated_at) VALUES(?,?,?,?,?,?,"pending",?,?,?, ?,NOW())');
   $updateDriver=$pdo->prepare('UPDATE drivers SET mobile=COALESCE(NULLIF(?, ""),mobile),gender=?,sepand_request_id=?,sepand_status=?,sepand_status_updated_at=NOW(),vehicle_id=CASE WHEN ? > 0 THEN ? ELSE vehicle_id END WHERE id=? AND agency_id=?');
   $setCarStatus=$pdo->prepare('UPDATE vehicles SET sepand_status=?,sepand_status_updated_at=NOW() WHERE id=? AND agency_id=?');
   foreach($driverIndex as $key=>$r){
    if(!$r)continue;$c=$r['fields'];$company=self::identity($c['M']??'');
    if(!isset($mapped[$company])){$counts['driversSkipped']++;continue;}
    $aid=$mapped[$company];$identity=self::identity($c['D']??'');$status=self::status($c['N']??'');
    if(strlen($identity)!==10||$status==='unknown'){$counts['driversSkipped']++;$addIssue('drivers',$r['line'],'کد ملی یا وضعیت سپند نامعتبر است.');continue;}
    $vin=self::clean($c['G']??'');$vid=(int)($vehicleIds[$company.':'.$vin]??0);
    $findDriver->execute([$aid,$identity]);$matches=$findDriver->fetchAll(PDO::FETCH_COLUMN);
    if(count($matches)>1){$counts['driversSkipped']++;$addIssue('drivers',$r['line'],'چند راننده با یک کد ملی در شرکت ثبت شده‌اند.');continue;}
    $gender=self::clean($c['E']??'')==='خانم'?'female':(self::clean($c['E']??'')==='آقا'?'male':null);
    if($matches){
     $counts['driversUpdated']++;
     if($apply)$updateDriver->execute([$c['F']??'', $gender,$c['A']??null,$status,$vid,$vid,(int)$matches[0],$aid]);
    }else{
     $counts['driversCreated']++;
     if($apply)$createDriver->execute([Uuid::v4(),$aid,$c['B'],$c['C'],$identity,$c['F']??null,$vid?:null,$gender,$c['A']??null,$status]);
    }
    if($apply&&$vid)$setCarStatus->execute([$status,$vid,$aid]);
   }
   if($apply){
    // Never replace a known class from the Sepand report (no class exists
    // there). Restore only blank classes with unanimous historical evidence.
    $counts['vehicleClassesRecovered']=VehicleTariffClassRecovery::recoverKnownClasses($pdo);
    foreach($mapped as $national=>$id){
     $pdo->prepare('UPDATE agencies SET sepand_national_id=? WHERE id=? AND (sepand_national_id IS NULL OR sepand_national_id=?)')
      ->execute([$national,$id,$national]);
    }
    $pdo->prepare('INSERT INTO sepand_import_runs(file_hash,performed_by,source_driver_rows,source_vehicle_rows,driver_created,driver_updated,vehicle_created,vehicle_updated,skipped_rows,report_json) VALUES(?,?,?,?,?,?,?,?,?,?)')
     ->execute([$digest,$user,count($drivers),count($cars),$counts['driversCreated'],$counts['driversUpdated'],$counts['vehiclesCreated'],$counts['vehiclesUpdated'],$counts['driversSkipped']+$counts['vehiclesSkipped'],json_encode(['issues'=>$issues],JSON_UNESCAPED_UNICODE)]);
    $pdo->commit();
   }
  }catch(\Throwable $e){if($apply&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
  return ['digest'=>$digest,'applied'=>$apply,'counts'=>$counts,'companies'=>$options,'unmatched'=>$unmatched,'issues'=>$issues,'issueLimit'=>250];
 }
}

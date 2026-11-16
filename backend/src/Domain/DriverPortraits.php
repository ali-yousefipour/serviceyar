<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Driver portraits are bound to the driver record, including drivers without portal accounts. */
final class DriverPortraits {
 private static function path(int $agency,int $driver): string {
  return dirname(__DIR__,3).'/storage/uploads/driver-portraits/agency-'.$agency.'-driver-'.$driver.'.jpg';
 }
 private static function authorize(int $uid,int $agency,int $driver,bool $admin): void {
  if($agency<1||$driver<1)throw new AuthException(422,'شرکت یا راننده معتبر نیست.','VALIDATION_ERROR');
  if($admin){
   if(!Authorization::isSuperAdmin($uid))
    throw new AuthException(403,'فقط مدیر کل مجاز است.','FORBIDDEN');
  }else{
   PortalService::requireDriverMembership($uid,$agency,$driver);
  }
  $q=Connection::get()->prepare('SELECT 1 FROM drivers d JOIN agencies a ON a.id=d.agency_id AND a.is_active=1 WHERE d.id=? AND d.agency_id=? AND d.is_active=1');
  $q->execute([$driver,$agency]);
  if(!$q->fetchColumn())throw new AuthException(404,'راننده فعال در شرکت انتخاب‌شده یافت نشد.','DRIVER_NOT_FOUND');
 }
 public static function stream(int $uid,int $agency,int $driver,bool $admin=false): never {
  self::authorize($uid,$agency,$driver,$admin);
  $path=self::path($agency,$driver);
  if(!is_file($path))throw new AuthException(404,'تصویر پرسنلی ثبت نشده است.','PHOTO_NOT_FOUND');
  header('Content-Type: image/jpeg');header('Cache-Control: private, no-store');
  header('X-Content-Type-Options: nosniff');
  readfile($path);exit;
 }
 public static function upload(int $uid,int $agency,int $driver,bool $admin,array $file): array {
  self::authorize($uid,$agency,$driver,$admin);
  $tempFile=$file['tmp_name']??null;
  $size=(int)($file['size']??0);
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($tempFile)||
    !is_uploaded_file($tempFile)||$size<1||$size>5*1024*1024)
   throw new AuthException(422,'تصویر باید حداکثر پنج مگابایت باشد.','INVALID_PROFILE_PHOTO');
  $info=@getimagesize($tempFile);
  if(!$info||!in_array($info['mime']??'',['image/jpeg','image/png','image/webp'],true)||
   $info[0]<64||$info[1]<64||$info[0]>6000||$info[1]>6000||$info[0]*$info[1]>24000000)
   throw new AuthException(422,'فرمت یا اندازه تصویر معتبر نیست.','INVALID_PROFILE_PHOTO');
  if(!function_exists('imagecreatefromstring')||!function_exists('imagejpeg'))
   throw new AuthException(503,'پردازش تصویر در سرور فعال نیست.','IMAGE_PROCESSOR_UNAVAILABLE');
  $source=@imagecreatefromstring((string)file_get_contents($tempFile));
  if(!$source)throw new AuthException(422,'تصویر قابل پردازش نیست.','INVALID_PROFILE_PHOTO');
  if($info['mime']==='image/jpeg'&&function_exists('exif_read_data')){
   $exif=@exif_read_data($tempFile,'IFD0');$orientation=(int)($exif['Orientation']??1);
   if(in_array($orientation,[3,6,8],true)){
    $rotated=@imagerotate($source,[3=>180,6=>270,8=>90][$orientation],0);
    if($rotated){imagedestroy($source);$source=$rotated;}
   }
  }
  $width=imagesx($source);$height=imagesy($source);$side=min($width,$height);
  $output=imagecreatetruecolor(512,512);
  if(!$output){imagedestroy($source);throw new AuthException(503,'پردازش تصویر انجام نشد.','IMAGE_PROCESSOR_UNAVAILABLE');}
  imagefill($output,0,0,imagecolorallocate($output,255,255,255));
  imagecopyresampled($output,$source,0,0,(int)(($width-$side)/2),(int)(($height-$side)/2),512,512,$side,$side);
  imagedestroy($source);
  $destination=self::path($agency,$driver);$folder=dirname($destination);
  if(!is_dir($folder)&&!@mkdir($folder,0700,true)&&!is_dir($folder)){
   imagedestroy($output);throw new AuthException(503,'فضای ذخیره‌سازی تصویر آماده نیست.','STORAGE_UNAVAILABLE');
  }
  $temporary=tempnam($folder,'drv-');
  if($temporary===false){imagedestroy($output);throw new AuthException(503,'تصویر ذخیره نشد.','STORAGE_UNAVAILABLE');}
  $ok=imagejpeg($output,$temporary,80);imagedestroy($output);
  if(!$ok||!@chmod($temporary,0600)||!@rename($temporary,$destination)){
   @unlink($temporary);throw new AuthException(503,'ثبت تصویر ناموفق بود.','PHOTO_SAVE_FAILED');
  }
  Connection::get()->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id) VALUES(?,?,?,?,?)')
   ->execute([$agency,$uid,$admin?'admin_driver_portrait_uploaded':'driver_portrait_uploaded','drivers',$driver]);
  clearstatcache(true,$destination);
  return ['updated'=>true,'agencyId'=>$agency,'driverId'=>$driver,'version'=>(int)filemtime($destination)];
 }
 public static function info(int $uid,int $agency,int $driver,bool $admin): array {
  self::authorize($uid,$agency,$driver,$admin);
  $path=self::path($agency,$driver);
  return ['hasPhoto'=>is_file($path),'version'=>is_file($path)?(int)filemtime($path):0];
 }
}

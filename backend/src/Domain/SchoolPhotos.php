<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use PDO;

/**
 * School photographs: agency-scoped, authenticated read and upload.
 * Stored under storage/uploads, never exposed as a public /schools URL.
 */
final class SchoolPhotos {
 private static function school(int $uid,int $aid,int $id,bool $write=false): array {
  Authorization::requireAgencyAccess($uid,$aid);
  Authorization::requirePermission($uid,$write?'schools.manage':'schools.view');
  if($id<1)throw new AuthException(422,'مدرسه نامعتبر است.','INVALID_SCHOOL');
  $q=Connection::get()->prepare('SELECT id,agency_id,photo_path FROM schools WHERE id=? AND agency_id=? LIMIT 1');
  $q->execute([$id,$aid]);
  $row=$q->fetch(PDO::FETCH_ASSOC);
  if(!$row)throw new AuthException(404,'مدرسه پیدا نشد.','SCHOOL_NOT_FOUND');
  return $row;
 }
 private static function root(): string {
  return dirname(__DIR__,3); // repository root / public_html
 }
 /**
  * Normalize to a 4:3 landscape or 3:4 portrait JPEG.
  * Crop from the center without stretching; recompress toward 100 KiB.
  * Re-encoding also strips EXIF, GPS and original file metadata.
  *
  * @return array{bytes:string,width:int,height:int,sizeBytes:int}
  */
 public static function prepareImage(string $file,string $mime): array {
  if(!function_exists('imagecreatefromstring')||!function_exists('imagejpeg'))
   throw new AuthException(503,'پردازش تصویر روی سرور فعال نیست؛ افزونه GD باید نصب شود.','IMAGE_PROCESSOR_UNAVAILABLE');
  $sourceData=file_get_contents($file);
  if($sourceData===false||$sourceData==='')
   throw new AuthException(422,'فایل تصویر قابل خواندن نیست.','INVALID_IMAGE');
  $original=@imagecreatefromstring($sourceData);
  unset($sourceData);
  if(!$original)throw new AuthException(422,'فایل تصویر آسیب‌دیده یا نامعتبر است.','INVALID_IMAGE');
  try{
   if($mime==='image/jpeg'&&function_exists('exif_read_data')){
    $exif=@exif_read_data($file,'IFD0');
    $orientation=(int)($exif['Orientation']??1);
    if(in_array($orientation,[3,6,8],true)){
     $degrees=[3=>180,6=>270,8=>90][$orientation];
     $rotated=imagerotate($original,$degrees,0);
     if($rotated){imagedestroy($original);$original=$rotated;}
    }
    if(in_array($orientation,[2,4,5,7],true)){
     if(in_array($orientation,[5,7],true)){
      $rotated=imagerotate($original,$orientation===5?270:90,0);
      if($rotated){imagedestroy($original);$original=$rotated;}
     }
     imageflip($original,in_array($orientation,[2,5],true)?IMG_FLIP_HORIZONTAL:IMG_FLIP_VERTICAL);
    }
   }
   $width=imagesx($original);$height=imagesy($original);
   if($width<1||$height<1||$width>6000||$height>6000||$width*$height>20000000)
    throw new AuthException(422,'ابعاد تصویر نامعتبر یا بیش از حد بزرگ است.','IMAGE_TOO_LARGE');
   $landscape=$width>=$height;
   $targetWidth=$landscape?640:480;
   $targetHeight=$landscape?480:640;
   $ratio=max($targetWidth/$width,$targetHeight/$height);
   $cropWidth=min($width,(int)round($targetWidth/$ratio));
   $cropHeight=min($height,(int)round($targetHeight/$ratio));
   $sourceX=(int)floor(($width-$cropWidth)/2);
   $sourceY=(int)floor(($height-$cropHeight)/2);
   $canvas=imagecreatetruecolor($targetWidth,$targetHeight);
   if(!$canvas)throw new AuthException(503,'پردازش تصویر امکان‌پذیر نیست.','IMAGE_PROCESSOR_UNAVAILABLE');
   try{
    $white=imagecolorallocate($canvas,255,255,255);
    imagefill($canvas,0,0,$white);
    if(!imagecopyresampled($canvas,$original,0,0,$sourceX,$sourceY,$targetWidth,$targetHeight,$cropWidth,$cropHeight))
     throw new AuthException(422,'تغییر اندازه تصویر انجام نشد.','IMAGE_RESIZE_FAILED');
    $best='';
    foreach([82,76,70,64,58,52,46,40,34,28,22,16,10] as $quality){
     ob_start();
     $ok=imagejpeg($canvas,null,$quality);
     $bytes=ob_get_clean();
     if(!$ok||!is_string($bytes)||$bytes==='')continue;
     $best=$bytes;
     if(strlen($bytes)<=110*1024)break;
    }
    if($best===''||strlen($best)>110*1024)
     throw new AuthException(422,'فشرده‌سازی تصویر تا حدود ۱۰۰ کیلوبایت امکان‌پذیر نبود.','IMAGE_COMPRESSION_FAILED');
    return ['bytes'=>$best,'width'=>$targetWidth,'height'=>$targetHeight,'sizeBytes'=>strlen($best)];
   }finally{imagedestroy($canvas);}
  }finally{imagedestroy($original);}
 }
 public static function upload(int $uid,int $aid,int $id,array $files): array {
  self::school($uid,$aid,$id,true);
  $upload=$files['photo']??null;
  if(!is_array($upload)||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)
   throw new AuthException(422,'تصویر مدرسه بارگذاری نشده یا انتقال آن ناموفق بوده است.','PHOTO_UPLOAD_ERROR');
  $tmp=(string)($upload['tmp_name']??'');
  $size=(int)($upload['size']??0);
  if($size<1||$size>5*1024*1024||!is_uploaded_file($tmp))
   throw new AuthException(422,'تصویر باید معتبر و حداکثر ۵ مگابایت باشد.','INVALID_IMAGE');
  $type=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
  $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if(!isset($extensions[$type])||getimagesize($tmp)===false)
   throw new AuthException(422,'فقط تصاویر JPEG، PNG و WebP قابل بارگذاری هستند.','INVALID_IMAGE_TYPE');
  $dimensions=getimagesize($tmp);
  if(($dimensions[0]??0)>6000||($dimensions[1]??0)>6000)
   throw new AuthException(422,'ابعاد تصویر نباید بیشتر از ۶۰۰۰ پیکسل باشد.','IMAGE_TOO_LARGE');
  $compressed=self::prepareImage($tmp,$type);
  $directory=self::root().'/storage/uploads/school-images/'.$aid.'/'.$id;
  if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))
   throw new AuthException(503,'فضای ذخیره‌سازی تصاویر در دسترس نیست.','PHOTO_STORAGE_UNAVAILABLE');
  $name=bin2hex(random_bytes(16)).'.jpg';
  $destination=$directory.'/'.$name;
  if(file_put_contents($destination,$compressed['bytes'],LOCK_EX)!==$compressed['sizeBytes']){
   @unlink($destination);
   throw new AuthException(503,'ذخیره تصویر فشرده‌شده مدرسه انجام نشد.','PHOTO_SAVE_FAILED');
  }
  @chmod($destination,0640);
  $relative='private-schools/'.$aid.'/'.$id.'/'.$name;
  try{
   $update=Connection::get()->prepare('UPDATE schools SET photo_path=? WHERE id=? AND agency_id=?');
   $update->execute([$relative,$id,$aid]);
  }catch(\Throwable $e){@unlink($destination);throw $e;}
  return ['schoolId'=>$id,'hasPhoto'=>true,'width'=>$compressed['width'],'height'=>$compressed['height'],'sizeBytes'=>$compressed['sizeBytes'],'mime'=>'image/jpeg'];
 }
 public static function stream(int $uid,int $aid,int $id): never {
  $row=self::school($uid,$aid,$id);
  $raw=(string)($row['photo_path']??'');
  $path=null;
  if(preg_match('~^private-schools/([0-9]+)/([0-9]+)/([a-f0-9]{32})\.(jpg|png|webp)$~D',$raw,$m)
    &&(int)$m[1]===$aid&&(int)$m[2]===$id){
   $path=self::root().'/storage/uploads/school-images/'.$aid.'/'.$id.'/'.$m[3].'.'.$m[4];
  }elseif(preg_match('~^(?:uploads/)?schools/((?:[0-9]{4}/[0-9]{2}/)?[a-zA-Z0-9_.-]+\.(?:jpg|jpeg|png|webp))$~iD',$raw,$m)){
   $path=self::root().'/schools/'.$m[1]; // Existing migrated images on this host.
  }
  if(!$path||!is_file($path)||!is_readable($path))
   throw new AuthException(404,'تصویری برای این مدرسه موجود نیست.','PHOTO_NOT_FOUND');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
  if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))
   throw new AuthException(404,'فایل تصویر معتبر نیست.','INVALID_IMAGE');
  header('Content-Type: '.$mime);
  header('Content-Length: '.filesize($path));
  header('Cache-Control: private, no-store, max-age=0');
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: inline; filename="school-photo.'.(['image/png'=>'png','image/webp'=>'webp'][$mime]??'jpg').'"');
  readfile($path);
  exit;
 }
}

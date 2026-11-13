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
  $directory=self::root().'/storage/uploads/school-images/'.$aid.'/'.$id;
  if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))
   throw new AuthException(503,'فضای ذخیره‌سازی تصاویر در دسترس نیست.','PHOTO_STORAGE_UNAVAILABLE');
  $name=bin2hex(random_bytes(16)).'.'.$extensions[$type];
  $destination=$directory.'/'.$name;
  if(!move_uploaded_file($tmp,$destination))
   throw new AuthException(503,'ذخیره تصویر مدرسه انجام نشد.','PHOTO_SAVE_FAILED');
  @chmod($destination,0640);
  $relative='private-schools/'.$aid.'/'.$id.'/'.$name;
  try{
   $update=Connection::get()->prepare('UPDATE schools SET photo_path=? WHERE id=? AND agency_id=?');
   $update->execute([$relative,$id,$aid]);
  }catch(\Throwable $e){@unlink($destination);throw $e;}
  return ['schoolId'=>$id,'hasPhoto'=>true];
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

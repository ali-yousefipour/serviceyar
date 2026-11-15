<?php
declare(strict_types=1);
namespace ServiceYar\Users;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use ServiceYar\Support\Uuid;

/** Private profile images, only visible to the account or authorized user managers. */
final class UserPhotos {
 private static function path(int $id): string {
  return dirname(__DIR__,3).'/storage/uploads/user-profiles/user-'.$id.'.jpg';
 }
 private static function access(int $actor,int $agency,int $target,bool $write): void {
  if($target<1)throw new AuthException(422,'کاربر نامعتبر است.','INVALID_USER');
  if($actor!==$target){
   if($agency>0){
    Authorization::requireAgencyAccess($actor,$agency);
    Authorization::requirePermission($actor,$write?'users.manage':'users.view');
    UserService::find($actor,$agency,$target);
    if($write) {
     // Keep the manager's existing restrictions on shared and privileged users.
     $q=Connection::get()->prepare("SELECT COUNT(*) FROM agency_users WHERE user_id=?");
     $q->execute([$target]);
     $role=Connection::get()->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?");
     $role->execute([$target]);
     if((int)$q->fetchColumn()>1||array_intersect(['super_admin','agency_admin'],$role->fetchAll(\PDO::FETCH_COLUMN)))
      throw new AuthException(403,'ویرایش تصویر حساب مدیر یا مشترک مجاز نیست.','PROTECTED_USER');
    }
   }else{
    if(!Authorization::isSuperAdmin($actor))
     throw new AuthException(403,'فقط مدیر کل مجاز است.','FORBIDDEN');
    UserService::find($actor,0,$target);
   }
  }
  $q=Connection::get()->prepare('SELECT id FROM users WHERE id=? AND is_active=1');
  $q->execute([$target]);
  if(!$q->fetchColumn())throw new AuthException(404,'کاربر فعال یافت نشد.','NOT_FOUND');
 }
 public static function stream(int $actor,int $agency,int $target): never {
  self::access($actor,$agency,$target,false);
  $path=self::path($target);
  if(!is_file($path))throw new AuthException(404,'برای این کاربر عکسی ثبت نشده است.','PHOTO_NOT_FOUND');
  header('Content-Type: image/jpeg');
  header('Cache-Control: private, no-store, max-age=0');
  header('X-Content-Type-Options: nosniff');
  readfile($path);exit;
 }
 public static function upload(int $actor,int $agency,int $target,array $file): array {
  self::access($actor,$agency,$target,true);
  $tmp=$file['tmp_name']??null;$size=(int)($file['size']??0);
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($tmp)||!is_uploaded_file($tmp)||$size<1||$size>5*1024*1024)
   throw new AuthException(422,'تصویر باید حداکثر ۵ مگابایت باشد.','INVALID_PROFILE_PHOTO');
  $info=@getimagesize($tmp);
  if(!$info||!in_array($info['mime']??'',['image/jpeg','image/png','image/webp'],true)||
    $info[0]<64||$info[1]<64||$info[0]>6000||$info[1]>6000||($info[0]*$info[1])>24000000)
   throw new AuthException(422,'فرمت یا ابعاد تصویر معتبر نیست.','INVALID_PROFILE_PHOTO');
  if(!function_exists('imagecreatefromstring')||!function_exists('imagejpeg'))
   throw new AuthException(503,'پردازش تصویر روی سرور فعال نیست.','IMAGE_PROCESSOR_UNAVAILABLE');
  $src=@imagecreatefromstring((string)file_get_contents($tmp));
  if(!$src)throw new AuthException(422,'فایل تصویر قابل خواندن نیست.','INVALID_PROFILE_PHOTO');
  $width=imagesx($src);$height=imagesy($src);$side=min($width,$height);
  $out=imagecreatetruecolor(512,512);
  if(!$out){imagedestroy($src);throw new AuthException(503,'پردازش تصویر انجام نشد.','IMAGE_PROCESSOR_UNAVAILABLE');}
  imagefill($out,0,0,imagecolorallocate($out,255,255,255));
  imagecopyresampled($out,$src,0,0,(int)(($width-$side)/2),(int)(($height-$side)/2),512,512,$side,$side);
  imagedestroy($src);
  $dir=dirname(self::path($target));
  if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir)){
   imagedestroy($out);throw new AuthException(503,'محل ذخیره تصویر در دسترس نیست.','STORAGE_UNAVAILABLE');
  }
  $temp=tempnam($dir,'usr-');
  if($temp===false){imagedestroy($out);throw new AuthException(503,'تصویر ذخیره نشد.','STORAGE_UNAVAILABLE');}
  $written=imagejpeg($out,$temp,80);imagedestroy($out);
  if(!$written||!@chmod($temp,0600)||!@rename($temp,self::path($target))){
   @unlink($temp);throw new AuthException(503,'تصویر کاربر ذخیره نشد.','PHOTO_SAVE_FAILED');
  }
  $p=Connection::get();
  $p->prepare('UPDATE users SET photo_version=photo_version+1 WHERE id=?')->execute([$target]);
  $log=$p->prepare('INSERT INTO audit_logs(uuid,user_id,action,entity_type,entity_id) VALUES(?,?,?,?,?)');
  $log->execute([Uuid::v4(),$actor,'user.photo_uploaded','user',(string)$target]);
  return ['updated'=>true,'userId'=>$target,'photoVersion'=>(int)$p->query('SELECT photo_version FROM users WHERE id='.(int)$target)->fetchColumn()];
 }
 public static function remove(int $actor,int $agency,int $target): array {
  self::access($actor,$agency,$target,true);
  $path=self::path($target);
  if(is_file($path)&&!@unlink($path))throw new AuthException(503,'حذف تصویر ناموفق بود.','PHOTO_DELETE_FAILED');
  $p=Connection::get();
  $p->prepare('UPDATE users SET photo_version=photo_version+1 WHERE id=?')->execute([$target]);
  $p->prepare('INSERT INTO audit_logs(uuid,user_id,action,entity_type,entity_id) VALUES(?,?,?,?,?)')
   ->execute([Uuid::v4(),$actor,'user.photo_removed','user',(string)$target]);
  return ['removed'=>true,'userId'=>$target];
 }
}

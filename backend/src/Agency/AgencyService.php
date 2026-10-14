<?php
declare(strict_types=1);
namespace ServiceYar\Agency;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use ServiceYar\Support\Uuid;
final class AgencyService {
 public static function ids(int $userId): array {
  if (Authorization::isSuperAdmin($userId)) {
   $s=Connection::get()->query('SELECT id FROM agencies WHERE is_active=1 ORDER BY id');
   return array_map('intval',$s->fetchAll(\PDO::FETCH_COLUMN));
  }
  $s=Connection::get()->prepare('SELECT a.id FROM agencies a JOIN agency_users au ON au.agency_id=a.id WHERE au.user_id=:u AND a.is_active=1 ORDER BY a.id');
  $s->execute(['u'=>$userId]); return array_map('intval',$s->fetchAll(\PDO::FETCH_COLUMN));
 }
 public static function currentId(int $userId): int {
  $ids=self::ids($userId); if(!$ids) throw new \ServiceYar\Auth\AuthException(403,'کاربر به هیچ شرکت فعالی دسترسی ندارد.','NO_AGENCY_SCOPE'); return $ids[0];
 }
 public static function list(int $userId): array {
  Authorization::requirePermission($userId,'agencies.view');
  if (Authorization::isSuperAdmin($userId)) return Connection::get()->query('SELECT id,uuid,name,code,city_id,is_active,created_at,updated_at FROM agencies ORDER BY name')->fetchAll();
  $ids=self::ids($userId); if(!$ids)return [];
  $in=implode(',',array_fill(0,count($ids),'?')); $s=Connection::get()->prepare("SELECT id,uuid,name,code,city_id,is_active,created_at,updated_at FROM agencies WHERE id IN ($in) ORDER BY name"); $s->execute($ids); return $s->fetchAll();
 }
 public static function update(int $userId,int $id,array $b): array {
  Authorization::requirePermission($userId,'agencies.manage'); if(!Authorization::isSuperAdmin($userId)) Authorization::requireAgencyAccess($userId,$id);
  $name=trim((string)($b['name']??'')); if($name==='') throw new \ServiceYar\Auth\AuthException(422,'نام شرکت الزامی است.','VALIDATION_ERROR');
  $p=Connection::get()->prepare('UPDATE agencies SET name=:n,code=:c,city_id=:city,is_active=:active WHERE id=:id'); $p->execute(['n'=>$name,'c'=>trim((string)($b['code']??''))!==''?$b['code']:null, 'city'=>isset($b['cityId'])&&$b['cityId']!==''?(int)$b['cityId']:null,'active'=>isset($b['isActive'])?(int)(bool)$b['isActive']:1,'id'=>$id]);
  $s=Connection::get()->prepare('SELECT id,uuid,name,code,city_id,is_active,created_at,updated_at FROM agencies WHERE id=:id'); $s->execute(['id'=>$id]); return $s->fetch() ?: [];
 }
 public static function create(int $userId,array $b): array {
  Authorization::requirePermission($userId,'agencies.manage'); $name=trim((string)($b['name']??'')); if($name==='') throw new \ServiceYar\Auth\AuthException(422,'نام شرکت الزامی است.','VALIDATION_ERROR');
  $p=Connection::get()->prepare('INSERT INTO agencies(uuid,name,code,city_id) VALUES(:u,:n,:c,:city)'); $p->execute(['u'=>Uuid::v4(),'n'=>$name,'c'=>trim((string)($b['code']??''))!==''?$b['code']:null,'city'=>isset($b['cityId'])&&$b['cityId']!==''?(int)$b['cityId']:null]);
  $id=(int)Connection::get()->lastInsertId(); Connection::get()->prepare('INSERT IGNORE INTO agency_users(agency_id,user_id) VALUES(:a,:u)')->execute(['a'=>$id,'u'=>$userId]);
  return self::update($userId,$id,$b);
 }
 public static function deactivate(int $userId,int $id): void {
  Authorization::requirePermission($userId,'agencies.manage');
  if (!Authorization::isSuperAdmin($userId)) throw new \ServiceYar\Auth\AuthException(403,'فقط مدیر کل می‌تواند شرکت را غیرفعال کند.','SUPER_ADMIN_REQUIRED');
  $p=Connection::get()->prepare('UPDATE agencies SET is_active=0 WHERE id=:id');$p->execute(['id'=>$id]);
  if($p->rowCount()===0){$q=Connection::get()->prepare('SELECT id FROM agencies WHERE id=:id');$q->execute(['id'=>$id]);if(!$q->fetchColumn())throw new \ServiceYar\Auth\AuthException(404,'شرکت پیدا نشد.','NOT_FOUND');}
 }
 public static function users(int $userId,int $agencyId): array {
  Authorization::requirePermission($userId,'agencies.manage');
  if (!Authorization::isSuperAdmin($userId)) throw new \ServiceYar\Auth\AuthException(403,'فقط مدیر کل می‌تواند کاربران شرکت‌ها را تعیین کند.','SUPER_ADMIN_REQUIRED');
  $pdo=Connection::get();$q=$pdo->prepare('SELECT id FROM agencies WHERE id=:id');$q->execute(['id'=>$agencyId]);if(!$q->fetchColumn())throw new \ServiceYar\Auth\AuthException(404,'شرکت پیدا نشد.','NOT_FOUND');
  $users=$pdo->query('SELECT id,username,first_name,last_name,is_active FROM users ORDER BY username')->fetchAll();
  $q=$pdo->prepare('SELECT user_id FROM agency_users WHERE agency_id=:id ORDER BY user_id');$q->execute(['id'=>$agencyId]);
  return ['users'=>$users,'assignedUserIds'=>array_map('intval',$q->fetchAll(\PDO::FETCH_COLUMN))];
 }
 public static function assignUsers(int $userId,int $agencyId,array $userIds): array {
  Authorization::requirePermission($userId,'agencies.manage');
  if (!Authorization::isSuperAdmin($userId)) throw new \ServiceYar\Auth\AuthException(403,'فقط مدیر کل می‌تواند کاربران شرکت‌ها را تعیین کند.','SUPER_ADMIN_REQUIRED');
  $pdo=Connection::get();$pdo->beginTransaction();
  try {
   $q=$pdo->prepare('SELECT id FROM agencies WHERE id=:id FOR UPDATE');$q->execute(['id'=>$agencyId]);if(!$q->fetchColumn())throw new \ServiceYar\Auth\AuthException(404,'شرکت پیدا نشد.','NOT_FOUND');
   $ids=array_values(array_unique(array_filter(array_map('intval',$userIds),static fn($id)=>$id>0)));
   if($ids){$in=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT id FROM users WHERE id IN ($in)");$q->execute($ids);$found=array_map('intval',$q->fetchAll(\PDO::FETCH_COLUMN));if(count($found)!==count($ids))throw new \ServiceYar\Auth\AuthException(422,'یکی از حساب‌های انتخاب‌شده وجود ندارد.','VALIDATION_ERROR');}
   $q=$pdo->prepare('DELETE FROM agency_users WHERE agency_id=:id');$q->execute(['id'=>$agencyId]);
   if($ids){$q=$pdo->prepare('INSERT INTO agency_users(agency_id,user_id) VALUES(:a,:u)');foreach($ids as $uid)$q->execute(['a'=>$agencyId,'u'=>$uid]);}
   $pdo->commit();return self::users($userId,$agencyId);
  } catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }

}

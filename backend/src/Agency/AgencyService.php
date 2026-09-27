<?php
declare(strict_types=1);
namespace ServiceYar\Agency;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use ServiceYar\Support\Uuid;
final class AgencyService {
 public static function ids(int $userId): array {
  $s=Connection::get()->prepare('SELECT a.id FROM agencies a JOIN agency_users au ON au.agency_id=a.id WHERE au.user_id=:u AND a.is_active=1 ORDER BY a.id');
  $s->execute(['u'=>$userId]); return array_map('intval',$s->fetchAll(\PDO::FETCH_COLUMN));
 }
 public static function currentId(int $userId): int {
  $ids=self::ids($userId); if(!$ids) throw new \ServiceYar\Auth\AuthException(403,'کاربر به هیچ سازمان فعالی دسترسی ندارد.','NO_AGENCY_SCOPE'); return $ids[0];
 }
 public static function list(int $userId): array {
  Authorization::requirePermission($userId,'agencies.view'); $ids=self::ids($userId); if(!$ids)return [];
  $in=implode(',',array_fill(0,count($ids),'?')); $s=Connection::get()->prepare("SELECT id,uuid,name,code,city_id,is_active,created_at,updated_at FROM agencies WHERE id IN ($in) ORDER BY name"); $s->execute($ids); return $s->fetchAll();
 }
 public static function update(int $userId,int $id,array $b): array {
  Authorization::requirePermission($userId,'agencies.manage'); Authorization::requireAgencyAccess($userId,$id);
  $name=trim((string)($b['name']??'')); if($name==='') throw new \ServiceYar\Auth\AuthException(422,'نام سازمان الزامی است.','VALIDATION_ERROR');
  $p=Connection::get()->prepare('UPDATE agencies SET name=:n,code=:c,city_id=:city WHERE id=:id'); $p->execute(['n'=>$name,'c'=>($b['code']??null), 'city'=>isset($b['cityId'])?(int)$b['cityId']:null,'id'=>$id]);
  $s=Connection::get()->prepare('SELECT id,uuid,name,code,city_id,is_active,created_at,updated_at FROM agencies WHERE id=:id'); $s->execute(['id'=>$id]); return $s->fetch() ?: [];
 }
 public static function create(int $userId,array $b): array {
  Authorization::requirePermission($userId,'agencies.manage'); $name=trim((string)($b['name']??'')); if($name==='') throw new \ServiceYar\Auth\AuthException(422,'نام سازمان الزامی است.','VALIDATION_ERROR');
  $p=Connection::get()->prepare('INSERT INTO agencies(uuid,name,code,city_id) VALUES(:u,:n,:c,:city)'); $p->execute(['u'=>Uuid::v4(),'n'=>$name,'c'=>$b['code']??null,'city'=>isset($b['cityId'])?(int)$b['cityId']:null]);
  $id=(int)Connection::get()->lastInsertId(); Connection::get()->prepare('INSERT IGNORE INTO agency_users(agency_id,user_id) VALUES(:a,:u)')->execute(['a'=>$id,'u'=>$userId]);
  return self::update($userId,$id,$b);
 }
}

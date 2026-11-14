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
  $pdo=Connection::get();
  $where='';$args=[];
  if(!Authorization::isSuperAdmin($userId)){
   $ids=self::ids($userId);if(!$ids)return [];
   $where=' WHERE a.id IN ('.implode(',',array_fill(0,count($ids),'?')).')';
   $args=$ids;
  }
  $sql="SELECT a.id,a.uuid,a.name,a.code,a.city_id,a.sepand_national_id,a.is_active,a.created_at,a.updated_at,
    (SELECT st.setting_value FROM agency_settings st WHERE st.agency_id=a.id AND st.setting_key='company_manager_name') AS manager_name,
    (SELECT st.setting_value FROM agency_settings st WHERE st.agency_id=a.id AND st.setting_key='company_ceo_mobile') AS manager_mobile,
    (SELECT st.setting_value FROM agency_settings st WHERE st.agency_id=a.id AND st.setting_key='company_phone') AS office_phone,
    (SELECT st.setting_value FROM agency_settings st WHERE st.agency_id=a.id AND st.setting_key='company_address') AS office_address,
    (SELECT st.setting_value FROM agency_settings st WHERE st.agency_id=a.id AND st.setting_key='company_location') AS location_json
    FROM agencies a".$where." ORDER BY a.name";
  $stmt=$pdo->prepare($sql);$stmt->execute($args);
  $rows=$stmt->fetchAll(\PDO::FETCH_ASSOC);
  foreach($rows as &$row){
   $loc=json_decode((string)($row['location_json']??''),true);
   $row['latitude']=is_array($loc)&&is_numeric($loc['latitude']??null)?(float)$loc['latitude']:null;
   $row['longitude']=is_array($loc)&&is_numeric($loc['longitude']??null)?(float)$loc['longitude']:null;
   unset($row['location_json']);
  }
  unset($row);
  return $rows;
 }
 public static function sessionList(int $userId): array {
  // /auth/me must return the caller's own agency scope even when their role
  // cannot manage or browse the agencies administration endpoint.
  if (Authorization::isSuperAdmin($userId)) {
   return Connection::get()->query('SELECT id,uuid,name,code,city_id,is_active,created_at,updated_at FROM agencies ORDER BY name')->fetchAll();
  }
  $ids=self::ids($userId);
  if(!$ids)return [];
  $in=implode(',',array_fill(0,count($ids),'?'));
  $s=Connection::get()->prepare("SELECT id,uuid,name,code,city_id,is_active,created_at,updated_at FROM agencies WHERE id IN ($in) ORDER BY name");
  $s->execute($ids);
  return $s->fetchAll();
 }
 public static function update(int $userId,int $id,array $b): array {
  Authorization::requirePermission($userId,'agencies.manage'); if(!Authorization::isSuperAdmin($userId)) Authorization::requireAgencyAccess($userId,$id);
  $q=Connection::get()->prepare('SELECT is_active FROM agencies WHERE id=?');$q->execute([$id]);$old=$q->fetchColumn();if($old===false)throw new \ServiceYar\Auth\AuthException(404,'شرکت پیدا نشد.','NOT_FOUND');
  if(isset($b['isActive'])&&!in_array($b['isActive'],[0,1,'0','1'],true))throw new \ServiceYar\Auth\AuthException(422,'وضعیت نامعتبر است.','VALIDATION_ERROR');
  if(isset($b['isActive'])&&(int)$b['isActive']!==(int)$old&&!Authorization::isSuperAdmin($userId))throw new \ServiceYar\Auth\AuthException(403,'فقط مدیر کل می‌تواند وضعیت شرکت را تغییر دهد.','FORBIDDEN');
  $name=trim((string)($b['name']??'')); if($name==='') throw new \ServiceYar\Auth\AuthException(422,'نام شرکت الزامی است.','VALIDATION_ERROR');
  if(array_key_exists('sepandNationalId',$b)){
   if(!Authorization::isSuperAdmin($userId))throw new \ServiceYar\Auth\AuthException(403,'فقط ادمین کل می‌تواند شناسه ملی سپند شرکت را تغییر دهد.','SUPER_ADMIN_REQUIRED');
   $national=preg_replace('/\D/','',strtr((string)$b['sepandNationalId'],['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']));
   if($national!==''&&!preg_match('/^\d{11}$/D',$national))throw new \ServiceYar\Auth\AuthException(422,'شناسه ملی شرکت باید دقیقاً ۱۱ رقم باشد.','INVALID_COMPANY_NATIONAL_ID');
   if($national!==''){
    $unique=Connection::get()->prepare('SELECT id FROM agencies WHERE sepand_national_id=? AND id<>? LIMIT 1');
    $unique->execute([$national,$id]);
    if($unique->fetchColumn())throw new \ServiceYar\Auth\AuthException(409,'این شناسه ملی قبلاً برای شرکت دیگری ثبت شده است.','DUPLICATE_COMPANY_NATIONAL_ID');
   }
   Connection::get()->prepare('UPDATE agencies SET sepand_national_id=? WHERE id=?')->execute([$national!==''?$national:null,$id]);
  }
  $p=Connection::get()->prepare('UPDATE agencies SET name=:n,code=:c,city_id=:city,is_active=:active WHERE id=:id'); $p->execute(['n'=>$name,'c'=>trim((string)($b['code']??''))!==''?$b['code']:null, 'city'=>isset($b['cityId'])&&$b['cityId']!==''?(int)$b['cityId']:null,'active'=>isset($b['isActive'])?(int)$b['isActive']:(int)$old,'id'=>$id]);
  $profileKeys=['company_manager_name','company_ceo_mobile','company_phone','company_address'];
 $profile=[];
 foreach($profileKeys as $key){if(array_key_exists($key,$b)){$value=trim((string)$b[$key]);if(mb_strlen($value,'UTF-8')>450)throw new \ServiceYar\Auth\AuthException(422,'اطلاعات شرکت بیش از حد طولانی است.','VALIDATION_ERROR');$profile[$key]=$value;}}
 if(array_key_exists('latitude',$b)||array_key_exists('longitude',$b)){
  if($b['latitude']!==''&&$b['longitude']!==''&&$b['latitude']!==null&&$b['longitude']!==null)
   $profile['company_location']=\ServiceYar\Integrations\Neshan::coordinate($b['latitude'],$b['longitude']);
 }
 if($profile)\ServiceYar\Settings\SettingsService::put($userId,$id,$profile);
 $s=Connection::get()->prepare('SELECT id,uuid,name,code,city_id,sepand_national_id,is_active,created_at,updated_at FROM agencies WHERE id=:id'); $s->execute(['id'=>$id]); return $s->fetch() ?: [];
 }
 public static function create(int $userId,array $b): array {
  Authorization::requirePermission($userId,'agencies.manage'); $name=trim((string)($b['name']??'')); if($name==='') throw new \ServiceYar\Auth\AuthException(422,'نام شرکت الزامی است.','VALIDATION_ERROR');
  $p=Connection::get()->prepare('INSERT INTO agencies(uuid,name,code,city_id) VALUES(:u,:n,:c,:city)'); $p->execute(['u'=>Uuid::v4(),'n'=>$name,'c'=>trim((string)($b['code']??''))!==''?$b['code']:null,'city'=>isset($b['cityId'])&&$b['cityId']!==''?(int)$b['cityId']:null]);
  $id=(int)Connection::get()->lastInsertId(); Connection::get()->prepare('INSERT IGNORE INTO agency_users(agency_id,user_id) VALUES(:a,:u)')->execute(['a'=>$id,'u'=>$userId]);
  return self::update($userId,$id,$b);
 }
 public static function activate(int $userId,int $id): void {
  Authorization::requirePermission($userId,'agencies.manage');
  if(!Authorization::isSuperAdmin($userId))throw new \ServiceYar\Auth\AuthException(403,'فقط مدیر کل می‌تواند شرکت را فعال کند.','SUPER_ADMIN_REQUIRED');
  $pdo=Connection::get();$stmt=$pdo->prepare('UPDATE agencies SET is_active=1 WHERE id=? AND is_active=0');$stmt->execute([$id]);
  if($stmt->rowCount()===0){$check=$pdo->prepare('SELECT id FROM agencies WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())throw new \ServiceYar\Auth\AuthException(404,'شرکت پیدا نشد.','NOT_FOUND');}
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

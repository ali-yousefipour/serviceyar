<?php
declare(strict_types=1);
namespace ServiceYar\Users;
use ServiceYar\Auth\Authorization as A;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
use ServiceYar\Support\Uuid;
use ServiceYar\Domain\OperationsService as O;
final class UserService {
 private static function scope(int $u,int $a,string $op): void {A::requirePermission($u,'users.'.$op);if($a)A::requireAgencyAccess($u,$a);elseif(!A::isSuperAdmin($u))throw new AuthException(403,'فقط مدیر کل مجاز است.','FORBIDDEN');}
 private static function profileColumns(\PDO $pdo): array {
  return $pdo->query('SHOW COLUMNS FROM users')->fetchAll(\PDO::FETCH_COLUMN);
 }
 private static function requireProfileColumns(\PDO $pdo): void {
  if(!in_array('photo_version',self::profileColumns($pdo),true))
   throw new AuthException(503,'ابتدا مهاجرت دیتابیس 0109_user_profiles_photos.sql را اجرا کنید.','USER_PROFILE_SCHEMA_REQUIRED');
 }
 public static function list(int $u,int $a): array {
  self::scope($u,$a,'view');
  $pdo=Connection::get();$cols=self::profileColumns($pdo);
  $select=['mobile','national_code','email','address','postal_code','emergency_phone','photo_version'];
  $extras=[];
  foreach($select as $col)$extras[]=in_array($col,$cols,true)?'u.'.$col:$pdo->quote(''). ' AS '.$col;
  $notDeleted=in_array('deleted_at',$cols,true)?'u.deleted_at IS NULL AND ':'';
  $where=$a?'EXISTS(SELECT 1 FROM agency_users au WHERE au.user_id=u.id AND au.agency_id=?)':'1=1';
  $sql='SELECT u.id,u.username,u.first_name,u.last_name,u.is_active,'.implode(',',$extras).',
    GROUP_CONCAT(DISTINCT r.title) roles,GROUP_CONCAT(DISTINCT r.name) role_names
    FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id
    WHERE '.$notDeleted.$where.' GROUP BY u.id ORDER BY u.id DESC';
  $q=$pdo->prepare($sql);$q->execute($a?[$a]:[]);
  $rows=$q->fetchAll();
  foreach($rows as &$row)$row['has_photo']=UserPhotos::hasPhoto((int)$row['id']);
  unset($row);
  return $rows;
 }
 public static function find(int $u,int $a,int $id): array {self::scope($u,$a,'view');foreach(self::list($u,$a) as $r)if((int)$r['id']===$id)return $r;throw new AuthException(404,'کاربر پیدا نشد.','NOT_FOUND');}
 private static function editable(int $u,int $a,int $id): array {$r=self::find($u,$a,$id);$q=Connection::get()->prepare('SELECT COUNT(*) FROM agency_users WHERE user_id=?');$q->execute([$id]);if(!A::isSuperAdmin($u)&&((bool)array_intersect(['super_admin','agency_admin','school_company_manager'],explode(',',(string)$r['role_names']))||(int)$q->fetchColumn()>1))throw new AuthException(403,'ویرایش حساب مشترک یا مدیر کل فقط توسط مدیر کل مجاز است.','PROTECTED_USER');return $r;}
 public static function profile(int $userId): array {
  $p=Connection::get();self::requireProfileColumns($p);
  $q=$p->prepare('SELECT id,username,first_name,last_name,mobile,national_code,email,address,postal_code,emergency_phone,photo_version FROM users WHERE id=? AND is_active=1');
  $q->execute([$userId]);$row=$q->fetch(\PDO::FETCH_ASSOC);
  if(!$row)throw new AuthException(404,'حساب کاربری پیدا نشد.','NOT_FOUND');
  $row['has_photo']=UserPhotos::hasPhoto((int)$userId);
  return $row;
 }
 private static function profileFields(array $body): array {
  $rules=['mobile'=>15,'nationalCode'=>10,'email'=>190,'address'=>1000,'postalCode'=>10,'emergencyPhone'=>15];
  $data=[];
  foreach($rules as $key=>$limit){
   if(!array_key_exists($key,$body))continue;
   $value=$body[$key];
   if(!is_string($value))throw new AuthException(422,'اطلاعات پروفایل معتبر نیست.','INVALID_PROFILE');
   $value=trim(strtr($value,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
   if(mb_strlen($value)>$limit||preg_match('/[\x00-\x1F\x7F]/u',$value))throw new AuthException(422,'طول یا کاراکترهای اطلاعات تکمیلی نامعتبر است.','INVALID_PROFILE');
   $data[preg_replace_callback('/[A-Z]/',fn($m)=>'_'.strtolower($m[0]),$key)]=$value===''?null:$value;
  }
  if(isset($data['mobile'])&&!preg_match('/^09[0-9]{9}$/D',$data['mobile']))
   throw new AuthException(422,'شماره همراه باید ۱۱ رقم و با ۰۹ آغاز شود.','INVALID_MOBILE');
  if(isset($data['emergency_phone'])&&!preg_match('/^09[0-9]{9}$/D',$data['emergency_phone']))
   throw new AuthException(422,'شماره تماس اضطراری باید ۱۱ رقم و با ۰۹ آغاز شود.','INVALID_EMERGENCY_PHONE');
  if(isset($data['postal_code'])&&!preg_match('/^[0-9]{10}$/D',$data['postal_code']))
   throw new AuthException(422,'کد پستی باید ۱۰ رقم باشد.','INVALID_POSTAL_CODE');
  if(isset($data['email'])&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))
   throw new AuthException(422,'نشانی ایمیل معتبر نیست.','INVALID_EMAIL');
  if(isset($data['national_code'])){
   $n=$data['national_code'];
   if(!preg_match('/^[0-9]{10}$/D',$n)||preg_match('/^([0-9])\1{9}$/',$n))
    throw new AuthException(422,'کد ملی معتبر نیست.','INVALID_NATIONAL_CODE');
   $sum=0;for($i=0;$i<9;$i++)$sum+=(int)$n[$i]*(10-$i);
   $remaining=$sum%11;if((int)$n[9]!==($remaining<2?$remaining:11-$remaining))
    throw new AuthException(422,'کد ملی معتبر نیست.','INVALID_NATIONAL_CODE');
  }
  return $data;
 }
 private static function password(mixed $pw): string {if(!is_string($pw)||strlen($pw)<8||strlen($pw)>72||str_contains($pw,"\0"))throw new AuthException(422,'رمز باید ۸ تا ۷۲ بایت باشد.','VALIDATION_ERROR');return $pw;}
 public static function save(int $u,int $a,array $b,?int $id=null): array {
  self::scope($u,$a,'manage');return O::transaction(function($p)use($u,$a,$b,$id){$old=$id?self::editable($u,$a,$id):[];
   if($id)$p->prepare('SELECT id FROM users WHERE id=? FOR UPDATE')->execute([$id]);
   $name=$b['username']??$old['username']??'';$fn=$b['firstName']??$old['first_name']??'';$ln=$b['lastName']??$old['last_name']??'';$active=$b['isActive']??$old['is_active']??1;
   if(!is_string($name)||!preg_match('/^[\p{L}\p{N}_.@-]{3,100}$/u',$name)||!is_string($fn)||!is_string($ln)||strlen($fn)>200||strlen($ln)>240||!in_array($active,[0,1,'0','1'],true))throw new AuthException(422,'نام کاربری یا مشخصات نامعتبر است.','VALIDATION_ERROR');
   if($id&&isset($b['password']))throw new AuthException(422,'از فرم تغییر رمز استفاده کنید.','WORKFLOW_REQUIRED');
   $profile=self::profileFields($b);
   if($profile)self::requireProfileColumns($p);
   $role=$b['role']??($id?null:'operator');$rid=null;if(!A::isSuperAdmin($u)&&$role!==null&&$role!=='operator')throw new AuthException(403,'مدیر شرکت فقط می‌تواند کاربر با نقش اپراتور ایجاد یا تعیین کند.','ROLE_ASSIGNMENT_FORBIDDEN');if($role!==null){if(!is_string($role))throw new AuthException(422,'نقش نامعتبر است.','VALIDATION_ERROR');$q=$p->prepare('SELECT id FROM roles WHERE name=?');$q->execute([$role]);$rid=$q->fetchColumn();if(!$rid)throw new AuthException(422,'نقش نامعتبر است.','VALIDATION_ERROR');if($role==='super_admin'&&!A::isSuperAdmin($u))throw new AuthException(403,'تخصیص مدیر کل مجاز نیست.','FORBIDDEN');}
   if($id===$u&&((int)$active!==1||$role!==null))throw new AuthException(409,'نقش و وضعیت حساب خود را تغییر ندهید.','SELF_PROTECTION');
   $q=$p->prepare('SELECT id FROM users WHERE username=? AND id<>?');$q->execute([$name,$id??0]);if($q->fetchColumn())throw new AuthException(409,'نام کاربری تکراری است.','USERNAME_EXISTS');
   if($id)$p->prepare('UPDATE users SET username=?,first_name=?,last_name=?,is_active=? WHERE id=?')->execute([$name,$fn,$ln,(int)$active,$id]);
   else{if(!$a)throw new AuthException(422,'شرکت را انتخاب کنید.','VALIDATION_ERROR');$pw=self::password($b['password']??null);$p->prepare('INSERT INTO users(uuid,username,password_hash,first_name,last_name,is_active) VALUES(?,?,?,?,?,?)')->execute([Uuid::v4(),$name,password_hash($pw,PASSWORD_DEFAULT),$fn,$ln,(int)$active]);$id=(int)$p->lastInsertId();$p->prepare('INSERT INTO agency_users(agency_id,user_id) VALUES(?,?)')->execute([$a,$id]);}
   if($profile){$parts=[];$values=[];foreach($profile as $key=>$value){$parts[]=$key.'=?';$values[]=$value;}$values[]=$id;$p->prepare('UPDATE users SET '.implode(',',$parts).' WHERE id=?')->execute($values);}
   if($rid){$p->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$id]);$p->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$id,$rid]);}
   if((int)$active===0||$rid)$p->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL')->execute([$id]);self::audit($p,$u,$id,'user.saved');return self::find($u,$a,$id);
  });
 }
 /** Super-admin deletion is a one-way archive so invoices, operations and audits keep their FK owners. */
 public static function delete(int $u,int $target): array {
  self::scope($u,0,'manage');
  if($target<1)throw new AuthException(422,'شناسه کاربر نامعتبر است.','VALIDATION_ERROR');
  if($u===$target)throw new AuthException(409,'حذف حساب کاربری خودتان مجاز نیست.','SELF_PROTECTION');
  return O::transaction(function($p)use($u,$target){
   $column=$p->query("SHOW COLUMNS FROM users LIKE 'deleted_at'")->fetch();
   if(!$column)throw new AuthException(503,'ابتدا مهاجرت دیتابیس 0108_user_soft_delete.sql را اجرا کنید.','USER_SCHEMA_UPDATE_REQUIRED');
   $q=$p->prepare('SELECT id,username,deleted_at FROM users WHERE id=? FOR UPDATE');
   $q->execute([$target]);$row=$q->fetch(\PDO::FETCH_ASSOC);
   if(!$row||$row['deleted_at']!==null)throw new AuthException(404,'کاربر یافت نشد یا قبلاً حذف شده است.','NOT_FOUND');
   $roles=$p->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?");
   $roles->execute([$target]);$names=$roles->fetchAll(\PDO::FETCH_COLUMN);
   if(in_array('super_admin',$names,true)){
    $count=$p->query("SELECT COUNT(DISTINCT u.id) FROM users u
     JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id
     WHERE r.name='super_admin' AND u.is_active=1 AND u.deleted_at IS NULL")->fetchColumn();
    if((int)$count<=1)throw new AuthException(409,'حذف آخرین مدیر کل فعال سامانه امکان‌پذیر نیست.','LAST_SUPER_ADMIN');
   }
   $p->prepare('UPDATE users SET is_active=0,deleted_at=UTC_TIMESTAMP() WHERE id=?')->execute([$target]);
   $p->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL')->execute([$target]);
   // Keep roles and agency links as historical references, but the deleted user
   // cannot authenticate and no longer appears in the management table.
   self::audit($p,$u,$target,'user.deleted');
   return ['deleted'=>true,'id'=>$target];
  });
 }
 private static function audit($p,int $u,int $id,string $action): void {$p->prepare('INSERT INTO audit_logs(uuid,user_id,action,entity_type,entity_id) VALUES(?,?,?,?,?)')->execute([Uuid::v4(),$u,$action,'user',(string)$id]);}
 public static function updateOwnProfile(int $userId,array $data): array {
  $first=$data['firstName']??null;$last=$data['lastName']??null;
  if(!is_string($first)||!is_string($last)||mb_strlen($first)>100||mb_strlen($last)>120
    ||preg_match('/[\x00-\x1F\x7F]/u',$first.$last))
   throw new AuthException(422,'نام یا نام خانوادگی معتبر نیست.','VALIDATION_ERROR');
  $first=trim($first);$last=trim($last);
  if($first==='')throw new AuthException(422,'نام را وارد کنید.','VALIDATION_ERROR');
  $extra=self::profileFields($data);
  return O::transaction(function($pdo)use($userId,$first,$last,$extra){
   $fields=['first_name=?','last_name=?'];$params=[$first,$last];
   foreach($extra as $key=>$value){$fields[]=$key.'=?';$params[]=$value;}
   $params[]=$userId;
   $stmt=$pdo->prepare('UPDATE users SET '.implode(',',$fields).' WHERE id=? AND is_active=1');
   $stmt->execute($params);
   $profile=self::profile($userId);
   self::audit($pdo,$userId,$userId,'user.profile_updated');
   return ['id'=>$userId,'firstName'=>$first,'lastName'=>$last]+$profile;
  });
 }
 public static function changePassword(int $u,int $a,int $id,array $b): array {
  if($u!==$id){self::scope($u,$a,'manage');self::editable($u,$a,$id);}$pw=self::password($b['password']??null);if($pw!==($b['passwordConfirmation']??null))throw new AuthException(422,'تکرار رمز یکسان نیست.','VALIDATION_ERROR');
  return O::transaction(function($p)use($u,$id,$b,$pw){$q=$p->prepare('SELECT password_hash FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$hash=$q->fetchColumn();if(!$hash)throw new AuthException(404,'کاربر پیدا نشد.','NOT_FOUND');if($u===$id&&(!is_string($b['currentPassword']??null)||!password_verify($b['currentPassword'],$hash)))throw new AuthException(422,'رمز فعلی صحیح نیست.','CURRENT_PASSWORD_INVALID');$p->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw,PASSWORD_DEFAULT),$id]);$p->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL')->execute([$id]);self::audit($p,$u,$id,'user.password_changed');return ['changed'=>true,'requiresLogin'=>$u===$id];});
 }
}

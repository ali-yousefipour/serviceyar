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
 public static function list(int $u,int $a): array {self::scope($u,$a,'view');$q=Connection::get()->prepare('SELECT u.id,u.username,u.first_name,u.last_name,u.is_active,GROUP_CONCAT(DISTINCT r.title) roles,GROUP_CONCAT(DISTINCT r.name) role_names FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id WHERE '.($a?'EXISTS(SELECT 1 FROM agency_users au WHERE au.user_id=u.id AND au.agency_id=?)':'1=1').' GROUP BY u.id ORDER BY u.id DESC');$q->execute($a?[$a]:[]);return $q->fetchAll();}
 public static function find(int $u,int $a,int $id): array {self::scope($u,$a,'view');foreach(self::list($u,$a) as $r)if((int)$r['id']===$id)return $r;throw new AuthException(404,'کاربر پیدا نشد.','NOT_FOUND');}
 private static function editable(int $u,int $a,int $id): array {$r=self::find($u,$a,$id);$q=Connection::get()->prepare('SELECT COUNT(*) FROM agency_users WHERE user_id=?');$q->execute([$id]);if(!A::isSuperAdmin($u)&&(in_array('super_admin',explode(',',(string)$r['role_names']),true)||(int)$q->fetchColumn()>1))throw new AuthException(403,'ویرایش حساب مشترک یا مدیر کل فقط توسط مدیر کل مجاز است.','PROTECTED_USER');return $r;}
 private static function password(mixed $pw): string {if(!is_string($pw)||strlen($pw)<8||strlen($pw)>72||str_contains($pw,"\0"))throw new AuthException(422,'رمز باید ۸ تا ۷۲ بایت باشد.','VALIDATION_ERROR');return $pw;}
 public static function save(int $u,int $a,array $b,?int $id=null): array {
  self::scope($u,$a,'manage');return O::transaction(function($p)use($u,$a,$b,$id){$old=$id?self::editable($u,$a,$id):[];
   if($id)$p->prepare('SELECT id FROM users WHERE id=? FOR UPDATE')->execute([$id]);
   $name=$b['username']??$old['username']??'';$fn=$b['firstName']??$old['first_name']??'';$ln=$b['lastName']??$old['last_name']??'';$active=$b['isActive']??$old['is_active']??1;
   if(!is_string($name)||!preg_match('/^[\p{L}\p{N}_.@-]{3,100}$/u',$name)||!is_string($fn)||!is_string($ln)||strlen($fn)>200||strlen($ln)>240||!in_array($active,[0,1,'0','1'],true))throw new AuthException(422,'نام کاربری یا مشخصات نامعتبر است.','VALIDATION_ERROR');
   if($id&&isset($b['password']))throw new AuthException(422,'از فرم تغییر رمز استفاده کنید.','WORKFLOW_REQUIRED');
   $role=$b['role']??null;$rid=null;if($role!==null){if(!is_string($role))throw new AuthException(422,'نقش نامعتبر است.','VALIDATION_ERROR');$q=$p->prepare('SELECT id FROM roles WHERE name=?');$q->execute([$role]);$rid=$q->fetchColumn();if(!$rid)throw new AuthException(422,'نقش نامعتبر است.','VALIDATION_ERROR');if($role==='super_admin'&&!A::isSuperAdmin($u))throw new AuthException(403,'تخصیص مدیر کل مجاز نیست.','FORBIDDEN');}
   if($id===$u&&((int)$active!==1||$role!==null))throw new AuthException(409,'نقش و وضعیت حساب خود را تغییر ندهید.','SELF_PROTECTION');
   $q=$p->prepare('SELECT id FROM users WHERE username=? AND id<>?');$q->execute([$name,$id??0]);if($q->fetchColumn())throw new AuthException(409,'نام کاربری تکراری است.','USERNAME_EXISTS');
   if($id)$p->prepare('UPDATE users SET username=?,first_name=?,last_name=?,is_active=? WHERE id=?')->execute([$name,$fn,$ln,(int)$active,$id]);
   else{if(!$a)throw new AuthException(422,'شرکت را انتخاب کنید.','VALIDATION_ERROR');$pw=self::password($b['password']??null);$p->prepare('INSERT INTO users(uuid,username,password_hash,first_name,last_name,is_active) VALUES(?,?,?,?,?,?)')->execute([Uuid::v4(),$name,password_hash($pw,PASSWORD_DEFAULT),$fn,$ln,(int)$active]);$id=(int)$p->lastInsertId();$p->prepare('INSERT INTO agency_users(agency_id,user_id) VALUES(?,?)')->execute([$a,$id]);}
   if($rid){$p->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$id]);$p->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$id,$rid]);}
   if((int)$active===0||$rid)$p->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL')->execute([$id]);self::audit($p,$u,$id,'user.saved');return self::find($u,$a,$id);
  });
 }
 private static function audit($p,int $u,int $id,string $action): void {$p->prepare('INSERT INTO audit_logs(uuid,user_id,action,entity_type,entity_id) VALUES(?,?,?,?,?)')->execute([Uuid::v4(),$u,$action,'user',(string)$id]);}
 public static function changePassword(int $u,int $a,int $id,array $b): array {
  if($u!==$id){self::scope($u,$a,'manage');self::editable($u,$a,$id);}$pw=self::password($b['password']??null);if($pw!==($b['passwordConfirmation']??null))throw new AuthException(422,'تکرار رمز یکسان نیست.','VALIDATION_ERROR');
  return O::transaction(function($p)use($u,$id,$b,$pw){$q=$p->prepare('SELECT password_hash FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$hash=$q->fetchColumn();if(!$hash)throw new AuthException(404,'کاربر پیدا نشد.','NOT_FOUND');if($u===$id&&(!is_string($b['currentPassword']??null)||!password_verify($b['currentPassword'],$hash)))throw new AuthException(422,'رمز فعلی صحیح نیست.','CURRENT_PASSWORD_INVALID');$p->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw,PASSWORD_DEFAULT),$id]);$p->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL')->execute([$id]);self::audit($p,$u,$id,'user.password_changed');return ['changed'=>true,'requiresLogin'=>$u===$id];});
 }
}

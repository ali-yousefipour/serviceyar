<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use ServiceYar\Support\Uuid;

final class StandaloneRepresentatives {
 public static function ensureSchema(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS representative_accounts (
   agency_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,
   PRIMARY KEY(agency_id,user_id), FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
   FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS representative_account_schools (
   agency_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,school_id BIGINT UNSIGNED NOT NULL,
   PRIMARY KEY(agency_id,user_id,school_id),
   FOREIGN KEY(agency_id,user_id) REFERENCES representative_accounts(agency_id,user_id) ON DELETE CASCADE,
   FOREIGN KEY(school_id) REFERENCES schools(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("INSERT IGNORE INTO roles(name,title) VALUES ('representative_portal','نماینده مدارس')");
 }
 private static function db(): PDO {$pdo=Connection::get();self::ensureSchema($pdo);return $pdo;}
 private static function staff(int $uid,int $agency): PDO {
  Authorization::requireAgencyAccess($uid,$agency);
  if(!Authorization::isSuperAdmin($uid))Authorization::requirePermission($uid,'users.manage');
  return self::db();
 }
 public static function list(PDO $pdo,int $agency): array {
  $q=$pdo->prepare("SELECT ra.user_id userId,ra.is_active isActive,u.first_name firstName,
   u.last_name lastName,u.mobile FROM representative_accounts ra
   JOIN users u ON u.id=ra.user_id WHERE ra.agency_id=? AND u.is_active=1 ORDER BY u.last_name,u.first_name");
  $q->execute([$agency]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$pdo->prepare("SELECT s.id,s.name FROM representative_account_schools a
    JOIN schools s ON s.id=a.school_id AND s.agency_id=a.agency_id AND s.is_active=1
    WHERE a.agency_id=? AND a.user_id=? ORDER BY s.name");
  foreach($items as &$row){$q->execute([$agency,(int)$row['userId']]);$row['schools']=$q->fetchAll(PDO::FETCH_ASSOC);}
  unset($row);return $items;
 }
 public static function memberships(int $uid): array {
  $pdo=self::db();$q=$pdo->prepare("SELECT 'representative' portalType,ra.user_id partyId,ra.agency_id agencyId,
   a.name agencyName FROM representative_accounts ra
   JOIN agencies a ON a.id=ra.agency_id AND a.is_active=1
   JOIN users u ON u.id=ra.user_id AND u.is_active=1
   WHERE ra.user_id=? AND ra.is_active=1 ORDER BY a.name");
  $q->execute([$uid]);return $q->fetchAll(PDO::FETCH_ASSOC);
 }
 public static function schools(int $uid,int $agency,int $userId): array {
  if($uid!==$userId)throw new AuthException(403,'دسترسی به حساب دیگران مجاز نیست.','PORTAL_FORBIDDEN');
  $pdo=self::db();
  $q=$pdo->prepare("SELECT s.id,s.name FROM representative_account_schools ras
    JOIN representative_accounts ra ON ra.agency_id=ras.agency_id AND ra.user_id=ras.user_id AND ra.is_active=1
    JOIN users u ON u.id=ra.user_id AND u.is_active=1
    JOIN agencies a ON a.id=ra.agency_id AND a.is_active=1
    JOIN schools s ON s.id=ras.school_id AND s.agency_id=ras.agency_id AND s.is_active=1
    WHERE ras.agency_id=? AND ras.user_id=? ORDER BY s.name");
  $q->execute([$agency,$uid]);return $q->fetchAll(PDO::FETCH_ASSOC);
 }
 public static function dashboard(int $uid,int $agency,int $userId): array {
  $schools=self::schools($uid,$agency,$userId);
  if(!self::isMember($uid,$agency))throw new AuthException(403,'نمایندگی فعال نیست.','PORTAL_FORBIDDEN');
  $pdo=self::db();
  $q=$pdo->prepare('SELECT id,name FROM agencies WHERE id=?');$q->execute([$agency]);$agencyRow=$q->fetch(PDO::FETCH_ASSOC);
  $q=$pdo->prepare('SELECT username,first_name,last_name,mobile FROM users WHERE id=?');$q->execute([$uid]);$user=$q->fetch(PDO::FETCH_ASSOC);
  return ['portalType'=>'representative','agency'=>$agencyRow,'profile'=>$user,
   'representative'=>$user,'schools'=>$schools,'readonly'=>true];
 }
 private static function isMember(int $uid,int $agency): bool {
  $q=self::db()->prepare("SELECT 1 FROM representative_accounts ra JOIN agencies a ON a.id=ra.agency_id AND a.is_active=1
   JOIN users u ON u.id=ra.user_id AND u.is_active=1
   WHERE ra.agency_id=? AND ra.user_id=? AND ra.is_active=1 LIMIT 1");
  $q->execute([$agency,$uid]);return (bool)$q->fetchColumn();
 }
 public static function report(int $uid,int $agency,int $userId,array $filter): array {
  if(!self::isMember($uid,$agency))throw new AuthException(403,'نمایندگی فعال نیست.','PORTAL_FORBIDDEN');
  $schools=self::schools($uid,$agency,$userId);
  $school=filter_var($filter['schoolId']??null,FILTER_VALIDATE_INT);
  if(!$school||!in_array((int)$school,array_map(static fn($r)=>(int)$r['id'],$schools),true))
   throw new AuthException(403,'مجوز مشاهده اطلاعات این مدرسه وجود ندارد.','SCHOOL_SCOPE_FORBIDDEN');
  return StudentDebtors::reportForRepresentative($uid,$agency,$filter);
 }
 private static function validateSchools(PDO $pdo,int $agency,mixed $raw,bool $required=true): array {
  if(!is_array($raw)||count($raw)>500)throw new AuthException(422,'فهرست مدارس نامعتبر است.','INVALID_SCHOOLS');
  $ids=[];
  foreach($raw as $value){
   if(!is_int($value)&&!(is_string($value)&&ctype_digit($value)))throw new AuthException(422,'شناسه مدرسه نامعتبر است.','INVALID_SCHOOLS');
   if((int)$value<1)throw new AuthException(422,'شناسه مدرسه نامعتبر است.','INVALID_SCHOOLS');
   $ids[(int)$value]=(int)$value;
  }
  $ids=array_values($ids);
  if($required&&!$ids)throw new AuthException(422,'حداقل یک مدرسه انتخاب کنید.','SCHOOLS_REQUIRED');
  if($ids){
   $marks=implode(',',array_fill(0,count($ids),'?'));
   $q=$pdo->prepare("SELECT COUNT(*) FROM schools WHERE agency_id=? AND is_active=1 AND id IN ($marks)");
   $q->execute([$agency,...$ids]);
   if((int)$q->fetchColumn()!==count($ids))throw new AuthException(422,'مدارس خارج از شرکت انتخاب شده‌اند.','SCHOOL_SCOPE_FORBIDDEN');
  }
  return $ids;
 }
 public static function create(int $uid,int $agency,array $data): array {
  $pdo=self::staff($uid,$agency);
  $first=trim((string)($data['firstName']??''));$last=trim((string)($data['lastName']??''));
  $mobile=strtr(trim((string)($data['mobile']??'')),
   ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
  $password=$data['password']??null;
  if($first===''||$last===''||mb_strlen($first)>100||mb_strlen($last)>120||
   !preg_match('/^09[0-9]{9}$/D',$mobile)||!is_string($password)||strlen($password)<10||strlen($password)>72)
   throw new AuthException(422,'مشخصات یا رمز عبور (حداقل ۱۰ کاراکتر) معتبر نیست.','INVALID_REPRESENTATIVE');
  $schools=self::validateSchools($pdo,$agency,$data['schoolIds']??null);
  $pdo->beginTransaction();
  try{
   $q=$pdo->prepare('SELECT id FROM users WHERE username=? OR mobile=? FOR UPDATE');$q->execute([$mobile,$mobile]);
   if($q->fetchColumn())throw new AuthException(409,'حسابی با این شماره همراه وجود دارد.','USER_EXISTS');
   $pdo->prepare('INSERT INTO users(uuid,username,password_hash,first_name,last_name,mobile,is_active) VALUES(?,?,?,?,?,?,1)')
    ->execute([Uuid::v4(),$mobile,password_hash($password,PASSWORD_DEFAULT),$first,$last,$mobile]);
   $newId=(int)$pdo->lastInsertId();
   $pdo->prepare('INSERT INTO agency_users(agency_id,user_id) VALUES(?,?)')->execute([$agency,$newId]);
   $pdo->prepare("INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE name='representative_portal'")->execute([$newId]);
   $pdo->prepare('INSERT INTO representative_accounts(agency_id,user_id,is_active) VALUES(?,?,1)')->execute([$agency,$newId]);
   $q=$pdo->prepare('INSERT INTO representative_account_schools(agency_id,user_id,school_id) VALUES(?,?,?)');
   foreach($schools as $school)$q->execute([$agency,$newId,$school]);
   $pdo->commit();return ['userId'=>$newId,'created'=>true];
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 public static function save(int $uid,int $agency,array $data): array {
  $pdo=self::staff($uid,$agency);
  $id=filter_var($data['userId']??null,FILTER_VALIDATE_INT);
  if(!$id)throw new AuthException(422,'نماینده معتبر انتخاب کنید.','INVALID_REPRESENTATIVE');
  $enabled=($data['enabled']??true)===true;
  $schools=self::validateSchools($pdo,$agency,$data['schoolIds']??null,$enabled);
  $pdo->beginTransaction();
  try{
   $q=$pdo->prepare('SELECT 1 FROM representative_accounts WHERE agency_id=? AND user_id=? FOR UPDATE');
   $q->execute([$agency,$id]);
   if(!$q->fetchColumn())throw new AuthException(404,'نماینده پیدا نشد.','NOT_FOUND');
   $pdo->prepare('UPDATE representative_accounts SET is_active=? WHERE agency_id=? AND user_id=?')
     ->execute([$enabled?1:0,$agency,$id]);
   $pdo->prepare('DELETE FROM representative_account_schools WHERE agency_id=? AND user_id=?')->execute([$agency,$id]);
   $q=$pdo->prepare('INSERT INTO representative_account_schools(agency_id,user_id,school_id) VALUES(?,?,?)');
   foreach($schools as $school)$q->execute([$agency,$id,$school]);
   $pdo->commit();return ['updated'=>true];
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}

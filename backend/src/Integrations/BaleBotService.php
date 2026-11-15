<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use PDO;

/** Independent Bale messaging/auth bot; wallet payments remain separately configured. */
final class BaleBotService {
 private static bool $schemaChecked=false;
 private static function ensureSchema(): void {
  if(self::$schemaChecked)return;
  $pdo=Connection::get();
  try {
   $pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (setting_key VARCHAR(150) NOT NULL PRIMARY KEY,setting_value TEXT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
   $pdo->exec("CREATE TABLE IF NOT EXISTS bale_user_links (user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,chat_id VARCHAR(80) NOT NULL UNIQUE,linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
   $pdo->exec("CREATE TABLE IF NOT EXISTS bale_auth_challenges (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,purpose ENUM('link','login') NOT NULL,code_hash CHAR(64) NOT NULL UNIQUE,expires_at DATETIME NOT NULL,consumed_at DATETIME NULL,attempt_count INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_bale_auth_user (user_id,purpose,expires_at),FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
   $pdo->exec("CREATE TABLE IF NOT EXISTS bale_webhook_updates (update_id VARCHAR(80) NOT NULL PRIMARY KEY,received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
   $pdo->exec("CREATE TABLE IF NOT EXISTS bale_otp_requests (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,ip_address VARCHAR(45) NOT NULL,requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_bale_otp_ip (ip_address,requested_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
   self::$schemaChecked=true;
  }catch(\PDOException $e) {
   throw new AuthException(503,'جدول‌های ربات بله آماده نیستند؛ مهاجرت 0110_bale_bot_auth.sql را اجرا کنید.','BALE_SCHEMA_REQUIRED');
  }
 }
 private static function setting(string $key): string {
  try{
   $q=Connection::get()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');
   $q->execute([$key]);return (string)($q->fetchColumn()?:'');
  }catch(\PDOException $e){
   if($e->getCode()==='42S02'||(int)($e->errorInfo[1]??0)===1146)return '';
   throw $e;
  }
 }
 private static function put(string $key,string $value): void {
  Connection::get()->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
 }
 private static function cipherKey(): string {
  $key=(string)config('BALE_SETTINGS_KEY',config('DB_PASSWORD',''));
  if(strlen($key)<12)throw new AuthException(503,'برای نگهداری امن توکن بله، BALE_SETTINGS_KEY با حداقل ۱۲ کاراکتر در تنظیمات سرور تعریف شود.','BALE_ENCRYPTION_KEY_REQUIRED');
  return hash('sha256','serviceyar-bale-v1|'.$key,true);
 }
 private static function encrypt(string $plaintext): string {
  $iv=random_bytes(12);$tag='';
  $cipher=openssl_encrypt($plaintext,'aes-256-gcm',self::cipherKey(),OPENSSL_RAW_DATA,$iv,$tag);
  if($cipher===false)throw new AuthException(503,'رمزگذاری توکن بله ممکن نیست.','BALE_ENCRYPTION_ERROR');
  return base64_encode($iv.$tag.$cipher);
 }
 private static function decrypt(string $encoded): string {
  $raw=base64_decode($encoded,true);
  if($raw===false||strlen($raw)<29)throw new AuthException(503,'تنظیمات محرمانه بله قابل خواندن نیست.','BALE_ENCRYPTION_ERROR');
  $token=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::cipherKey(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
  if($token===false)throw new AuthException(503,'کلید رمزگذاری توکن بله تغییر کرده است.','BALE_ENCRYPTION_ERROR');
  return $token;
 }
 private static function token(): string {
  $value=self::setting('bale_auth_token');
  return $value!==''?self::decrypt($value):'';
 }
 public static function ready(): bool {
  try{return self::setting('bale_auth_enabled')==='1'&&self::token()!=='';}catch(\Throwable){return false;}
 }
 private static function call(string $method,array $body=[],?string $override=null): array {
  $token=$override??self::token();
  if(!preg_match('/^[A-Za-z0-9:_-]{25,200}$/D',$token))throw new AuthException(503,'توکن ربات بله معتبر نیست.','BALE_TOKEN_INVALID');
  if(!in_array($method,['getMe','setWebhook','getWebhookInfo','sendMessage'],true))throw new \InvalidArgumentException('Unsupported Bale API method');
  $data=JsonHttp::post('https://tapi.bale.ai/bot'.$token.'/'.$method,$body,[],12);
  if(($data['ok']??false)!==true)throw new AuthException(502,'بله درخواست را تأیید نکرد.','BALE_API_ERROR');
  return $data;
 }
 public static function overview(int $uid): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط ادمین کل به تنظیمات ربات دسترسی دارد.','FORBIDDEN');
  $configured=self::ready();
  return ['configured'=>$configured,'tokenStored'=>self::setting('bale_auth_token')!=='','botUsername'=>self::setting('bale_auth_username'),'webhookUrl'=>$configured?self::webhookUrl():'','webhookRegistered'=>self::setting('bale_auth_webhook_registered')==='1'];
 }
 private static function webhookUrl(): string {
  $base=rtrim((string)config('APP_URL',''),'/');
  if(!preg_match('#^https://[^/]+$#i',$base))throw new AuthException(503,'APP_URL باید نشانی HTTPS اصلی سامانه باشد.','BALE_APP_URL_REQUIRED');
  return $base.'/api/v1/bale/bot/webhook/'.self::setting('bale_auth_webhook_secret');
 }
 public static function configure(int $uid,array $data): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط ادمین کل مجاز است.','FORBIDDEN');
  if(array_diff(array_keys($data),['token','enabled']))throw new AuthException(422,'ورودی تنظیمات بله معتبر نیست.','VALIDATION_ERROR');
  self::ensureSchema();
  $token=trim((string)($data['token']??''));
  if($token!==''&&!preg_match('/^[A-Za-z0-9:_-]{25,200}$/D',$token))throw new AuthException(422,'قالب توکن بله نامعتبر است.','VALIDATION_ERROR');
  if($token!==''&&hash_equals((string)config('BALE_BOT_TOKEN',''),$token))throw new AuthException(422,'توکن ربات پیام‌رسانی باید با ربات پرداخت کیف پول بله متفاوت باشد تا وب‌هوک پرداخت مختل نشود.','BALE_PAYMENT_TOKEN_CONFLICT');
  if($token!==''){
   $me=self::call('getMe',[],$token);
   $username=(string)($me['result']['username']??'');
   self::put('bale_auth_token',self::encrypt($token));
   self::put('bale_auth_username',$username);
  }
  if(array_key_exists('enabled',$data))self::put('bale_auth_enabled',$data['enabled']===true||$data['enabled']==='1'?'1':'0');
  if(self::ready()){
   if(self::setting('bale_auth_webhook_secret')==='')self::put('bale_auth_webhook_secret',bin2hex(random_bytes(32)));
   self::put('bale_auth_webhook_registered','0');
   self::call('setWebhook',['url'=>self::webhookUrl()]);
   self::put('bale_auth_webhook_registered','1');
  }
  return self::overview($uid);
 }
 public static function health(int $uid): array {
  if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط ادمین کل مجاز است.','FORBIDDEN');
  if(!self::ready())return ['connected'=>false,'webhookCorrect'=>false,'message'=>'ربات فعال نیست.'];
  $me=self::call('getMe');
  $hook=self::call('getWebhookInfo');
  $info=is_array($hook['result']??null)?$hook['result']:[];
  return ['connected'=>true,'botUsername'=>(string)($me['result']['username']??''),
   'webhookCorrect'=>hash_equals(self::webhookUrl(),(string)($info['url']??'')),
   'pendingUpdates'=>(int)($info['pending_update_count']??0),
   'lastError'=>(string)($info['last_error_message']??'')];
 }
 public static function status(int $user): array {
  if(!self::ready())return ['enabled'=>false,'linked'=>false,'botUsername'=>self::setting('bale_auth_username')];
  $q=Connection::get()->prepare('SELECT chat_id FROM bale_user_links WHERE user_id=?');
  $q->execute([$user]);return ['enabled'=>self::ready(),'linked'=>(bool)$q->fetchColumn(),'botUsername'=>self::setting('bale_auth_username')];
 }
 public static function startLink(int $user): array {
  if(!self::ready())throw new AuthException(503,'ربات بله هنوز فعال نشده است.','BALE_DISABLED');
  $pdo=Connection::get();
  $pdo->prepare("DELETE FROM bale_auth_challenges WHERE user_id=? AND purpose='link'")->execute([$user]);
  $code=bin2hex(random_bytes(16));$hash=hash('sha256',$code);
  $pdo->prepare("INSERT INTO bale_auth_challenges(user_id,purpose,code_hash,expires_at) VALUES(?,'link',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))")->execute([$user,$hash]);
  $name=self::setting('bale_auth_username');
  return ['code'=>$code,'botUsername'=>$name,'expiresIn'=>600,'instruction'=>'دستور /start را همراه کد به ربات بله ارسال کنید.'];
 }
 public static function unlink(int $user): array {
  Connection::get()->prepare('DELETE FROM bale_user_links WHERE user_id=?')->execute([$user]);
  Connection::get()->prepare('DELETE FROM bale_auth_challenges WHERE user_id=?')->execute([$user]);
  return ['linked'=>false];
 }
 public static function handleWebhook(string $secret,array $update): array {
  $saved=self::setting('bale_auth_webhook_secret');
  if(!self::ready()||strlen($saved)<32||!hash_equals($saved,$secret))throw new AuthException(403,'وب‌هوک غیرمجاز است.','FORBIDDEN');
  $chat=$update['message']['chat']??null;
  $text=trim((string)($update['message']['text']??''));
  if(!is_array($chat)||($chat['type']??'')!=='private'||!preg_match('/^[0-9]{1,30}$/D',(string)($chat['id']??'')))return ['accepted'=>true];
  $id=(string)$chat['id'];
  $updateId=(string)($update['update_id']??'');
  if($updateId===''||!preg_match('/^[0-9]{1,30}$/D',$updateId))return ['accepted'=>true];
  try{Connection::get()->prepare('INSERT INTO bale_webhook_updates(update_id) VALUES(?)')->execute([$updateId]);}
  catch(\PDOException $e){if($e->getCode()==='23000')return ['accepted'=>true,'duplicate'=>true];throw $e;}
  if(preg_match('/^\/start\s+([a-f0-9]{32})$/i',$text,$match)){
   $pdo=Connection::get();$hash=hash('sha256',strtolower($match[1]));
   $pdo->beginTransaction();
   try{
    $q=$pdo->prepare("SELECT c.id,c.user_id FROM bale_auth_challenges c JOIN users u ON u.id=c.user_id AND u.is_active=1 WHERE c.code_hash=? AND c.purpose='link' AND c.consumed_at IS NULL AND c.expires_at>UTC_TIMESTAMP() FOR UPDATE");
    $q->execute([$hash]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row){$pdo->rollBack();self::send($id,'کد اتصال نامعتبر یا منقضی شده است. کد جدید را از پروفایل سامانه دریافت کنید.');return ['accepted'=>true];}
    $pdo->prepare('DELETE FROM bale_user_links WHERE chat_id=? OR user_id=?')->execute([$id,(int)$row['user_id']]);
    $pdo->prepare('INSERT INTO bale_user_links(user_id,chat_id) VALUES(?,?)')->execute([(int)$row['user_id'],$id]);
    $pdo->prepare('UPDATE bale_auth_challenges SET consumed_at=UTC_TIMESTAMP() WHERE id=?')->execute([(int)$row['id']]);
    $pdo->commit();
    self::send($id,'حساب بله شما به سرویس‌یار متصل شد. اکنون می‌توانید کد ورود را در همین گفتگو دریافت کنید.');
   }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }elseif($text==='/start'||$text==='/help'){
   self::send($id,'به ربات سرویس‌یار خوش آمدید. ابتدا وارد حساب کاربری خود در سایت شوید و از قسمت پروفایل، کد اتصال بله را دریافت کنید. سپس دستور /start و کد اتصال را ارسال نمایید.');
  }else{
   self::send($id,'برای اتصال حساب، کد یک‌بارمصرف را از پروفایل سرویس‌یار دریافت کنید و با دستور /start ارسال کنید.');
  }
  return ['accepted'=>true];
 }
 private static function send(string $chat,string $message): void {
  self::call('sendMessage',['chat_id'=>$chat,'text'=>$message]);
 }
 public static function sendToUser(int $actor,int $recipient,string $text): array {
  if(!self::ready())throw new AuthException(503,'ربات فعال نیست.','BALE_DISABLED');
  if(!Authorization::isSuperAdmin($actor)){
   Authorization::requirePermission($actor,'users.manage');
   $q=Connection::get()->prepare('SELECT 1 FROM agency_users a JOIN agency_users b ON b.agency_id=a.agency_id WHERE a.user_id=? AND b.user_id=? LIMIT 1');
   $q->execute([$actor,$recipient]);if(!$q->fetchColumn())throw new AuthException(403,'گیرنده متعلق به شرکت شما نیست.','FORBIDDEN');
  }
  $text=trim($text);
  if($text===''||mb_strlen($text)>3000)throw new AuthException(422,'پیام باید بین ۱ تا ۳۰۰۰ کاراکتر باشد.','VALIDATION_ERROR');
  $q=Connection::get()->prepare('SELECT l.chat_id FROM bale_user_links l JOIN users u ON u.id=l.user_id WHERE u.id=? AND u.is_active=1');
  $q->execute([$recipient]);$chat=$q->fetchColumn();
  if(!$chat)throw new AuthException(409,'حساب بله این کاربر متصل نیست.','BALE_NOT_LINKED');
  self::send((string)$chat,$text);
  return ['sent'=>true,'recipientId'=>$recipient];
 }
 public static function requestLogin(string $username): array {
  if(!self::ready())throw new AuthException(503,'ورود با بله فعال نیست.','BALE_DISABLED');
  $username=trim($username);
  if($username===''||strlen($username)>100)throw new AuthException(422,'نام کاربری معتبر نیست.','VALIDATION_ERROR');
  $pdo=Connection::get();
  $ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
  $limit=$pdo->prepare('SELECT COUNT(*) FROM bale_otp_requests WHERE ip_address=? AND requested_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)');
  $limit->execute([$ip]);if((int)$limit->fetchColumn()>=10)throw new AuthException(429,'تعداد درخواست‌های کد ورود بیش از حد مجاز است.','BALE_RATE_LIMITED');
  $pdo->prepare('INSERT INTO bale_otp_requests(ip_address) VALUES(?)')->execute([$ip]);
  if(random_int(1,100)===1)$pdo->exec('DELETE FROM bale_otp_requests WHERE requested_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
  $q=$pdo->prepare('SELECT u.id,l.chat_id FROM users u JOIN bale_user_links l ON l.user_id=u.id WHERE u.username=? AND u.is_active=1 LIMIT 1');
  $q->execute([$username]);$row=$q->fetch(PDO::FETCH_ASSOC);
  // Do not disclose whether a username is linked.
  if(!$row)return ['requested'=>true,'expiresIn'=>300];
  $uid=(int)$row['id'];
  $rate=$pdo->prepare("SELECT COUNT(*) FROM bale_auth_challenges WHERE user_id=? AND purpose='login' AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)");
  $rate->execute([$uid]);if((int)$rate->fetchColumn()>=5)throw new AuthException(429,'تعداد درخواست‌های ورود بیش از حد مجاز است.','BALE_RATE_LIMITED');
  do{
   $code=(string)random_int(100000,999999);
   $hash=hash_hmac('sha256',$uid.':'.$code,self::cipherKey());
   $exists=$pdo->prepare('SELECT 1 FROM bale_auth_challenges WHERE code_hash=? LIMIT 1');
   $exists->execute([$hash]);
  }while($exists->fetchColumn());
  $pdo->prepare("UPDATE bale_auth_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose='login' AND consumed_at IS NULL")->execute([$uid]);
  $pdo->prepare("INSERT INTO bale_auth_challenges(user_id,purpose,code_hash,expires_at) VALUES(?,'login',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))")->execute([$uid,$hash]);
  self::send((string)$row['chat_id'],'کد ورود یک‌بارمصرف سرویس‌یار: '.$code."\nاعتبار: ۵ دقیقه. این کد را به هیچ‌کس ندهید.");
  return ['requested'=>true,'expiresIn'=>300];
 }
 public static function verifyLogin(string $username,string $code): int {
  if(!self::ready())throw new AuthException(503,'ورود با بله فعال نیست.','BALE_DISABLED');
  if(!preg_match('/^[0-9]{6}$/D',$code))throw new AuthException(401,'کد ورود معتبر نیست.','BALE_OTP_INVALID');
  $pdo=Connection::get();$pdo->beginTransaction();
  try {
   $u=$pdo->prepare('SELECT u.id FROM users u JOIN bale_user_links l ON l.user_id=u.id WHERE u.username=? AND u.is_active=1 LIMIT 1');
   $u->execute([trim($username)]);$uid=(int)$u->fetchColumn();
   if(!$uid)throw new AuthException(401,'کد ورود معتبر نیست.','BALE_OTP_INVALID');
   $q=$pdo->prepare("SELECT id,code_hash,attempt_count FROM bale_auth_challenges WHERE user_id=? AND purpose='login' AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE");
   $q->execute([$uid]);$challenge=$q->fetch(PDO::FETCH_ASSOC);
   if(!$challenge||$challenge['attempt_count']>=5)throw new AuthException(401,'کد ورود نامعتبر یا منقضی شده است.','BALE_OTP_INVALID');
   $pdo->prepare('UPDATE bale_auth_challenges SET attempt_count=attempt_count+1 WHERE id=?')->execute([$challenge['id']]);
   if(!hash_equals($challenge['code_hash'],hash_hmac('sha256',$uid.':'.$code,self::cipherKey()))) {$pdo->commit();throw new AuthException(401,'کد ورود معتبر نیست.','BALE_OTP_INVALID');}
   $pdo->prepare('UPDATE bale_auth_challenges SET consumed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$challenge['id']]);
   $pdo->commit();return $uid;
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Auth;

use ServiceYar\Database\Connection;
use ServiceYar\Integrations\SmsProvider;
use PDO;

final class SmsLogin
{
    /** Privileged one-time setup on enabling SMS login; public OTP never runs DDL. */
    public static function prepareSchema(): void
    {
        $pdo=Connection::get();
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sms_login_challenges (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,purpose ENUM('login','password_reset') NOT NULL DEFAULT 'login',code_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,consumed_at DATETIME NULL,
                attempt_count INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sms_login_user (user_id,created_at,expires_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS sms_login_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sms_login_ip (ip_address,requested_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch(\PDOException $e) {
            error_log('[ServiceYar][SMS] Unable to initialize OTP schema: '.$e->getCode());
            throw new AuthException(503,'جدول ورود پیامکی قابل ایجاد نیست؛ مهاجرت 0111_sms_login.sql را اجرا کنید.','SMS_LOGIN_MIGRATION_REQUIRED');
        }
    }
    private static function schema(): bool
    {
        $pdo=Connection::get();
        try {
            $pdo->query('SELECT id FROM sms_login_challenges LIMIT 0');
            $pdo->query('SELECT ip_address FROM sms_login_requests LIMIT 0');
        } catch(\PDOException $e) {
            throw new AuthException(503,'جدول‌های ورود پیامکی آماده نیستند؛ مهاجرت 0111_sms_login.sql را اجرا کنید.','SMS_LOGIN_MIGRATION_REQUIRED');
        }
        try {
            $pdo->query('SELECT purpose FROM sms_login_challenges LIMIT 0');
            return true;
        } catch(\PDOException $e) {
            if($e->getCode()==='42S22'||(int)($e->errorInfo[1]??0)===1054)return false;
            throw new AuthException(503,'ساختار ورود پیامکی قابل بررسی نیست.','SMS_LOGIN_SCHEMA_ERROR');
        }
    }

    public static function recoveryReady(): bool
    {
        try {
            $q=Connection::get()->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sms_login_challenges' AND COLUMN_NAME='purpose'");
            $q->execute();$type=(string)$q->fetchColumn();
            return $type!==''&&str_contains($type,'password_reset');
        } catch(\Throwable){return false;}
    }
    private static function digits(string $text): string
    {
        return strtr(trim($text),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    }
    private static function normalizeMobile(string $mobile): string
    {
        $mobile=preg_replace('/[\s\-()]/u','',self::digits($mobile));
        if(str_starts_with($mobile,'+98'))$mobile='0'.substr($mobile,3);
        elseif(str_starts_with($mobile,'0098'))$mobile='0'.substr($mobile,4);
        elseif(str_starts_with($mobile,'98')&&strlen($mobile)===12)$mobile='0'.substr($mobile,2);
        elseif(preg_match('/^9[0-9]{9}$/D',$mobile))$mobile='0'.$mobile;
        return preg_match('/^09[0-9]{9}$/D',$mobile)?$mobile:'';
    }
    private static function user(string $username): ?array
    {
        $pdo=Connection::get();
        $q=$pdo->prepare('SELECT id, mobile FROM users WHERE username=? AND is_active=1 LIMIT 1');
        $q->execute([self::digits($username)]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)return null;
        $mobile=self::normalizeMobile((string)($row['mobile']??''));
        return $mobile!==''?['id'=>(int)$row['id'],'mobile'=>$mobile]:null;
    }
    public static function request(string $username): array
    {
        LoginMethods::requireEnabled('sms');
        if(!SmsProvider::ready())throw new AuthException(503,'سرویس پیامک ورود تنظیم نشده است.','SMS_NOT_CONFIGURED');
        $username=self::digits($username);
        if($username===''||strlen($username)>100)throw new AuthException(422,'نام کاربری معتبر نیست.','VALIDATION_ERROR');
        $hasPurpose=self::schema();
        $pdo=Connection::get();
        $ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT COUNT(*) FROM sms_login_requests WHERE ip_address=? AND requested_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)');
            $q->execute([$ip]);
            if((int)$q->fetchColumn()>=10)throw new AuthException(429,'تعداد درخواست‌های ورود زیاد است. ۱۵ دقیقه بعد تلاش کنید.','SMS_RATE_LIMITED');
            $pdo->prepare('INSERT INTO sms_login_requests(ip_address) VALUES(?)')->execute([$ip]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        $user=self::user($username);
        // Identical public response regardless of whether the account/mobile exists.
        if(!$user)return ['requested'=>true,'expiresIn'=>300];
        $uid=$user['id'];
        $q=$pdo->prepare($hasPurpose?"SELECT COUNT(*) FROM sms_login_challenges WHERE user_id=? AND purpose='login' AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)":"SELECT COUNT(*) FROM sms_login_challenges WHERE user_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)");
        $q->execute([$uid]);
        if((int)$q->fetchColumn()>=5)throw new AuthException(429,'تعداد درخواست‌های کد ورود زیاد است.','SMS_RATE_LIMITED');
        $code=(string)random_int(100000,999999);
        $hash=password_hash($code,PASSWORD_DEFAULT);
        $pdo->prepare($hasPurpose?"UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose='login' AND consumed_at IS NULL":"UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND consumed_at IS NULL")->execute([$uid]);
        $pdo->prepare($hasPurpose?"INSERT INTO sms_login_challenges(user_id,purpose,code_hash,expires_at) VALUES(?,'login',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))":"INSERT INTO sms_login_challenges(user_id,code_hash,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))")->execute([$uid,$hash]);
        try{
            $result=(new SmsProvider())->send($user['mobile'],'کد ورود سرویس‌یار: '.$code."\nاعتبار: ۵ دقیقه. کد را در اختیار دیگران قرار ندهید.");
            if(!($result['accepted']??false))throw new AuthException(502,'پیامک ورود توسط سرویس‌دهنده پذیرفته نشد.','SMS_PROVIDER_REJECTED');
        }catch(\Throwable $e){
            $pdo->prepare($hasPurpose?"UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose='login' AND code_hash=?":"UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND code_hash=?")->execute([$uid,$hash]);
            throw $e;
        }
        return ['requested'=>true,'expiresIn'=>300];
    }
    public static function verify(string $username,string $code): int
    {
        LoginMethods::requireEnabled('sms');
        $code=self::digits($code);
        if(!preg_match('/^[0-9]{6}$/D',$code))throw new AuthException(401,'کد ورود نامعتبر است.','SMS_OTP_INVALID');
        $hasPurpose=self::schema();
        $user=self::user($username);
        if(!$user)throw new AuthException(401,'کد ورود نامعتبر است.','SMS_OTP_INVALID');
        $pdo=Connection::get();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare($hasPurpose?"SELECT id,code_hash,attempt_count FROM sms_login_challenges WHERE user_id=? AND purpose='login' AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE":"SELECT id,code_hash,attempt_count FROM sms_login_challenges WHERE user_id=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $q->execute([$user['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||(int)$row['attempt_count']>=5)throw new AuthException(401,'کد ورود نامعتبر یا منقضی است.','SMS_OTP_INVALID');
            $pdo->prepare('UPDATE sms_login_challenges SET attempt_count=attempt_count+1 WHERE id=?')->execute([$row['id']]);
            if(!password_verify($code,$row['code_hash'])){$pdo->commit();throw new AuthException(401,'کد ورود صحیح نیست.','SMS_OTP_INVALID');}
            $pdo->prepare('UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$row['id']]);
            $pdo->commit();return $user['id'];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function userByMobile(string $mobile): ?array
    {
        $mobile=self::normalizeMobile($mobile);
        if($mobile==='')return null;
        $pdo=Connection::get();
        $q=$pdo->prepare('SELECT id,mobile FROM users WHERE mobile=? AND is_active=1 LIMIT 2');
        $q->execute([$mobile]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)!==1)return null;
        return ['id'=>(int)$rows[0]['id'],'mobile'=>$mobile];
    }

    public static function requestMobile(string $mobile): array
    {
        LoginMethods::requireEnabled('mobile');
        if(!SmsProvider::ready())throw new AuthException(503,'سرویس پیامک ورود تنظیم نشده است.','SMS_NOT_CONFIGURED');
        $mobile=self::normalizeMobile($mobile);
        if($mobile==='')throw new AuthException(422,'شماره همراه معتبر وارد کنید.','INVALID_MOBILE');
        $hasPurpose=self::schema();
        $pdo=Connection::get();$ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT COUNT(*) FROM sms_login_requests WHERE ip_address=? AND requested_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)');
            $q->execute([$ip]);
            if((int)$q->fetchColumn()>=10)throw new AuthException(429,'تعداد درخواست‌های ورود زیاد است. ۱۵ دقیقه بعد تلاش کنید.','SMS_RATE_LIMITED');
            $pdo->prepare('INSERT INTO sms_login_requests(ip_address) VALUES(?)')->execute([$ip]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        $user=self::userByMobile($mobile);
        if(!$user)return ['requested'=>true,'expiresIn'=>300];
        $uid=$user['id'];$q=$pdo->prepare($hasPurpose?"SELECT COUNT(*) FROM sms_login_challenges WHERE user_id=? AND purpose='login' AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)":"SELECT COUNT(*) FROM sms_login_challenges WHERE user_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)");
        $q->execute([$uid]);if((int)$q->fetchColumn()>=5)throw new AuthException(429,'تعداد درخواست‌های کد ورود زیاد است.','SMS_RATE_LIMITED');
        $code=(string)random_int(100000,999999);$hash=password_hash($code,PASSWORD_DEFAULT);
        $pdo->prepare($hasPurpose?"UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose='login' AND consumed_at IS NULL":"UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND consumed_at IS NULL")->execute([$uid]);
        $pdo->prepare($hasPurpose?"INSERT INTO sms_login_challenges(user_id,purpose,code_hash,expires_at) VALUES(?,'login',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))":"INSERT INTO sms_login_challenges(user_id,code_hash,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))")->execute([$uid,$hash]);
        try{
            $result=(new SmsProvider())->send($user['mobile'],'کد ورود سرویس‌یار: '.$code."\nاعتبار: ۵ دقیقه. کد را در اختیار دیگران قرار ندهید.");
            if(!($result['accepted']??false))throw new AuthException(502,'پیامک ورود توسط سرویس‌دهنده پذیرفته نشد.','SMS_PROVIDER_REJECTED');
        }catch(\Throwable $e){
            $pdo->prepare($hasPurpose?"UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose='login' AND code_hash=?":"UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND code_hash=?")->execute([$uid,$hash]);throw $e;
        }
        return ['requested'=>true,'expiresIn'=>300];
    }

    public static function verifyMobile(string $mobile,string $code): int
    {
        LoginMethods::requireEnabled('mobile');$code=self::digits($code);
        if(!preg_match('/^[0-9]{6}$/D',$code))throw new AuthException(401,'کد ورود نامعتبر است.','SMS_OTP_INVALID');
        $hasPurpose=self::schema();$user=self::userByMobile($mobile);
        if(!$user)throw new AuthException(401,'کد ورود نامعتبر است.','SMS_OTP_INVALID');
        $pdo=Connection::get();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare($hasPurpose?"SELECT id,code_hash,attempt_count FROM sms_login_challenges WHERE user_id=? AND purpose='login' AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE":"SELECT id,code_hash,attempt_count FROM sms_login_challenges WHERE user_id=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $q->execute([$user['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||(int)$row['attempt_count']>=5)throw new AuthException(401,'کد ورود نامعتبر یا منقضی است.','SMS_OTP_INVALID');
            $pdo->prepare('UPDATE sms_login_challenges SET attempt_count=attempt_count+1 WHERE id=?')->execute([$row['id']]);
            if(!password_verify($code,$row['code_hash'])){$pdo->commit();throw new AuthException(401,'کد ورود صحیح نیست.','SMS_OTP_INVALID');}
            $pdo->prepare('UPDATE sms_login_challenges SET consumed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$row['id']]);$pdo->commit();return $user['id'];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

}

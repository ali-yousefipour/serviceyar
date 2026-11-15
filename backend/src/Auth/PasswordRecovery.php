<?php
declare(strict_types=1);
namespace ServiceYar\Auth;

use ServiceYar\Database\Connection;
use ServiceYar\Integrations\BaleBotService;
use ServiceYar\Integrations\NeginSms;
use PDO;

final class PasswordRecovery
{
    private static function digits(string $value): string
    {
        return strtr(trim($value),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    }

    private static function baleRecoveryReady(): bool
    {
        try{
            $pdo=Connection::get();
            $q=$pdo->query("SELECT COLUMN_NAME,COLUMN_TYPE,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bale_auth_challenges' AND COLUMN_NAME IN ('purpose','code_hash')");
            $columns=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$columns[$row['COLUMN_NAME']]=$row;
            return isset($columns['purpose'],$columns['code_hash'])
                &&str_contains((string)$columns['purpose']['COLUMN_TYPE'],'password_reset')
                &&strtolower((string)$columns['code_hash']['DATA_TYPE'])==='varchar';
        }catch(\Throwable){return false;}
    }

    public static function methods(): array
    {
        $policy=LoginMethods::policies();
        $smsSchema=SmsLogin::recoveryReady();
        $baleSchema=self::baleRecoveryReady();
        return [
            'sms'=>(($policy['sms']??false)||($policy['mobile']??false))&&NeginSms::configured()&&class_exists(\SoapClient::class)&&$smsSchema,
            'bale'=>($policy['bale']??false)&&BaleBotService::ready()&&$baleSchema
        ];
    }

    private static function requireChannel(string $channel,bool $forDelivery=false): void
    {
        if(!in_array($channel,['sms','bale'],true))
            throw new AuthException(403,'روش بازیابی رمز عبور معتبر نیست.','PASSWORD_RECOVERY_METHOD_DISABLED');
        $policy=LoginMethods::policies();
        $schemaReady=$channel==='sms'?SmsLogin::recoveryReady():self::baleRecoveryReady();
        $enabled=$channel==='sms'
            ?(($policy['sms']??false)||($policy['mobile']??false))
            :($policy['bale']??false);
        $providerReady=$channel==='sms'
            ?(NeginSms::configured()&&class_exists(\\SoapClient::class))
            :BaleBotService::ready();
        if(!$enabled||!$schemaReady||($forDelivery&&!$providerReady))
            throw new AuthException(403,'این روش بازیابی رمز عبور فعال یا آماده نیست.','PASSWORD_RECOVERY_METHOD_DISABLED');
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

    public static function request(string $username,string $channel): array
    {
        self::requireChannel($channel);
        $username=self::digits($username);
        if($username===''||strlen($username)>100)throw new AuthException(422,'نام کاربری معتبر نیست.','VALIDATION_ERROR');
        $pdo=Connection::get();
        try {
            if($channel==='sms')$pdo->query("SELECT purpose FROM sms_login_challenges LIMIT 0");
            else $pdo->query("SELECT purpose FROM bale_auth_challenges LIMIT 0");
        }catch(\PDOException $e){
            throw new AuthException(503,'ساختار بازیابی رمز آماده نیست؛ مهاجرت 0112_login_recovery_and_mobile_otp.sql را اجرا کنید.','PASSWORD_RECOVERY_SCHEMA_REQUIRED');
        }
        $rateTable=$channel==='sms'?'sms_login_requests':'bale_otp_requests';
        $ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
        $q=$pdo->prepare('SELECT COUNT(*) FROM '.$rateTable.' WHERE ip_address=? AND requested_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)');
        $q->execute([$ip]);
        if((int)$q->fetchColumn()>=10)throw new AuthException(429,'تعداد درخواست‌های بازیابی زیاد است؛ ۱۵ دقیقه بعد دوباره تلاش کنید.','PASSWORD_RECOVERY_RATE_LIMITED');
        $pdo->prepare('INSERT INTO '.$rateTable.'(ip_address) VALUES(?)')->execute([$ip]);

        $q=$pdo->prepare('SELECT id,mobile FROM users WHERE username=? AND is_active=1 LIMIT 1');
        $q->execute([$username]);$user=$q->fetch(PDO::FETCH_ASSOC);
        // Keep the response identical for unknown, inactive, unlinked and incomplete accounts.
        if(!$user)return ['requested'=>true,'expiresIn'=>300];
        $uid=(int)$user['id'];
        $mobile=self::normalizeMobile((string)($user['mobile']??''));
        $chat=null;
        if($channel==='sms'&&$mobile==='')return ['requested'=>true,'expiresIn'=>300];
        if($channel==='bale'){
            $q=$pdo->prepare('SELECT chat_id FROM bale_user_links WHERE user_id=?');$q->execute([$uid]);$chat=$q->fetchColumn();
            if(!$chat)return ['requested'=>true,'expiresIn'=>300];
        }

        $table=$channel==='sms'?'sms_login_challenges':'bale_auth_challenges';
        $q=$pdo->prepare("SELECT COUNT(*) FROM ".$table." WHERE user_id=? AND purpose='password_reset' AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)");
        $q->execute([$uid]);
        if((int)$q->fetchColumn()>=5)throw new AuthException(429,'تعداد درخواست‌های بازیابی این حساب بیش از حد مجاز است.','PASSWORD_RECOVERY_RATE_LIMITED');
        $code=(string)random_int(100000,999999);$hash=password_hash($code,PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        try{
            $pdo->prepare("UPDATE ".$table." SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose='password_reset' AND consumed_at IS NULL")->execute([$uid]);
            if($channel==='sms'){
                $pdo->prepare("INSERT INTO sms_login_challenges(user_id,purpose,code_hash,expires_at) VALUES(?,'password_reset',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))")->execute([$uid,$hash]);
            }else{
                $pdo->prepare("INSERT INTO bale_auth_challenges(user_id,purpose,code_hash,expires_at) VALUES(?,'password_reset',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))")->execute([$uid,$hash]);
            }
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

        try{
            $message='کد بازیابی رمز عبور سرویس‌یار: '.$code."\nاعتبار: ۵ دقیقه. این کد را در اختیار دیگران قرار ندهید.";
            if($channel==='sms'){
                $sent=(new NeginSms())->send($mobile,$message);
                if(!($sent['accepted']??false))throw new AuthException(502,'پیامک بازیابی توسط سرویس‌دهنده پذیرفته نشد.','SMS_PROVIDER_REJECTED');
            }else{
                BaleBotService::sendAuthCodeToUser($uid,$message);
            }
        }catch(\Throwable $e){
            $pdo->prepare("UPDATE ".$table." SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose='password_reset' AND code_hash=?")->execute([$uid,$hash]);
            throw $e;
        }
        return ['requested'=>true,'expiresIn'=>300];
    }

    public static function confirm(string $username,string $channel,string $code,string $password,string $confirmation): array
    {
        self::requireChannel($channel);
        $username=self::digits($username);$code=self::digits($code);
        if($username===''||strlen($username)>100)throw new AuthException(422,'نام کاربری معتبر نیست.','VALIDATION_ERROR');
        if(!preg_match('/^[0-9]{6}$/D',$code))throw new AuthException(422,'کد تأیید باید ۶ رقم باشد.','VALIDATION_ERROR');
        if(strlen($password)<8||strlen($password)>72||str_contains($password,"\0"))throw new AuthException(422,'رمز جدید باید بین ۸ تا ۷۲ بایت باشد.','INVALID_PASSWORD');
        if($password!==$confirmation)throw new AuthException(422,'تکرار رمز عبور با رمز جدید یکسان نیست.','PASSWORD_CONFIRMATION_MISMATCH');

        $pdo=Connection::get();$table=$channel==='sms'?'sms_login_challenges':'bale_auth_challenges';
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT id FROM users WHERE username=? AND is_active=1 LIMIT 1 FOR UPDATE');
            $q->execute([$username]);$uid=(int)$q->fetchColumn();
            if(!$uid)throw new AuthException(401,'کد بازیابی نامعتبر یا منقضی است.','PASSWORD_RESET_INVALID');
            $q=$pdo->prepare("SELECT id,code_hash,attempt_count FROM ".$table." WHERE user_id=? AND purpose='password_reset' AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $q->execute([$uid]);$challenge=$q->fetch(PDO::FETCH_ASSOC);
            if(!$challenge||(int)$challenge['attempt_count']>=5)throw new AuthException(401,'کد بازیابی نامعتبر یا منقضی است.','PASSWORD_RESET_INVALID');
            $pdo->prepare('UPDATE '.$table.' SET attempt_count=attempt_count+1 WHERE id=?')->execute([$challenge['id']]);
            if(!password_verify($code,$challenge['code_hash'])){
                $pdo->commit();throw new AuthException(401,'کد بازیابی صحیح نیست.','PASSWORD_RESET_INVALID');
            }
            $pdo->prepare('UPDATE '.$table.' SET consumed_at=UTC_TIMESTAMP() WHERE id=?')->execute([$challenge['id']]);
            // Invalidate reset and sign-in challenges on both providers when their tables are installed.
            foreach(['sms_login_challenges','bale_auth_challenges'] as $challengeTable){
                $column=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='purpose'");
                $column->execute([$challengeTable]);
                if((int)$column->fetchColumn()>0){
                    $pdo->prepare("UPDATE ".$challengeTable." SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose IN ('login','password_reset') AND consumed_at IS NULL")->execute([$uid]);
                }
            }
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$uid]);
            $pdo->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL')->execute([$uid]);
            $pdo->prepare("INSERT INTO audit_logs(uuid,user_id,action,entity_type,entity_id) VALUES(UUID(),?,'auth.password_reset','user',?)")->execute([$uid,(string)$uid]);
            $pdo->commit();
            return ['changed'=>true,'requiresLogin'=>true];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}

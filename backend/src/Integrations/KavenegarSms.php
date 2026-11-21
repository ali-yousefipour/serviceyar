<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/**
 * First-party REST client. No browser credentials, external SDK or composer
 * dependency; transport is server-side HTTPS with peer verification.
 */
final class KavenegarSms
{
    private const KEY='kavenegar_api_key_enc';
    private const SENDER='kavenegar_sender';
    private const ENABLED='kavenegar_enabled';

    private static function settings(): array
    {
        try {
            $q=Connection::get()->prepare('SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN (?,?,?)');
            $q->execute([self::KEY,self::SENDER,self::ENABLED]);
            return $q->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\PDOException $e) {
            if($e->getCode()==='42S02'||(int)($e->errorInfo[1]??0)===1146)return [];
            throw $e;
        }
    }

    private static function credentials(): array
    {
        $settings=self::settings();
        $saved=(string)($settings[self::KEY]??'');
        return [
            'key'=>$saved!==''?NeginSms::decryptProviderSecret($saved):(string)config('KAVENEGAR_API_KEY',''),
            'sender'=>(string)($settings[self::SENDER]??config('KAVENEGAR_SENDER','')),
            'enabled'=>isset($settings[self::ENABLED])?$settings[self::ENABLED]==='1':config('KAVENEGAR_ENABLED','0')==='1',
        ];
    }

    public static function configured(): bool
    {
        try {
            $c=self::credentials();
            return $c['enabled'] && $c['key']!=='';
        }catch(\Throwable){return false;}
    }

    public static function ready(): bool
    {
        return self::configured() && function_exists('curl_init');
    }

    public static function overview(int $actor): array
    {
        if(!Authorization::isSuperAdmin($actor))
            throw new AuthException(403,'فقط ادمین کل به تنظیمات کاوه‌نگار دسترسی دارد.','SUPER_ADMIN_REQUIRED');
        $c=self::credentials();
        return [
            'enabled'=>$c['enabled'],
            'credentialsConfigured'=>$c['key']!=='',
            'sender'=>$c['sender'],
            'curlAvailable'=>function_exists('curl_init'),
            'ready'=>self::ready(),
        ];
    }

    public static function configure(int $actor,array $input): array
    {
        if(!Authorization::isSuperAdmin($actor))
            throw new AuthException(403,'فقط ادمین کل مجاز به تنظیم کاوه‌نگار است.','SUPER_ADMIN_REQUIRED');
        if(array_diff(array_keys($input),['enabled','apiKey','sender'])||
            !isset($input['enabled'])||!is_bool($input['enabled'])||
            (isset($input['apiKey'])&&!is_string($input['apiKey']))||
            (isset($input['sender'])&&!is_string($input['sender'])))
            throw new AuthException(422,'تنظیمات کاوه‌نگار معتبر نیست.','VALIDATION_ERROR');

        $key=trim((string)($input['apiKey']??''));
        $sender=trim((string)($input['sender']??''));
        if($key!==''&&(!preg_match('/^[A-Za-z0-9_-]{10,180}$/D',$key)))
            throw new AuthException(422,'قالب کلید API کاوه‌نگار معتبر نیست.','VALIDATION_ERROR');
        if(strlen($sender)>32||($sender!==''&&!preg_match('/^[A-Za-z0-9+_-]{1,32}$/D',$sender)))
            throw new AuthException(422,'شماره فرستنده کاوه‌نگار معتبر نیست.','VALIDATION_ERROR');
        if($input['enabled']&&$key===''&&self::credentials()['key']==='')
            throw new AuthException(422,'برای فعال‌سازی کاوه‌نگار ابتدا کلید API را ثبت کنید.','SMS_CREDENTIALS_REQUIRED');

        $pdo=Connection::get();
        $pdo->beginTransaction();
        try{
            $put=$pdo->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            if($key!=='')$put->execute([self::KEY,NeginSms::encryptProviderSecret($key)]);
            $put->execute([self::SENDER,$sender]);
            $put->execute([self::ENABLED,$input['enabled']?'1':'0']);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return self::overview($actor);
    }

    /** Convert documented response envelopes; no API key or message data in logs. */
    public static function parseResponse(int $httpStatus,string $body): array
    {
        $json=json_decode($body,true);
        if(!is_array($json)||!isset($json['return'])||!is_array($json['return']))
            throw new AuthException(502,'پاسخ سرویس پیامک کاوه‌نگار نامعتبر است.','SMS_PROVIDER_INVALID_RESPONSE');
        $status=(int)($json['return']['status']??0);
        if($httpStatus===200&&$status===200){
            $id=$json['entries'][0]['messageid']??null;
            if(!is_scalar($id)||trim((string)$id)==='')
                throw new AuthException(502,'شناسه پیامک در پاسخ کاوه‌نگار وجود ندارد.','SMS_PROVIDER_INVALID_RESPONSE');
            return ['accepted'=>true,'id'=>(string)$id];
        }
        return ['accepted'=>false,'code'=>(string)$status];
    }

    public function send(string $mobile,string $text): array
    {
        if(!preg_match('/^09\d{9}$/D',$mobile)||trim($text)==='')
            throw new AuthException(422,'شماره یا متن پیامک نامعتبر است.','VALIDATION_ERROR');
        if(strlen($text)>4000)throw new AuthException(422,'متن پیامک طولانی است.','VALIDATION_ERROR');
        if(!self::ready())
            throw new AuthException(503,'درگاه کاوه‌نگار تنظیم یا آماده نشده است.','SMS_NOT_CONFIGURED');
        $c=self::credentials();
        // Key appears only in the server-to-provider TLS request; never store
        // the complete URL in an exception, trace, response, or application log.
        $url='https://api.kavenegar.com/v1/'.rawurlencode($c['key']).'/sms/send.json';
        $fields=['receptor'=>$mobile,'message'=>$text];
        if($c['sender']!=='')$fields['sender']=$c['sender'];
        $handle=curl_init($url);
        if($handle===false)throw new AuthException(503,'اتصال به درگاه پیامک ممکن نیست.','PROVIDER_UNAVAILABLE');
        try {
            curl_setopt_array($handle,[
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>http_build_query($fields,'','&',PHP_QUERY_RFC3986),
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_CONNECTTIMEOUT=>8,
                CURLOPT_TIMEOUT=>25,
                CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_FOLLOWLOCATION=>false,
            ]);
            $body=curl_exec($handle);
            $status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);
            if($body===false)throw new AuthException(502,'نتیجه ارسال مشخص نیست؛ پیش از ارسال مجدد وضعیت پنل کاوه‌نگار بررسی شود.','SMS_UNCERTAIN');
            return self::parseResponse($status,(string)$body);
        }finally{curl_close($handle);}
    }
}

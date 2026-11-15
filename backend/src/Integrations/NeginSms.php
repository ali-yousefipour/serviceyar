<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use PDO;

final class NeginSms
{
    private const USERNAME_KEY='negin_sms_username_enc';
    private const PASSWORD_KEY='negin_sms_password_enc';
    private const ENABLED_KEY='negin_sms_enabled';

    private static function settings(): array
    {
        try {
            $q=Connection::get()->prepare('SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN (?,?,?)');
            $q->execute([self::USERNAME_KEY,self::PASSWORD_KEY,self::ENABLED_KEY]);
            return $q->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\PDOException $e) {
            if($e->getCode()==='42S02'||(int)($e->errorInfo[1]??0)===1146)return [];
            throw $e;
        }
    }

    private static function cipherKey(): string
    {
        $directory=dirname(__DIR__,3).'/storage/private';
        if(!is_dir($directory)&&!@mkdir($directory,0700,true)&&!is_dir($directory))
            throw new AuthException(503,'پوشه امن ذخیره‌سازی تنظیمات پیامک قابل ایجاد نیست؛ دسترسی نوشتن پوشه storage را بررسی کنید.','SMS_SECRET_STORAGE_UNAVAILABLE');
        $path=$directory.'/negin-sms.key';
        if(!is_file($path)){
            $generated=bin2hex(random_bytes(32));
            $handle=@fopen($path,'x');
            if($handle!==false){
                @chmod($path,0600);
                try{
                    if(fwrite($handle,$generated)!==strlen($generated))
                        throw new \RuntimeException('Unable to persist SMS encryption key');
                }finally{fclose($handle);}
            }
        }
        $key=@file_get_contents($path);
        if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',trim($key)))
            throw new AuthException(503,'کلید رمزگذاری اطلاعات پیامک قابل خواندن نیست؛ مجوز storage/private را بررسی کنید.','SMS_SECRET_STORAGE_UNAVAILABLE');
        return hash('sha256','serviceyar-negin-sms-v1|'.trim($key),true);
    }

    private static function encrypt(string $plain): string
    {
        if(!function_exists('openssl_encrypt'))
            throw new AuthException(503,'افزونه OpenSSL برای ذخیره امن اطلاعات پیامک فعال نیست.','SMS_ENCRYPTION_UNAVAILABLE');
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($plain,'aes-256-gcm',self::cipherKey(),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false)throw new AuthException(503,'رمزگذاری اطلاعات پیامک ناموفق بود.','SMS_ENCRYPTION_ERROR');
        return base64_encode($iv.$tag.$cipher);
    }

    private static function decrypt(string $encoded): string
    {
        if(!function_exists('openssl_decrypt'))
            throw new AuthException(503,'افزونه OpenSSL برای خواندن امن اطلاعات پیامک فعال نیست.','SMS_ENCRYPTION_UNAVAILABLE');
        $raw=base64_decode($encoded,true);
        if($raw===false||strlen($raw)<29)
            throw new AuthException(503,'اطلاعات رمزگذاری‌شده پیامک معتبر نیست؛ نام کاربری و رمز پنل را دوباره ثبت کنید.','SMS_ENCRYPTION_ERROR');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::cipherKey(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        if($plain===false)
            throw new AuthException(503,'رمزگشایی اطلاعات پیامک ناموفق بود؛ کلید خصوصی storage را بررسی کنید یا اطلاعات پنل را دوباره ثبت کنید.','SMS_ENCRYPTION_ERROR');
        return $plain;
    }

    private static function credentials(): array
    {
        $settings=self::settings();
        $storedUser=(string)($settings[self::USERNAME_KEY]??'');
        $storedPass=(string)($settings[self::PASSWORD_KEY]??'');
        if($storedUser!==''||$storedPass!==''){
            if($storedUser===''||$storedPass==='')
                throw new AuthException(503,'اطلاعات ورود پنل پیامک ناقص است؛ نام کاربری و رمز را دوباره با هم ثبت کنید.','SMS_CREDENTIALS_INCOMPLETE');
            return ['username'=>self::decrypt($storedUser),'password'=>self::decrypt($storedPass),'source'=>'database'];
        }
        return [
            'username'=>(string)config('NEGIN_SMS_USERNAME',''),
            'password'=>(string)config('NEGIN_SMS_PASSWORD',''),
            'source'=>'environment'
        ];
    }

    private static function enabled(): bool
    {
        $settings=self::settings();
        return isset($settings[self::ENABLED_KEY])
            ?$settings[self::ENABLED_KEY]==='1'
            :config('NEGIN_SMS_ENABLED','0')==='1';
    }

    public static function configured(): bool
    {
        $credentials=self::credentials();
        return self::enabled()&&$credentials['username']!==''&&$credentials['password']!=='';
    }

    public static function ready(): bool
    {
        return self::configured()&&class_exists(\SoapClient::class);
    }

    public static function overview(int $actor): array
    {
        if(!Authorization::isSuperAdmin($actor))
            throw new AuthException(403,'فقط ادمین کل به تنظیمات پیامک نگین دسترسی دارد.','SUPER_ADMIN_REQUIRED');
        $credentials=self::credentials();
        $username=(string)$credentials['username'];
        $hint='';
        if($username!==''){
            $length=strlen($username);
            $hint=$length<=3?str_repeat('*',$length):substr($username,0,1).str_repeat('*',min(8,$length-3)).substr($username,-2);
        }
        return [
            'enabled'=>self::enabled(),
            'credentialsConfigured'=>$username!==''&&(string)$credentials['password']!=='',
            'usernameHint'=>$hint,
            'source'=>$credentials['source'],
            'soapAvailable'=>class_exists(\SoapClient::class),
            'ready'=>self::ready()
        ];
    }

    public static function configure(int $actor,array $input): array
    {
        if(!Authorization::isSuperAdmin($actor))
            throw new AuthException(403,'فقط ادمین کل مجاز به تنظیم سرویس پیامک است.','SUPER_ADMIN_REQUIRED');
        if(array_diff(array_keys($input),['enabled','username','password'])||!array_key_exists('enabled',$input)||!is_bool($input['enabled']))
            throw new AuthException(422,'اطلاعات تنظیمات پیامک معتبر نیست.','VALIDATION_ERROR');

        $username=trim((string)($input['username']??''));
        $password=(string)($input['password']??'');
        $hasUsername=$username!=='';
        $hasPassword=trim($password)!=='';
        if($hasUsername!==$hasPassword)
            throw new AuthException(422,'برای تغییر اطلاعات پنل، نام کاربری و رمز عبور را هر دو وارد کنید.','SMS_CREDENTIALS_PAIR_REQUIRED');
        if($hasUsername&&(
            strlen($username)>150||preg_match('/[\x00-\x1F\x7F]/',$username)||
            strlen($password)>500
        ))
            throw new AuthException(422,'طول یا قالب اطلاعات پنل پیامک معتبر نیست.','VALIDATION_ERROR');

        $pdo=Connection::get();
        try{
            $pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
                setting_key VARCHAR(150) NOT NULL PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }catch(\PDOException){
            throw new AuthException(503,'جدول تنظیمات سامانه آماده نیست؛ مهاجرت 0032 یا دسترسی دیتابیس را بررسی کنید.','PLATFORM_SETTINGS_MIGRATION_REQUIRED');
        }

        if($hasUsername){
            $put=$pdo->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $put->execute([self::USERNAME_KEY,self::encrypt($username)]);
            $put->execute([self::PASSWORD_KEY,self::encrypt($password)]);
        }
        $put=$pdo->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $put->execute([self::ENABLED_KEY,$input['enabled']?'1':'0']);

        if($input['enabled']&&!self::configured())
            throw new AuthException(422,'برای فعال‌کردن پیامک، ابتدا نام کاربری و رمز پنل نگین را ثبت کنید.','SMS_CREDENTIALS_REQUIRED');

        return self::overview($actor);
    }

    public static function result(object $response): array
    {
        $raw=$response->pMessageIds->long??[];$ids=is_array($raw)?$raw:[$raw];$ids=array_values(array_filter(array_map('strval',$ids),fn($id)=>preg_match('/^-?\d+$/',$id)&&$id!=='0'));
        $code=(string)($response->SendSmsResult??'0');
        if($ids)return ['accepted'=>true,'id'=>$ids[0]];
        if(is_numeric($code)&&(float)$code<0)return ['accepted'=>true,'id'=>$code];
        return ['accepted'=>false,'code'=>ctype_digit($code)?$code:'unknown'];
    }

    public function send(string $mobile,string $text): array
    {
        if(!self::configured())throw new AuthException(503,'سرویس پیامک نگین تنظیم یا فعال نشده است.','SMS_NOT_CONFIGURED');
        if(!preg_match('/^09\d{9}$/',$mobile)||trim($text)==='')throw new AuthException(422,'شماره یا متن پیامک نامعتبر است.','VALIDATION_ERROR');
        if(!class_exists(\SoapClient::class))throw new AuthException(503,'افزونه SOAP برای پیامک نگین فعال نیست.','PROVIDER_UNAVAILABLE');
        $credentials=self::credentials();
        try{
            $client=new \SoapClient(__DIR__.'/negin.wsdl',['location'=>'https://sms.3300.ir/almassms.asmx','exceptions'=>true,'trace'=>false,'connection_timeout'=>8,'cache_wsdl'=>WSDL_CACHE_MEMORY,'stream_context'=>stream_context_create(['http'=>['timeout'=>25],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]])]);
            return self::result($client->SendSms(['pUsername'=>$credentials['username'],'pPassword'=>$credentials['password'],'messages'=>[$text],'mobiles'=>[$mobile]]));
        }catch(\SoapFault){throw new AuthException(502,'نتیجه ارسال پیامک مشخص نیست؛ برای جلوگیری از ارسال تکراری، وضعیت پنل نگین بررسی شود.','SMS_UNCERTAIN');}
    }
}

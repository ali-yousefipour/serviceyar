<?php
declare(strict_types=1);
namespace ServiceYar\Settings;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

final class PlatformSettings
{
    private const KEYS=['neshan_service_key','neshan_web_key'];
    public static function value(string $key): string
    {
        if(!in_array($key,self::KEYS,true))return '';
        $query=Connection::get()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');
        $query->execute([$key]);return (string)($query->fetchColumn()?:'');
    }
    public static function overview(int $uid): array
    {
        if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'تنظیمات سامانه فقط در اختیار ادمین کل است.','SUPER_ADMIN_REQUIRED');
        return ['neshanServiceConfigured'=>self::value('neshan_service_key')!==''||config('NESHAN_SERVICE_KEY','')!=='',
            'neshanWebConfigured'=>self::value('neshan_web_key')!==''||config('NESHAN_WEB_KEY','')!=='',
            'smsConfigured'=>\ServiceYar\Integrations\NeginSms::configured(),
            'baleConfigured'=>\ServiceYar\Integrations\BaleClient::configured(),
            'smsManagedByEnvironment'=>true];
    }
    public static function save(int $uid,array $payload): array
    {
        if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط ادمین کل مجاز به تنظیم کلیدهای سامانه است.','SUPER_ADMIN_REQUIRED');
        if(array_diff(array_keys($payload),self::KEYS))throw new AuthException(422,'تنظیمات ناشناخته مجاز نیست.','VALIDATION_ERROR');
        $pdo=Connection::get();
        foreach($payload as $key=>$value){
            if(!is_string($value))throw new AuthException(422,'کلید سرویس معتبر نیست.','VALIDATION_ERROR');
            if($value==='')continue;
            $prefix=$key==='neshan_web_key'?'web':'service';
            if(!preg_match('/^'.preg_quote($prefix,'/').'\.[a-zA-Z0-9-]{16,200}$/D',$value))
                throw new AuthException(422,'قالب کلید نشان معتبر نیست.','VALIDATION_ERROR');
            $q=$pdo->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $q->execute([$key,$value]);
        }
        return self::overview($uid);
    }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

final class SmsProvider
{
    private const SETTING='sms_provider_selected';

    public static function selected(): string
    {
        try{
            $q=Connection::get()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');
            $q->execute([self::SETTING]);$v=$q->fetchColumn();
            return $v==='kavenegar'?'kavenegar':'negin';
        }catch(\PDOException $e){
            if($e->getCode()==='42S02'||(int)($e->errorInfo[1]??0)===1146)return 'negin';
            throw $e;
        }
    }

    public static function configured(): bool
    {
        return self::selected()==='kavenegar'?KavenegarSms::configured():NeginSms::configured();
    }

    public static function ready(): bool
    {
        return self::selected()==='kavenegar'?KavenegarSms::ready():NeginSms::ready();
    }

    public static function overview(int $actor): array
    {
        if(!Authorization::isSuperAdmin($actor))
            throw new AuthException(403,'تنظیمات درگاه پیامک فقط در اختیار ادمین کل است.','SUPER_ADMIN_REQUIRED');
        return [
            'selected'=>self::selected(),
            'providers'=>['negin'=>NeginSms::overview($actor),'kavenegar'=>KavenegarSms::overview($actor)],
            'ready'=>self::ready()
        ];
    }

    public static function configure(int $actor,array $input): array
    {
        if(!Authorization::isSuperAdmin($actor))
            throw new AuthException(403,'فقط ادمین کل مجاز به انتخاب درگاه پیامک است.','SUPER_ADMIN_REQUIRED');
        if(count($input)!==1||!is_string($input['provider']??null)||!in_array($input['provider'],['negin','kavenegar'],true))
            throw new AuthException(422,'درگاه پیامک معتبر نیست.','VALIDATION_ERROR');
        $selected=$input['provider'];
        $ready=$selected==='kavenegar'?KavenegarSms::ready():NeginSms::ready();
        if(!$ready)
            throw new AuthException(422,'درگاه انتخاب‌شده آماده نیست؛ ابتدا مشخصات و پیش‌نیازهای آن را تکمیل کنید.','SMS_PROVIDER_NOT_READY');
        $q=Connection::get()->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $q->execute([self::SETTING,$selected]);
        return self::overview($actor);
    }

    public function send(string $mobile,string $text): array
    {
        // Deliberately never fall back to a different provider after an
        // uncertain delivery result: retries could duplicate an OTP/message.
        return self::selected()==='kavenegar'
            ?(new KavenegarSms())->send($mobile,$text)
            :(new NeginSms())->send($mobile,$text);
    }
}

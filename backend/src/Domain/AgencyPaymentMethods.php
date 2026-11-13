<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use PDO;

/** Each agency owns its editable payment-method catalogue; historical codes remain readable. */
final class AgencyPaymentMethods
{
    public const KEY = 'finance_payment_methods';
    public const DEFAULTS = [
        ['code'=>'cash','name'=>'نقدی','active'=>true],
        ['code'=>'card','name'=>'کارت به کارت','active'=>true],
        ['code'=>'bank','name'=>'پایا','active'=>true],
        ['code'=>'transfer','name'=>'ساتنا','active'=>true],
        ['code'=>'cheque','name'=>'چک','active'=>true],
        ['code'=>'wallet','name'=>'کیف پول','active'=>true],
    ];

    public static function validate(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input) || count($input)>30 || count($input)<1)
            throw new AuthException(422,'روش‌های دریافت و پرداخت باید یک فهرست معتبر باشند.','VALIDATION_ERROR');
        $used=[];$out=[];$active=0;
        foreach ($input as $method) {
            if (!is_array($method) || array_diff(array_keys($method),['code','name','active']))
                throw new AuthException(422,'ساختار روش پرداخت نامعتبر است.','VALIDATION_ERROR');
            $code=$method['code']??null;$name=$method['name']??null;$enabled=$method['active']??true;
            if (!is_string($code)||!preg_match('/^[a-z][a-z0-9_]{1,39}$/D',$code)||isset($used[$code]))
                throw new AuthException(422,'کد روش پرداخت نامعتبر یا تکراری است.','VALIDATION_ERROR');
            if (!is_string($name)||trim($name)===''||mb_strlen(trim($name))>80)
                throw new AuthException(422,'عنوان روش پرداخت نامعتبر است.','VALIDATION_ERROR');
            if (!is_bool($enabled)) throw new AuthException(422,'وضعیت روش پرداخت نامعتبر است.','VALIDATION_ERROR');
            $used[$code]=true;$active+=(int)$enabled;
            $out[]=['code'=>$code,'name'=>trim($name),'active'=>$enabled];
        }
        if (!$active)throw new AuthException(422,'حداقل یک روش پرداخت باید فعال بماند.','VALIDATION_ERROR');
        return $out;
    }

    public static function get(int $uid,int $aid): array
    {
        Authorization::requirePermission($uid,'finance.view');
        Authorization::requireAgencyAccess($uid,$aid);
        return self::forAgency($aid);
    }

    public static function forAgency(int $aid): array
    {
        $q=Connection::get()->prepare('SELECT setting_value FROM agency_settings WHERE agency_id=? AND setting_key=? AND is_secret=0');
        $q->execute([$aid,self::KEY]);$raw=$q->fetchColumn();
        if ($raw===false)return self::DEFAULTS;
        $methods=json_decode((string)$raw,true);
        try{return self::validate($methods);}catch(AuthException){return self::DEFAULTS;}
    }

    public static function assertUsable(int $aid,string $code,?string $existingCode=null): void
    {
        foreach (self::forAgency($aid) as $method) {
            if ($method['code']===$code && ($method['active'] || $existingCode===$code))return;
        }
        if ($existingCode!==null && $existingCode===$code)return; // Legacy entries with a removed method remain editable.
        throw new AuthException(422,'روش دریافت یا پرداخت انتخاب‌شده برای این شرکت فعال نیست.','PAYMENT_METHOD_DISABLED');
    }
}

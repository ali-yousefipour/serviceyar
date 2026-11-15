<?php
declare(strict_types=1);
namespace ServiceYar\Auth;
use ServiceYar\Database\Connection;
use ServiceYar\Integrations\BaleBotService;
use ServiceYar\Integrations\NeginSms;

final class LoginMethods
{
    private const KEYS=['password'=>'login_method_password','sms'=>'login_method_sms','bale'=>'login_method_bale'];

    public static function policies(): array
    {
        $values=[];
        try {
            $q=Connection::get()->query("SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN ('login_method_password','login_method_sms','login_method_bale')");
            $values=$q->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch(\PDOException $e) {
            if($e->getCode()!=='42S02'&&(int)($e->errorInfo[1]??0)!==1146)throw $e;
        }
        $result=[];
        foreach(self::KEYS as $key=>$setting)$result[$key]=!isset($values[$setting])||$values[$setting]==='1';
        return $result;
    }

    public static function publicStatus(): array
    {
        $configured=self::policies();
        $available=['password'=>true,'sms'=>NeginSms::configured(),'bale'=>BaleBotService::ready()];
        return ['methods'=>array_map(fn($key)=>$configured[$key]&&$available[$key],array_keys(self::KEYS)) === [] ? [] : [
            'password'=>$configured['password'],
            'sms'=>$configured['sms']&&$available['sms'],
            'bale'=>$configured['bale']&&$available['bale']
        ],'configured'=>$configured,'available'=>$available];
    }

    public static function requireEnabled(string $method): void
    {
        $policy=self::policies();
        if(!array_key_exists($method,$policy)||!$policy[$method])
            throw new AuthException(403,'این روش ورود توسط مدیر سامانه غیرفعال شده است.','LOGIN_METHOD_DISABLED');
    }

    public static function overview(int $actor): array
    {
        if(!Authorization::isSuperAdmin($actor))throw new AuthException(403,'فقط ادمین کل به تنظیمات روش‌های ورود دسترسی دارد.','FORBIDDEN');
        return self::publicStatus();
    }

    public static function save(int $actor,array $input): array
    {
        if(!Authorization::isSuperAdmin($actor))throw new AuthException(403,'فقط ادمین کل مجاز است.','FORBIDDEN');
        if(array_keys($input)!==array_keys(self::KEYS))
            throw new AuthException(422,'هر سه روش ورود باید تعیین شوند.','VALIDATION_ERROR');
        foreach($input as $name=>$enabled)
            if(!is_bool($enabled))throw new AuthException(422,'تنظیم روش ورود باید درست یا نادرست باشد.','VALIDATION_ERROR');
        if(!in_array(true,$input,true))
            throw new AuthException(422,'حداقل یک روش ورود باید فعال بماند.','LOGIN_METHOD_REQUIRED');
        $pdo=Connection::get();
        $pdo->beginTransaction();
        try {
            foreach(self::KEYS as $name=>$key){
                $q=$pdo->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
                $q->execute([$key,$input[$name]?'1':'0']);
            }
            $pdo->commit();
        } catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return self::overview($actor);
    }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

final class FinancialAccounts
{
    public const KEY='finance_accounts';
    public const SLOTS=['serviceIncome','driverSalary','discount','penalty','bonus','cash','bank','absence','wallet','chequeReceivable'];

    public static function get(int $uid,int $aid): array
    {
        Authorization::requirePermission($uid,'finance.view');Authorization::requireAgencyAccess($uid,$aid);
        $q=Connection::get()->prepare('SELECT setting_value FROM agency_settings WHERE agency_id=? AND setting_key=? AND is_secret=0');$q->execute([$aid,self::KEY]);
        $value=json_decode((string)$q->fetchColumn(),true);$out=[];
        foreach(self::SLOTS as $slot)$out[$slot]=is_array($value)&&isset($value[$slot])?(int)$value[$slot]:null;
        return $out;
    }

    public static function validate(int $uid,int $aid,mixed $value): array
    {
        Authorization::requirePermission($uid,'finance.manage');Authorization::requireAgencyAccess($uid,$aid);
        if(!is_array($value)||array_diff(array_keys($value),self::SLOTS))throw new AuthException(422,'نوع حساب پیش‌فرض نامعتبر است.','VALIDATION_ERROR');
        $out=[];$q=Connection::get()->prepare('SELECT id FROM accounting_accounts WHERE id=? AND agency_id=? AND is_active=1');
        foreach(self::SLOTS as $slot){$id=$value[$slot]??null;if($id===null||$id===''){$out[$slot]=null;continue;}
            if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new AuthException(422,'شناسه حساب نامعتبر است.','VALIDATION_ERROR');
            $q->execute([(int)$id,$aid]);if(!$q->fetchColumn())throw new AuthException(422,'حساب پیش‌فرض باید فعال و متعلق به همین شرکت باشد.','CROSS_SCOPE_REFERENCE');$out[$slot]=(int)$id;
        }
        return $out;
    }

    public static function save(int $uid,int $aid,array $value): array
    {
        return OperationsService::transaction(function(PDO $p)use($uid,$aid,$value){
            $value=self::validate($uid,$aid,$value);
            $p->prepare('INSERT INTO agency_settings(agency_id,setting_key,setting_value,is_secret) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),is_secret=0')->execute([$aid,self::KEY,json_encode($value,JSON_UNESCAPED_UNICODE)]);
            OperationsService::audit($uid,$aid,'save_financial_accounts','agency_settings',$aid);return $value;
        });
    }
}

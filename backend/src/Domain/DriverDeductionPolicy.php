<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

/** Company-specific driver deduction policy, with backward-compatible defaults. */
final class DriverDeductionPolicy
{
    public const KEY='driver_deduction_rates';
    public const DEFAULTS=[
        'supervision'=>3.0,
        'coordination'=>1.0,
        'overhead'=>7.0,
        'insurance'=>7.8,
    ];

    public static function validate(mixed $value): string
    {
        if (is_string($value)) $value=json_decode($value,true);
        if (!is_array($value)||array_keys($value)!==array_keys(self::DEFAULTS)) {
            throw new AuthException(422,'چهار درصد کسورات باید به‌صورت کامل مشخص شوند.','INVALID_DEDUCTION_RATES');
        }
        $out=[];
        foreach (self::DEFAULTS as $key=>$default) {
            $v=$value[$key];
            if (!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>100) {
                throw new AuthException(422,'درصد کسورات نامعتبر است.','INVALID_DEDUCTION_RATES');
            }
            $out[$key]=round((float)$v,3);
        }
        if (array_sum($out)>100) {
            throw new AuthException(422,'مجموع کسورات نباید بیشتر از ۱۰۰ درصد باشد.','INVALID_DEDUCTION_RATES');
        }
        return json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }

    public static function rates(int $agencyId): array
    {
        if ($agencyId<=0) return self::DEFAULTS;
        $stmt=Connection::get()->prepare('SELECT setting_value FROM agency_settings WHERE agency_id=:a AND setting_key=:k LIMIT 1');
        $stmt->execute(['a'=>$agencyId,'k'=>self::KEY]);
        $stored=$stmt->fetchColumn();
        if ($stored===false||$stored===null||$stored==='') return self::DEFAULTS;
        $decoded=json_decode((string)$stored,true);
        if (!is_array($decoded)) return self::DEFAULTS;
        try {return json_decode(self::validate($decoded),true,512,JSON_THROW_ON_ERROR);}
        catch (\Throwable) {return self::DEFAULTS;}
    }

    public static function calculate(float $gross,array $rates): array
    {
        $gross=max(0,$gross);
        $amounts=[];
        foreach (self::DEFAULTS as $key=>$default) {
            $amounts[$key.'_deduction']=round($gross*(float)$rates[$key]/100);
            $amounts[$key.'_rate']=(float)$rates[$key];
        }
        $total=array_sum(array_filter($amounts,fn($k)=>str_ends_with($k,'_deduction'),ARRAY_FILTER_USE_KEY));
        return $amounts+[
            'deduction_rate'=>array_sum($rates),
            'total_deductions'=>$total,
            'net_earned'=>max(0,round($gross)-$total)
        ];
    }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

final class FinancialRules
{
    public const KEY='finance_rules';
    public static function defaults(): array
    {
        return ['mode'=>'monthly_distance','baseAmount'=>0,'perKm'=>0,'includedKm'=>0,'minimumAmount'=>0,'vehicleFactor'=>1,'discountPercent'=>0,'driverPercent'=>100,'deductionPercent'=>0,'fixedDeduction'=>0,'nominalDays'=>22,'rounding'=>1];
    }
    public static function validate(mixed $input): array
    {
        if(is_string($input)){$input=json_decode($input,true);}
        if(!is_array($input)||array_is_list($input))self::invalid();
        $rules=self::defaults();if(array_diff(array_keys($input),array_keys($rules)))self::invalid();
        $rules=array_merge($rules,$input);
        if(!in_array($rules['mode'],['fixed','monthly_distance','daily_distance'],true))self::invalid();
        foreach(['baseAmount'=>1000000000,'perKm'=>1000000000,'includedKm'=>1000,'minimumAmount'=>1000000000,'vehicleFactor'=>5,'discountPercent'=>100,'driverPercent'=>100,'deductionPercent'=>100,'fixedDeduction'=>1000000000,'nominalDays'=>31,'rounding'=>1000000] as $key=>$max){
            $v=$rules[$key];if(!is_scalar($v)||!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>$max)self::invalid();
            if(in_array($key,['baseAmount','minimumAmount','fixedDeduction','nominalDays','rounding'],true)&&floor((float)$v)!==(float)$v)self::invalid();
            if(round((float)$v,3)!==(float)$v)self::invalid();$rules[$key]=(float)$v;
        }
        if($rules['nominalDays']<1||$rules['rounding']<1||$rules['vehicleFactor']<=0)self::invalid();return $rules;
    }
    private static function invalid(): never {throw new AuthException(422,'قواعد مالی یا ورودی محاسبه نامعتبر است.','VALIDATION_ERROR');}
    public static function stored(int $aid): array
    {
        $q=Connection::get()->prepare('SELECT setting_value FROM agency_settings WHERE agency_id=? AND setting_key=? AND is_secret=0');$q->execute([$aid,self::KEY]);$v=$q->fetchColumn();return $v===false?self::defaults():self::validate($v);
    }
    public static function preview(int $uid,int $aid,array $body): array
    {
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,isset($body['rules'])?'settings.view':'finance.view');
        return self::calculate(isset($body['rules'])?self::validate($body['rules']):self::stored($aid),$body['input']??[]);
    }
    public static function calculate(array $rules,mixed $input): array
    {
        $r=self::validate($rules);if(!is_array($input))self::invalid();
        $v=array_merge(['distanceKm'=>0,'days'=>$r['nominalDays'],'tripsPerDay'=>2],$input);
        if(array_diff(array_keys($v),['distanceKm','days','tripsPerDay']))self::invalid();
        foreach(['distanceKm'=>1000,'days'=>31,'tripsPerDay'=>4] as $k=>$max){if(!is_scalar($v[$k])||!is_numeric($v[$k])||!is_finite((float)$v[$k])||(float)$v[$k]<0||(float)$v[$k]>$max||round((float)$v[$k],3)!==(float)$v[$k])self::invalid();$v[$k]=(float)$v[$k];}
        if($v['days']>$r['nominalDays']||$v['tripsPerDay']<1||floor($v['days'])!==$v['days']||floor($v['tripsPerDay'])!==$v['tripsPerDay'])self::invalid();
        $km=max(0,$v['distanceKm']-$r['includedKm']);
        $distance=$r['mode']==='fixed'?0:$km*$r['perKm'];
        if($r['mode']==='daily_distance')$distance*=$v['tripsPerDay']*$r['nominalDays'];
        $gross=max($r['minimumAmount'],($r['baseAmount']+$distance)*$r['vehicleFactor'])*$v['days']/$r['nominalDays'];
        $fee=$gross*(1-$r['discountPercent']/100);$step=$r['rounding'];$fee=round($fee/$step,0,PHP_ROUND_HALF_UP)*$step;
        $share=round($fee*$r['driverPercent']/100,0,PHP_ROUND_HALF_UP);$deduction=min($share,round($share*$r['deductionPercent']/100+$r['fixedDeduction']*$v['days']/$r['nominalDays'],0,PHP_ROUND_HALF_UP));
        if($fee>1000000000000)self::invalid();
        return ['monthlyFee'=>(int)$fee,'driverGross'=>(int)$share,'deductions'=>(int)$deduction,'driverNet'=>(int)($share-$deduction),'companyShare'=>(int)($fee-$share),'grossBeforeDiscount'=>round($gross,2),'input'=>$v,'rules'=>$r,'currency'=>'IRR'];
    }
}

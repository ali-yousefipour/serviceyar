<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use DateTimeImmutable;
use DateTimeZone;
use ServiceYar\Auth\AuthException;

/**
 * Pure effective-date and proration rules for vehicle-class changes.
 * This does not mutate student invoices, immutable contracts, or payroll.
 * Financial services must consume frozen rate periods rather than current vehicle class.
 */
final class VehicleClassEffectiveDates
{
    public static function parseChoices(array $body): array
    {
        $contractChoice=self::boolChoice($body,'updateContract');
        $contract=$contractChoice
            ?self::requireDate($body['contractEffectiveDate']??null,'تاریخ اعمال تغییر کلاس در قرارداد')
            :null;
        $debt=self::boolChoice($body,'updateStudentDebt');
        $pay=self::boolChoice($body,'updateDriverPay');
        return [
            'updateContract'=>$contractChoice,
            'contractEffectiveDate'=>$contract,
            'updateStudentDebt'=>$debt,
            'studentDebtEffectiveDate'=>$debt
                ?self::requireDate($body['studentDebtEffectiveDate']??null,'تاریخ اعمال نرخ جدید بدهی دانش‌آموز')
                :null,
            'updateDriverPay'=>$pay,
            'driverPayEffectiveDate'=>$pay
                ?self::requireDate($body['driverPayEffectiveDate']??null,'تاریخ اعمال نرخ جدید حقوق راننده')
                :null,
        ];
    }

    private static function boolChoice(array $body,string $name): bool
    {
        if(!array_key_exists($name,$body)||!is_bool($body[$name]))
            throw new AuthException(422,'تصمیم اعمال تغییرات مالی باید صریحاً با بله یا خیر ثبت شود.','VEHICLE_CLASS_CHOICE_REQUIRED');
        return $body[$name];
    }

    public static function requireDate(mixed $date,string $title): string
    {
        if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))
            throw new AuthException(422,$title.' معتبر نیست.','INVALID_EFFECTIVE_DATE');
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
        if(!$parsed||$parsed->format('Y-m-d')!==$date)
            throw new AuthException(422,$title.' معتبر نیست.','INVALID_EFFECTIVE_DATE');
        return $date;
    }

    /**
     * Project the full contracted school year by successive signed rate
     * periods. The academic-year boundaries and 8.5/9 month rule come from
     * ContractBilling, so the source invoice is never recalculated in place.
     */
    public static function annualEntitlement(string $year,bool $primary,int $currentMonthly,array $periods): int
    {
        $bounds=ContractBilling::defaults($year,$primary);
        $start=$bounds['startDate'];$end=$bounds['endDate'];
        if(!$periods){
            $months=ContractBilling::quote($year,$start,$end,0)['months'];
            return (int)round($currentMonthly*$months);
        }
        usort($periods,static fn($a,$b)=>strcmp((string)$a['effective_date'],(string)$b['effective_date'])
            ?:((int)($a['id']??0)<=>(int)($b['id']??0)));
        $rate=(int)$periods[0]['old_monthly_irr'];
        $cursor=$start;$total=0.0;
        foreach($periods as $period){
            $effective=self::requireDate($period['effective_date'],'تاریخ دوره');
            if($effective>$cursor){
                $last=min($end,(new DateTimeImmutable($effective))->modify('-1 day')->format('Y-m-d'));
                if($cursor<=$last)
                    $total+=$rate*ContractBilling::quote($year,$cursor,$last,0)['months'];
            }
            $cursor=max($cursor,$effective);
            $rate=(int)$period['new_monthly_irr'];
            if($cursor>$end)break;
        }
        if($cursor<=$end)$total+=$rate*ContractBilling::quote($year,$cursor,$end,0)['months'];
        return (int)round($total);
    }

    /**
     * Prorate a continuous ISO date period [start,end] with old/new monthly
     * rates. The caller supplies ONE complete accounting month (normally
     * a Jalali month converted to ISO). Denominator is the actual day count
     * of that accounting period; final rounding happens only once.
     * This routine must never be called across multiple accounting months.
     */
    /**
     * Blend multiple successive class changes within one accounting period.
     * The periods must be ordered chronologically; the oldest entry contains
     * the immutable rate that applied before the first change.
     * Returns the existing monthly rate when no approved changes exist.
     */
    public static function blendedRate(string $from,string $to,int $currentMonthly,array $periods): int
    {
        self::requireDate($from,'شروع دوره');
        self::requireDate($to,'پایان دوره');
        if($from>$to||$currentMonthly<0)
            throw new AuthException(422,'بازه یا مبلغ محاسبه نامعتبر است.','INVALID_RATE_PERIOD');
        if(!$periods)return $currentMonthly;
        usort($periods,static fn($a,$b)=>strcmp((string)$a['effective_date'],(string)$b['effective_date'])
            ?: ((int)($a['id']??0)<=>(int)($b['id']??0)));
        $first=new DateTimeImmutable($from.'T12:00:00+00:00');
        $last=new DateTimeImmutable($to.'T12:00:00+00:00');
        $days=(int)$first->diff($last)->days+1;
        if($days>31)throw new AuthException(422,'محاسبه دوره‌ای باید برای یک ماه انجام شود.','INVALID_RATE_PERIOD');
        $sum=0.0;
        for($date=$first;$date<=$last;$date=$date->modify('+1 day')){
            $day=$date->format('Y-m-d');
            $rate=(int)$periods[0]['old_monthly_irr'];
            foreach($periods as $period){
                if((string)$period['effective_date']>$day)break;
                $rate=(int)$period['new_monthly_irr'];
            }
            $sum+=$rate/$days;
        }
        return (int)round($sum);
    }

    public static function splitRates(string $start,string $end,string $effectiveDate,
        int $oldMonthly,int $newMonthly): array
    {
        foreach([[$start,'شروع دوره'],[$end,'پایان دوره'],[$effectiveDate,'تاریخ تغییر']] as [$date,$name])
            self::requireDate($date,$name);
        if($start>$end||$oldMonthly<0||$newMonthly<0)
            throw new AuthException(422,'بازه یا مبلغ دوره نامعتبر است.','INVALID_RATE_PERIOD');
        $cursor=new DateTimeImmutable($start.'T12:00:00+00:00');
        $last=new DateTimeImmutable($end.'T12:00:00+00:00');
        $oldDays=0;$newDays=0;$weighted=0.0;
        $totalDays=(int)$cursor->diff($last)->days+1;
        if($totalDays>31)throw new AuthException(422,'دوره محاسبه باید حداکثر یک ماه باشد.','INVALID_RATE_PERIOD');
        while($cursor<=$last){
            $day=$cursor->format('Y-m-d');
            $denominator=$totalDays;
            if($day<$effectiveDate){$oldDays++;$weighted+=$oldMonthly/$denominator;}
            else{$newDays++;$weighted+=$newMonthly/$denominator;}
            $cursor=$cursor->modify('+1 day');
        }
        return [
            'startDate'=>$start,'endDate'=>$end,'effectiveDate'=>$effectiveDate,
            'oldDays'=>$oldDays,'newDays'=>$newDays,'totalDays'=>$totalDays,
            'oldMonthlyIrr'=>$oldMonthly,'newMonthlyIrr'=>$newMonthly,
            'amountIrr'=>(int)round($weighted),
        ];
    }
}

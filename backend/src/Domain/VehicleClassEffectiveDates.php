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
        $contract=self::requireDate($body['contractEffectiveDate']??null,'تاریخ اعمال تغییر کلاس در قرارداد');
        $debt=self::boolChoice($body,'updateStudentDebt');
        $pay=self::boolChoice($body,'updateDriverPay');
        return [
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
     * Prorate a continuous ISO date period [start,end] with old/new monthly
     * rates. Denominator equals the ACTUAL days in each Gregorian calendar
     * segment; month boundaries are handled independently and rounding occurs
     * only on the final total, preventing fractional-rial drift.
     * For Persian monthly statements use splitRates with exact Jalali month
     * boundaries supplied by that statement's date converter.
     */
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
        while($cursor<=$last){
            $day=$cursor->format('Y-m-d');
            $denominator=(int)$cursor->format('t');
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

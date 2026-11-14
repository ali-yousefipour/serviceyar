<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;

/** Shared tuition and driver-base resolver. All money amounts are monthly IRR. */
final class ServicePricing {
    public const SOURCES=['taxi','agency','agreed'];

    public static function source(mixed $raw): string {
        $mode=(string)($raw??'taxi');
        if(!in_array($mode,self::SOURCES,true))throw new AuthException(422,'روش محاسبه هزینه باید تاکسیرانی، تعرفه شرکت یا توافقی باشد.','INVALID_FEE_SOURCE');
        return $mode;
    }
    public static function quote(PDO $pdo,int $aid,array $student,string $source): array {
        $source=self::source($source);
        $school=(int)($student['school_id']??0);
        $class=$student['tariff_vehicle_class']??null;
        $distance=$student['tariff_distance_km']??null;
        $year=(string)(($student['academic_year']??null)?:AgencySchoolTariffs::YEAR);
        if($source==='agreed'){
            $amount=$student['monthly_fee']??null;
            if(!is_numeric($amount)||!is_finite((float)$amount)||(float)$amount<0||(float)$amount>999999999999)
                throw new AuthException(422,'برای مبلغ توافقی، هزینه ماهانه معتبر را وارد کنید.','AGREED_FEE_REQUIRED');
            $fee=(int)round((float)$amount);
            return ['monthlyFee'=>$fee,'driverBaseMonthly'=>$fee,'feeSource'=>$source,'currency'=>'IRR'];
        }
        if($school<1||$class===null||$class===''||$distance===null||$distance==='')
            throw new AuthException(422,'برای محاسبه تعرفه، مدرسه، کلاس خودرو و مسافت الزامی هستند.','TARIFF_INPUT_REQUIRED');
        if($source==='taxi'){
            $quote=TaxiOrganizationTariffs::quoteStudent($pdo,$aid,$school,$class,$distance);
            return ['monthlyFee'=>$quote['parentMonthly'],'driverBaseMonthly'=>$quote['driverMonthly'],
                'feeSource'=>$source,'distanceAppliedKm'=>$quote['distanceKm'],'currency'=>'IRR'];
        }
        $fee=AgencySchoolTariffs::quote($pdo,$aid,$school,$year,$distance,$class);
        if($fee===null)throw new AuthException(422,'برای کلاس خودرو، ناحیه و مسافت انتخاب‌شده، تعرفه شرکت ثبت نشده است.','AGENCY_RATE_NOT_FOUND');
        // Until an agency driver-only schedule exists, its agreed student amount
        // is the payout calculation base; do not pretend it came from the taxi schedule.
        return ['monthlyFee'=>$fee,'driverBaseMonthly'=>$fee,'feeSource'=>$source,'currency'=>'IRR'];
    }
    public static function updateStudent(PDO $pdo,int $aid,int $studentId,string $source): array {
        $q=$pdo->prepare('SELECT id,school_id,academic_year,tariff_distance_km,tariff_vehicle_class,monthly_fee FROM students WHERE id=? AND agency_id=? AND is_active=1 FOR UPDATE');
        $q->execute([$studentId,$aid]);$student=$q->fetch();
        if(!$student)throw new AuthException(404,'دانش‌آموز یافت نشد.','NOT_FOUND');
        $quote=self::quote($pdo,$aid,$student,$source);
        $up=$pdo->prepare('UPDATE students SET fee_source=?,monthly_fee=?,driver_base_monthly=?,tariff_calculated_at=NOW() WHERE id=? AND agency_id=?');
        $up->execute([$source,$quote['monthlyFee'],$quote['driverBaseMonthly'],$studentId,$aid]);
        return $quote;
    }
}

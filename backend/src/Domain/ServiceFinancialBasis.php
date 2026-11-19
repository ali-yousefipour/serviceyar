<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;

/**
 * Single financial basis for student tuition, service entitlement and payroll.
 * Parent monthly fees are the gross contractual base. Apply agency deductions
 * exactly once, after summing the fees. All amounts are whole Iranian rials.
 */
final class ServiceFinancialBasis
{
    public static function service(PDO $pdo,int $agencyId,int $serviceId): array
    {
        $stmt=$pdo->prepare("SELECT sv.id,sv.fee_source,sv.driver_id,v.tariff_vehicle_class
            FROM services sv LEFT JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=sv.agency_id
            LEFT JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=sv.agency_id AND v.is_active=1
            WHERE sv.agency_id=? AND sv.id=?");
        $stmt->execute([$agencyId,$serviceId]);
        $service=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$service)throw new AuthException(404,'سرویس یافت نشد.','NOT_FOUND');
        return $service;
    }

    public static function quoteStudent(PDO $pdo,int $agencyId,array $student,string $source,int $vehicleClass): array
    {
        if(!in_array($vehicleClass,[1,2,3],true))
            throw new AuthException(422,'کلاس خودروی راننده معتبر نیست.','VEHICLE_TARIFF_CLASS_REQUIRED');
        $student['tariff_vehicle_class']=$vehicleClass;
        return ServicePricing::quote($pdo,$agencyId,$student,$source);
    }

    public static function grossOfService(PDO $pdo,int $agencyId,int $serviceId): int
    {
        $stmt=$pdo->prepare("SELECT COALESCE(SUM(st.monthly_fee),0)
            FROM service_students ss JOIN students st ON st.id=ss.student_id
            JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=st.agency_id
            WHERE ss.service_id=? AND sv.agency_id=? AND st.is_active=1");
        $stmt->execute([$serviceId,$agencyId]);
        return (int)round((float)$stmt->fetchColumn());
    }

    public static function netOfGross(int $gross,int $agencyId): array
    {
        return OperationsService::driverDeductions((float)$gross,$agencyId);
    }

    public static function prorate(int $monthly,int $days): int
    {
        if($days<0||$days>30)throw new AuthException(422,'روزهای خدمت باید بین صفر تا ۳۰ باشد.','INVALID_SERVICE_DAYS');
        return (int)round($monthly*$days/30);
    }

    private static function normalizeEducation(string $value): string
    {
        return trim(strtr(strtolower($value),['ي'=>'ی','ك'=>'ک','‌'=>' ','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
    }

    public static function monthsOfStudent(array $student): float
    {
        $grade=self::normalizeEducation((string)($student['grade']??''));
        $levels=$student['education_levels']??'';
        $level=self::normalizeEducation(is_array($levels)?implode(' ',array_map('strval',$levels)):(string)$levels);
        $legacy=self::normalizeEducation((string)($student['school_type']??''));
        $primary='/ابتدایی|دبستان|primary|elementary/u';
        $secondary='/متوسطه|دبیرستان|راهنمایی|secondary|high.school|middle.school/u';
        // Explicit student education stage wins; a generic grade such as "اول"
        // is not sufficient to distinguish first grade from first-year secondary.
        $p=(bool)preg_match($primary,$grade);$s=(bool)preg_match($secondary,$grade);
        if($p!==$s)return $p?8.5:9.0;
        foreach([$level,$legacy] as $school){
            $p=(bool)preg_match($primary,$school);$s=(bool)preg_match($secondary,$school);
            if($p!==$s)return $p?8.5:9.0;
            if($p&&$s)break;
        }
        if(preg_match('/^(?:پایه\s*)?(1[0-2]|[1-9])$/u',$grade,$m))return (int)$m[1]<=6?8.5:9.0;
        if(preg_match('/^(?:پایه\s*)?(هفتم|هشتم|نهم|دهم|یازدهم|دوازدهم)$/u',$grade))return 9.0;
        if(preg_match('/^(?:پایه\s*)?(چهارم|پنجم|ششم)$/u',$grade))return 8.5;
        throw new AuthException(422,'مقطع تحصیلی دانش‌آموز مشخص نیست؛ پایه و مقطع مدرسه را اصلاح کنید.','EDUCATION_LEVEL_REQUIRED');
    }
    /** SQL equivalent of stage resolution for bulk reports; NULL means review required.
     * Arguments are trusted internal SQL expressions, never request parameters. */
    public static function monthsSql(string $grade,string $levels,string $legacy): string
    {
        $norm=static function(string $x): string {
            $x="LOWER(TRIM(COALESCE($x,'')))";
            foreach(['ي'=>'ی','ك'=>'ک','‌'=>' ','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'] as $a=>$b)$x="REPLACE($x,'$a','$b')";
            return $x;
        };
        $g=$norm($grade);$l=$norm($levels);$t=$norm($legacy);
        $p="'ابتدایی|دبستان|primary|elementary'";$s="'متوسطه|دبیرستان|راهنمایی|secondary|high.school|middle.school'";
        $cases="WHEN $g REGEXP $p AND NOT ($g REGEXP $s) THEN 8.5 WHEN $g REGEXP $s AND NOT ($g REGEXP $p) THEN 9 ";
        $cases.="WHEN $l REGEXP $p AND NOT ($l REGEXP $s) THEN 8.5 WHEN $l REGEXP $s AND NOT ($l REGEXP $p) THEN 9 ";
        $cases.="WHEN NOT ($l REGEXP $p AND $l REGEXP $s) AND $t REGEXP $p AND NOT ($t REGEXP $s) THEN 8.5 WHEN NOT ($l REGEXP $p AND $l REGEXP $s) AND $t REGEXP $s AND NOT ($t REGEXP $p) THEN 9 ";
        return "(CASE $cases WHEN $g REGEXP '^(پایه[[:space:]]*)?([1-6]|چهارم|پنجم|ششم)$' THEN 8.5 WHEN $g REGEXP '^(پایه[[:space:]]*)?(7|8|9|10|11|12|هفتم|هشتم|نهم|دهم|یازدهم|دوازدهم)$' THEN 9 ELSE NULL END)";
    }

}

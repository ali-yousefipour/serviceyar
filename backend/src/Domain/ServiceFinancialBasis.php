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

    public static function monthsOfStudent(array $student): float
    {
        $grade=(string)($student['grade']??'');
        $schoolType=(string)($student['school_type']??'');
        $primary=preg_match('/ابتدایی|دبستان|primary|elementary/ui',$schoolType)
            ||(!preg_match('/متوسطه|دبیرستان|secondary|high.school/ui',$schoolType)
                && (preg_match('/ابتدایی|دبستان|primary|elementary/ui',$grade)
                    ||in_array($grade,['1','2','3','4','5','6','۱','۲','۳','۴','۵','۶'],true)));
        return $primary?8.5:9.0;
    }
}

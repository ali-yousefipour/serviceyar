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
    /** Apply an explicitly selected, approved vehicle family/class from a
     * service editor to the driver's actual registered vehicle. This is part
     * of the same database transaction as the service assignment.
     */
    public static function setDriverVehicleType(PDO $pdo,int $agencyId,int $driverId,array $body): void {
        if(!array_key_exists('vehicleModel',$body)&&!array_key_exists('vehicleClass',$body))return;
        $model=trim((string)($body['vehicleModel']??''));
        $class=(string)($body['vehicleClass']??'');
        if($model===''||!in_array($class,['1','2','3'],true))
            throw new AuthException(422,'نوع خودرو و کلاس تعرفه را از فهرست انتخاب و بررسی کنید.','VEHICLE_SELECTION_REQUIRED');
        if(!VehicleClassCatalog::validSelection($pdo,$model))
            throw new AuthException(422,'این نوع خودرو در فهرست تأییدشده تعریف نشده است. ابتدا از تنظیمات ادمین کل مدل را اضافه کنید.','VEHICLE_MODEL_NOT_APPROVED');
        $q=$pdo->prepare("SELECT v.id,v.tariff_vehicle_class FROM drivers d
          JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=d.agency_id AND v.is_active=1
          WHERE d.id=? AND d.agency_id=? AND d.is_active=1 FOR UPDATE");
        $q->execute([$driverId,$agencyId]);$vehicle=$q->fetch(PDO::FETCH_ASSOC);
        if(!$vehicle)throw new AuthException(422,'برای راننده انتخاب‌شده خودروی فعال و معتبر ثبت نشده است.','DRIVER_VEHICLE_REQUIRED');
        $previous=(string)($vehicle['tariff_vehicle_class']??'');
        $pdo->prepare('UPDATE vehicles SET model=?,vehicle_trim=?,tariff_vehicle_class=? WHERE id=? AND agency_id=?')
            ->execute([$model,$model,(int)$class,(int)$vehicle['id'],$agencyId]);
        if($previous!==$class){
            $linked=$pdo->prepare("SELECT id,fee_source FROM services WHERE agency_id=? AND driver_id=? AND is_active=1");
            $linked->execute([$agencyId,$driverId]);
            foreach($linked->fetchAll(PDO::FETCH_ASSOC) as $service)
                self::synchronizeServiceVehicle($pdo,$agencyId,(int)$service['id'],(string)$service['fee_source']);
        }
    }

    /**
     * Vehicle data, not student input, determines the tariff class.
     * Called inside an existing transaction when a service is saved or its
     * driver changes. Missing class fails with an actionable validation error.
     */
    public static function synchronizeServiceVehicle(PDO $pdo,int $agencyId,int $serviceId,string $source,bool $requireClass=true,bool $reprice=true): array {
        $source=self::source($source);
        $q=$pdo->prepare("SELECT sv.driver_id,sv.code,d.vehicle_id,v.model,
            v.tariff_vehicle_class
            FROM services sv
            LEFT JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=sv.agency_id
            LEFT JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=sv.agency_id AND v.is_active=1
            WHERE sv.id=? AND sv.agency_id=? FOR UPDATE");
        $q->execute([$serviceId,$agencyId]);$service=$q->fetch(PDO::FETCH_ASSOC);
        if(!$service)throw new AuthException(404,'سرویس پیدا نشد.','NOT_FOUND');
        $members=$pdo->prepare("SELECT st.id FROM service_students ss
            JOIN students st ON st.id=ss.student_id AND st.agency_id=?
            WHERE ss.service_id=? ORDER BY st.id FOR UPDATE");
        $members->execute([$agencyId,$serviceId]);
        $ids=array_map('intval',$members->fetchAll(PDO::FETCH_COLUMN));
        if(!$ids)return ['updatedStudents'=>0,'vehicleClass'=>$service['tariff_vehicle_class']??null];
        $class=(string)($service['tariff_vehicle_class']??'');
        if(!in_array($class,['1','2','3'],true)) {
            // Unrelated edits of old services must stay possible until their
            // vehicle is classified. Explicit driver/source changes and new
            // services always require an authoritative vehicle class.
            if(!$requireClass)return ['updatedStudents'=>0,'vehicleClass'=>null,'requiresVehicleClass'=>true];
            throw new AuthException(422,
             'کلاس تعرفه خودروی راننده برای این سرویس مشخص نیست. ابتدا در قسمت خودروها، کلاس تعرفه خودرو را ۱، ۲ یا ۳ تعیین کنید.',
             'VEHICLE_TARIFF_CLASS_REQUIRED');
        }
        $set=$pdo->prepare('UPDATE students SET tariff_vehicle_class=? WHERE id=? AND agency_id=?');
        $updated=0;
        foreach($ids as $studentId){
            $set->execute([(int)$class,$studentId,$agencyId]);
            if($reprice && $source!=='agreed')self::updateStudent($pdo,$agencyId,$studentId,$source);
            if($reprice)StudentServiceBilling::sync($pdo,$agencyId,$studentId);
            $updated++;
        }
        $gross=$pdo->prepare('SELECT COALESCE(SUM(COALESCE(st.driver_base_monthly,st.monthly_fee)),0) FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=? WHERE ss.service_id=?');
        $gross->execute([$agencyId,$serviceId]);
        $net=OperationsService::driverDeductions((float)$gross->fetchColumn(),$agencyId)['net_earned'];
        if($reprice && $source!=='agreed')$pdo->prepare('UPDATE services SET monthly_fee=? WHERE id=? AND agency_id=?')
            ->execute([$net,$serviceId,$agencyId]);
        return ['updatedStudents'=>$updated,'vehicleClass'=>(int)$class];
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

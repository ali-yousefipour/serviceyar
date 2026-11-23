<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Database\Connection;
use ServiceYar\Auth\AuthException;

/**
 * Apply approved tuition/payroll rates only when their effective date arrives.
 * The operation is idempotent; every rate period is applied exactly once and
 * previously issued contracts, invoices and payments remain unchanged.
 */
final class VehicleClassRateApplication
{
    public static function applyDue(int $agencyId): int
    {
        if(!VehicleClassSchema::exists())return 0;
        $pdo=Connection::get();
        // Only active, still-linked assignments may update current student
        // caches. Orphaned periods remain pending for explicit reconciliation,
        // but must never take the whole company's API offline.
        $eligible="EXISTS(SELECT 1 FROM service_students ss
              JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=p.agency_id
              JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
              WHERE ss.service_id=p.service_id AND ss.student_id=p.subject_id
                AND st.is_active=1 AND sv.is_active=1 AND sv.status<>'cancelled')";
        $probe=$pdo->prepare("SELECT p.id FROM vehicle_class_rate_periods p
          WHERE p.agency_id=? AND p.applied_at IS NULL
            AND p.effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE)
            AND $eligible LIMIT 1");
        $probe->execute([$agencyId]);
        if(!$probe->fetchColumn())return 0;
        return OperationsService::transaction(function(PDO $pdo)use($agencyId,$eligible){
            $count=0;$services=[];
            do {
                $q=$pdo->prepare("SELECT p.id,p.subject_type,p.subject_id,p.service_id,
                      p.new_class,p.new_monthly_irr
                      FROM vehicle_class_rate_periods p
                      WHERE p.agency_id=? AND p.applied_at IS NULL AND p.effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE)
                        AND $eligible
                      ORDER BY p.effective_date,p.id LIMIT 500 FOR UPDATE");
                $q->execute([$agencyId]);$pending=$q->fetchAll(PDO::FETCH_ASSOC);
                $assignment=$pdo->prepare("SELECT 1 FROM service_students ss
                    JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=?
                    JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
                    WHERE ss.service_id=? AND ss.student_id=?
                      AND sv.is_active=1 AND sv.status<>'cancelled'
                      AND st.is_active=1 LIMIT 1");
                foreach($pending as $p){
                    // A future-dated adjustment must never overwrite a student
                    // who has since moved to an unrelated service or company.
                    $assignment->execute([$agencyId,$p['service_id'],$p['subject_id']]);
                    if(!$assignment->fetchColumn())
                        continue; // A concurrent transfer is reconciled, never repriced here.
                    if($p['subject_type']==='student_debt'){
                        $pdo->prepare("UPDATE students SET monthly_fee=?,tariff_vehicle_class=?
                            WHERE id=? AND agency_id=?")
                            ->execute([$p['new_monthly_irr'],$p['new_class'],$p['subject_id'],$agencyId]);
                    }else{
                        $pdo->prepare("UPDATE students SET driver_base_monthly=?
                            WHERE id=? AND agency_id=?")
                            ->execute([$p['new_monthly_irr'],$p['subject_id'],$agencyId]);
                        $services[(int)$p['service_id']]=true;
                    }
                    $pdo->prepare('UPDATE vehicle_class_rate_periods SET applied_at=NOW() WHERE id=? AND agency_id=? AND applied_at IS NULL')
                        ->execute([$p['id'],$agencyId]);
                    $count++;
                }
            }while(count($pending)===500);
            foreach(array_keys($services) as $serviceId){
                $q=$pdo->prepare("SELECT COALESCE(SUM(st.driver_base_monthly),0) AS gross,
                     SUM(CASE WHEN st.driver_base_monthly IS NULL OR st.driver_base_monthly<0 THEN 1 ELSE 0 END) AS invalid_driver_rates
                     FROM service_students ss
                     JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=?
                     JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id AND st.is_active=1
                     WHERE ss.service_id=?");
                $q->execute([$agencyId,$serviceId]);$result=$q->fetch(PDO::FETCH_ASSOC);
                if((int)($result['invalid_driver_rates']??0)>0)
                    throw new AuthException(409,'مبنای حقوق برخی دانش‌آموزان ثبت نشده است؛ بدون اصلاح داده‌های پایه، مبلغ سرویس به‌روزرسانی نمی‌شود.','DRIVER_BASE_MONTHLY_REQUIRED');
                $gross=(float)($result['gross']??0);
                $net=OperationsService::driverDeductions($gross,$agencyId)['net_earned'];
                $pdo->prepare('UPDATE services SET monthly_fee=? WHERE id=? AND agency_id=?')
                    ->execute([$net,$serviceId,$agencyId]);
            }
            return $count;
        });
    }
}

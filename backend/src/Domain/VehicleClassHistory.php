<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Tenant-isolated read-only audit report for vehicle class changes. */
final class VehicleClassHistory
{
    private static function connection(int $uid,int $agencyId): PDO {
        Authorization::requireAgencyAccess($uid,$agencyId);
        Authorization::requirePermission($uid,'drivers.view');
        VehicleClassSchema::ensure();
        return Connection::get();
    }

    public static function list(int $uid,int $agencyId,?int $vehicleId=null,array $filters=[]): array {
        if($vehicleId!==null&&$vehicleId<1)
            throw new AuthException(422,'شناسه خودرو نامعتبر است.','INVALID_VEHICLE');
        $pdo=self::connection($uid,$agencyId);
        $sql="SELECT ch.id,ch.vehicle_id,ch.driver_id,ch.old_vehicle_model,ch.new_vehicle_model,
          ch.old_vehicle_class,ch.new_vehicle_class,ch.contract_effective_date,
          ch.update_student_debt,ch.student_debt_effective_date,ch.update_driver_pay,
          ch.driver_pay_effective_date,ch.created_at,ch.created_by,
          CONCAT_WS(' ',u.first_name,u.last_name) AS changed_by,
          JSON_UNQUOTE(JSON_EXTRACT(ch.old_vehicle_snapshot,'$.plate')) AS old_plate,
          JSON_UNQUOTE(JSON_EXTRACT(ch.new_vehicle_snapshot,'$.plate')) AS new_plate,
          (SELECT COUNT(*) FROM vehicle_class_contract_appendices a WHERE a.agency_id=ch.agency_id AND a.change_id=ch.id) AS amended_contract_count,
          (SELECT COUNT(*) FROM vehicle_class_rate_periods p WHERE p.agency_id=ch.agency_id AND p.change_id=ch.id AND p.subject_type='student_debt') AS adjusted_student_count,
          (SELECT COALESCE(SUM(a.amount_irr),0) FROM vehicle_class_debt_adjustments a WHERE a.agency_id=ch.agency_id AND a.change_id=ch.id) AS total_debt_adjustment,
          CONCAT_WS(' ',d.first_name,d.last_name) AS driver_name,
          v.plate AS current_plate
          FROM vehicle_class_changes ch
          LEFT JOIN drivers d ON d.id=ch.driver_id AND d.agency_id=ch.agency_id
          LEFT JOIN vehicles v ON v.id=ch.vehicle_id AND v.agency_id=ch.agency_id
          LEFT JOIN users u ON u.id=ch.created_by
          WHERE ch.agency_id=?";
        $params=[$agencyId];
        if($vehicleId!==null){$sql.=' AND ch.vehicle_id=?';$params[]=$vehicleId;}
        foreach(['oldClass'=>'old_vehicle_class','newClass'=>'new_vehicle_class'] as $key=>$column){
            if(!isset($filters[$key])||$filters[$key]==='')continue;
            if(!in_array((string)$filters[$key],['1','2','3'],true))
                throw new AuthException(422,'فیلتر کلاس خودرو معتبر نیست.','INVALID_CLASS_FILTER');
            $sql.=' AND ch.'.$column.'=?';$params[]=(int)$filters[$key];
        }
        foreach(['from'=>'>=','to'=>'<='] as $key=>$operator){
            $date=(string)($filters[$key]??'');
            if($date==='')continue;
            VehicleClassEffectiveDates::requireDate($date,'تاریخ گزارش');
            $sql.=' AND DATE(ch.created_at)'.$operator.'?';$params[]=$date;
        }
        $search=trim((string)($filters['search']??''));
        if($search!==''){
            if(mb_strlen($search)>100)throw new AuthException(422,'متن جستجو طولانی است.','SEARCH_TOO_LONG');
            $sql.=" AND (CONCAT_WS(' ',d.first_name,d.last_name) LIKE ?
              OR v.plate LIKE ?
              OR JSON_UNQUOTE(JSON_EXTRACT(ch.old_vehicle_snapshot,'$.plate')) LIKE ?
              OR JSON_UNQUOTE(JSON_EXTRACT(ch.new_vehicle_snapshot,'$.plate')) LIKE ?
              OR CAST(ch.id AS CHAR) = ?)";
            $like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
            array_push($params,$like,$like,$like,$like,$search);
        }
        $sql.=' ORDER BY ch.id DESC LIMIT 1000';
        $q=$pdo->prepare($sql);$q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function detail(int $uid,int $agencyId,int $changeId): array {
        if($changeId<1)throw new AuthException(422,'شناسه تغییر معتبر نیست.','INVALID_CHANGE_ID');
        $pdo=self::connection($uid,$agencyId);
        $q=$pdo->prepare("SELECT ch.*,CONCAT_WS(' ',d.first_name,d.last_name) AS driver_name,
                v.plate AS vehicle_plate
            FROM vehicle_class_changes ch
            LEFT JOIN drivers d ON d.id=ch.driver_id AND d.agency_id=ch.agency_id
            LEFT JOIN vehicles v ON v.id=ch.vehicle_id AND v.agency_id=ch.agency_id
            WHERE ch.id=? AND ch.agency_id=?");
        $q->execute([$changeId,$agencyId]);$change=$q->fetch(PDO::FETCH_ASSOC);
        if(!$change)throw new AuthException(404,'این تغییر برای شرکت جاری یافت نشد.','CHANGE_NOT_FOUND');
        $q=$pdo->prepare("SELECT a.id,a.student_id,a.service_id,a.academic_year,a.effective_date,a.amount_irr,
              CONCAT_WS(' ',s.first_name,s.last_name) AS student_name
            FROM vehicle_class_debt_adjustments a
            JOIN students s ON s.id=a.student_id AND s.agency_id=a.agency_id
            WHERE a.agency_id=? AND a.change_id=? ORDER BY s.last_name,s.first_name,a.id");
        $q->execute([$agencyId,$changeId]);$adjustments=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT a.id,a.contract_id,a.effective_date,c.contract_no,c.party_type,c.party_id
            FROM vehicle_class_contract_appendices a
            JOIN contracts c ON c.id=a.contract_id AND c.agency_id=a.agency_id
            WHERE a.agency_id=? AND a.change_id=? ORDER BY a.id");
        $q->execute([$agencyId,$changeId]);
        $contracts=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT p.id,p.subject_type,p.subject_id,p.service_id,p.effective_date,
                 p.old_monthly_irr,p.new_monthly_irr,p.applied_at,
                 CASE
                   WHEN p.applied_at IS NOT NULL THEN 'applied'
                   WHEN p.effective_date>DATE(UTC_TIMESTAMP()+INTERVAL 210 MINUTE) THEN 'scheduled'
                   WHEN NOT EXISTS (
                     SELECT 1 FROM service_students ss
                     JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=p.agency_id
                     JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
                     JOIN vehicle_class_changes change_owner ON change_owner.id=p.change_id
                       AND change_owner.agency_id=p.agency_id
                     WHERE ss.service_id=p.service_id AND ss.student_id=p.subject_id
                       AND sv.driver_id=change_owner.driver_id
                       AND ((p.subject_type='student_debt'
                             AND change_owner.update_student_debt=1
                             AND st.monthly_fee=p.old_monthly_irr)
                         OR (p.subject_type='driver_pay'
                             AND change_owner.update_driver_pay=1
                             AND st.driver_base_monthly=p.old_monthly_irr))
                       AND sv.is_active=1 AND sv.status='active' AND st.is_active=1
                   ) THEN 'needs_review'
                   ELSE 'pending'
                 END AS application_status
            FROM vehicle_class_rate_periods p
            WHERE p.agency_id=? AND p.change_id=? ORDER BY p.subject_type,p.id");
        $q->execute([$agencyId,$changeId]);
        return ['change'=>$change,'adjustments'=>$adjustments,'contracts'=>$contracts,'rates'=>$q->fetchAll(PDO::FETCH_ASSOC)];
    }
}

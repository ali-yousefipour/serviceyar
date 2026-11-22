<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Read-only, company-scoped history of consented vehicle class changes. */
final class VehicleClassHistory
{
    public static function list(int $uid,int $agencyId,?int $vehicleId=null): array
    {
        Authorization::requireAgencyAccess($uid,$agencyId);
        Authorization::requirePermission($uid,'drivers.view');
        if($vehicleId!==null&&$vehicleId<1)
            throw new AuthException(422,'شناسه خودرو نامعتبر است.','INVALID_VEHICLE');
        VehicleClassSchema::ensure();
        $pdo=Connection::get();
        try {
            $pdo->query('SELECT id FROM vehicle_class_changes LIMIT 0');
        } catch(\PDOException $e) {
            if(($e->errorInfo[1]??null)===1146)
                throw new AuthException(503,'مهاجرت تاریخچه تغییر کلاس خودرو (0168) اجرا نشده است.','VEHICLE_CLASS_HISTORY_SCHEMA_REQUIRED');
            throw $e;
        }
        $sql="SELECT ch.id,ch.vehicle_id,ch.driver_id,ch.old_vehicle_model,ch.new_vehicle_model,
          ch.old_vehicle_class,ch.new_vehicle_class,ch.contract_effective_date,
          ch.update_student_debt,ch.student_debt_effective_date,ch.update_driver_pay,
          ch.driver_pay_effective_date,ch.created_at,
          CONCAT_WS(' ',d.first_name,d.last_name) AS driver_name,
          v.plate AS current_plate
          FROM vehicle_class_changes ch
          LEFT JOIN drivers d ON d.id=ch.driver_id AND d.agency_id=ch.agency_id
          LEFT JOIN vehicles v ON v.id=ch.vehicle_id AND v.agency_id=ch.agency_id
          WHERE ch.agency_id=?";
        $params=[$agencyId];
        if($vehicleId!==null){$sql.=' AND ch.vehicle_id=?';$params[]=$vehicleId;}
        $sql.=' ORDER BY ch.id DESC LIMIT 200';
        $q=$pdo->prepare($sql);$q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}

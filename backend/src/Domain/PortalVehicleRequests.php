<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Company approvals for driver-submitted vehicle edits. */
final class PortalVehicleRequests {
    private static function guard(int $uid,int $agencyId): PDO {
        Authorization::requirePermission($uid,'drivers.manage');
        Authorization::requireAgencyAccess($uid,$agencyId);
        return Connection::get();
    }
    public static function list(int $uid,int $agencyId): array {
        $pdo=self::guard($uid,$agencyId);
        $q=$pdo->prepare("SELECT req.id,req.created_at,req.status,
            req.requested_model,req.requested_plate,req.note,
            req.driver_id,d.first_name,d.last_name,
            v.plate current_plate,v.model current_model,
            u.username requested_by
            FROM portal_vehicle_change_requests req
            JOIN drivers d ON d.id=req.driver_id AND d.agency_id=req.agency_id
            JOIN vehicles v ON v.id=req.vehicle_id AND v.agency_id=req.agency_id
            JOIN users u ON u.id=req.user_id
            WHERE req.agency_id=? ORDER BY
                CASE WHEN req.status='pending' THEN 0 ELSE 1 END,req.id DESC LIMIT 200");
        $q->execute([$agencyId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function decide(int $uid,int $agencyId,int $id,string $action): array {
        $pdo=self::guard($uid,$agencyId);
        if(!in_array($action,['approve','reject'],true)||$id<1)
            throw new AuthException(422,'تصمیم نامعتبر است.','INVALID_DECISION');
        $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try {
            $q=$pdo->prepare("SELECT req.*,d.vehicle_id currently_assigned
              FROM portal_vehicle_change_requests req
              JOIN drivers d ON d.id=req.driver_id AND d.agency_id=req.agency_id AND d.is_active=1
              WHERE req.id=? AND req.agency_id=? FOR UPDATE");
            $q->execute([$id,$agencyId]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new AuthException(404,'درخواست تغییر خودرو یافت نشد.','NOT_FOUND');
            if($row['status']!=='pending')throw new AuthException(409,'این درخواست قبلاً بررسی شده است.','ALREADY_REVIEWED');
            if((int)$row['currently_assigned']!==(int)$row['vehicle_id'])
                throw new AuthException(409,'خودروی راننده تغییر کرده است؛ درخواست قدیمی قابل اعمال نیست.','VEHICLE_CHANGED');
            if($action==='approve') {
                $plate=trim((string)($row['requested_plate']??''));
                $model=trim((string)($row['requested_model']??''));
                if($model!==''&&!VehicleClassCatalog::validSelection($pdo,$model))
                    throw new AuthException(422,'مدل در فهرست خودروهای تأییدشده وجود ندارد.','MODEL_NOT_APPROVED');
                if($plate!==''&&(mb_strlen($plate)<5||mb_strlen($plate)>50))
                    throw new AuthException(422,'پلاک درخواست‌شده معتبر نیست.','INVALID_PLATE');
                $update=$pdo->prepare('UPDATE vehicles SET model=COALESCE(?,model),plate=COALESCE(?,plate)
                  WHERE id=? AND agency_id=? AND is_active=1');
                $update->execute([$model?:null,$plate?:null,(int)$row['vehicle_id'],$agencyId]);
                if($update->rowCount()===0){
                    $q=$pdo->prepare('SELECT 1 FROM vehicles WHERE id=? AND agency_id=? AND is_active=1');
                    $q->execute([(int)$row['vehicle_id'],$agencyId]);
                    if(!$q->fetchColumn())throw new AuthException(409,'خودرو دیگر فعال نیست.','VEHICLE_INACTIVE');
                }
            }
            $newStatus=$action==='approve'?'approved':'rejected';
            $pdo->prepare('UPDATE portal_vehicle_change_requests SET status=? WHERE id=? AND agency_id=?')
                ->execute([$newStatus,$id,$agencyId]);
            $pdo->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id)
                 VALUES(?,?,?,?,?)')->execute([$agencyId,$uid,'portal_vehicle_'.$newStatus,'vehicles',(int)$row['vehicle_id']]);
            if($owns)$pdo->commit();
            return ['id'=>$id,'status'=>$newStatus,'vehicleId'=>(int)$row['vehicle_id']];
        }catch(\Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
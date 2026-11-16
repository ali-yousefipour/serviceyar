<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/**
 * Preview and explicitly apply the official Taxi Organization tariff to a
 * service, or all active services assigned to a driver.
 *
 * A quote never mutates data. Apply is atomic, re-quotes locked student rows,
 * synchronizes parent invoices through the existing billing policy, and
 * preserves posted payments and finalized financial documents.
 */
final class TaxiTariffApplication
{
    private static function services(PDO $pdo,int $aid,string $kind,int $id): array
    {
        if($id<1||!in_array($kind,['service','driver'],true))
            throw new AuthException(422,'شناسه سرویس یا راننده معتبر نیست.','INVALID_TARIFF_TARGET');
        if($kind==='service'){
            $q=$pdo->prepare('SELECT sv.id,sv.title,sv.fee_source,sv.status,sv.is_active,
               sv.driver_id,v.tariff_vehicle_class,sv.monthly_fee
               FROM services sv LEFT JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=sv.agency_id
               LEFT JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=sv.agency_id AND v.is_active=1
               WHERE sv.agency_id=? AND sv.id=?');
            $q->execute([$aid,$id]);
        }else{
            $q=$pdo->prepare("SELECT sv.id,sv.title,sv.fee_source,sv.status,sv.is_active,
               sv.driver_id,v.tariff_vehicle_class,sv.monthly_fee
               FROM services sv JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=sv.agency_id AND d.is_active=1
               LEFT JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=sv.agency_id AND v.is_active=1
               WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1
               AND sv.status<>'cancelled' ORDER BY sv.id");
            $q->execute([$aid,$id]);
        }
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows){
            if($kind==='service')throw new AuthException(404,'سرویس در شرکت انتخابی یافت نشد.','NOT_FOUND');
            $driver=$pdo->prepare('SELECT 1 FROM drivers WHERE agency_id=? AND id=? AND is_active=1');
            $driver->execute([$aid,$id]);
            if(!$driver->fetchColumn())throw new AuthException(404,'راننده فعال یافت نشد.','NOT_FOUND');
        }
        return $rows;
    }

    private static function previewFor(PDO $pdo,int $aid,array $services): array
    {
        $members=$pdo->prepare("SELECT st.id,st.first_name,st.last_name,st.school_id,
           st.academic_year,st.tariff_distance_km,st.tariff_vehicle_class,
           st.monthly_fee,st.driver_base_monthly,st.fee_source
           FROM service_students ss JOIN students st ON st.id=ss.student_id
                AND st.agency_id=? AND st.is_active=1
           WHERE ss.service_id=? ORDER BY st.id");
        $rows=[];$blocking=0;$students=0;$changes=0;
        foreach($services as $sv){
            $entry=['serviceId'=>(int)$sv['id'],'serviceTitle'=>$sv['title'],
              'oldSource'=>$sv['fee_source'],'oldServiceFee'=>(int)($sv['monthly_fee']??0),
              'studentCount'=>0,'students'=>[],'errors'=>[],'changeCount'=>0,
              'vehicleClass'=>$sv['tariff_vehicle_class']===null?null:(int)$sv['tariff_vehicle_class']];
            if((int)$sv['is_active']!==1||$sv['status']==='cancelled'){
                $entry['errors'][]='سرویس غیرفعال یا لغوشده است.';$blocking++;
                $rows[]=$entry;continue;
            }
            $class=(string)($sv['tariff_vehicle_class']??'');
            if(!in_array($class,['1','2','3'],true)){
                $entry['errors'][]='کلاس تعرفه خودروی راننده مشخص نشده است.';$blocking++;
                $rows[]=$entry;continue;
            }
            $members->execute([$aid,(int)$sv['id']]);
            $list=$members->fetchAll(PDO::FETCH_ASSOC);
            $entry['studentCount']=count($list);$students+=count($list);
            if(!$list){$entry['errors'][]='دانش‌آموزی به این سرویس متصل نیست.';$blocking++;}
            foreach($list as $st){
                try{
                    $previousClass=(int)($st['tariff_vehicle_class']??0);
                    $st['tariff_vehicle_class']=(int)$class;
                    $quote=ServicePricing::quote($pdo,$aid,$st,'taxi');
                    $old=(int)$st['monthly_fee'];$new=(int)$quote['monthlyFee'];
                    $entry['students'][]=['studentId'=>(int)$st['id'],'name'=>trim($st['first_name'].' '.$st['last_name']),
                      'oldFee'=>$old,'newFee'=>$new,'driverBaseMonthly'=>(int)$quote['driverBaseMonthly'],
                      'changed'=>$old!==$new||$st['fee_source']!=='taxi'||$previousClass!==(int)$class];
                    if($old!==$new||$st['fee_source']!=='taxi'||$previousClass!==(int)$class){$changes++;$entry['changeCount']++;}
                }catch(\Throwable $error){
                    $entry['errors'][]='دانش‌آموز '.(int)$st['id'].': '.$error->getMessage();
                    $blocking++;
                }
            }
            $rows[]=$entry;
        }
        return ['services'=>$rows,'serviceCount'=>count($rows),'studentCount'=>$students,
            'changes'=>$changes,'blocking'=>$blocking,'canApply'=>count($rows)>0&&$blocking===0];
    }

    public static function run(int $uid,int $aid,string $kind,int $id,bool $apply=false): array
    {
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,'services.view');
        if($apply){
            Authorization::requirePermission($uid,'services.manage');
            Authorization::requirePermission($uid,'students.manage');
            Authorization::requirePermission($uid,'finance.manage');
        }
        if(!$apply){
            $pdo=Connection::get();
            return self::previewFor($pdo,$aid,self::services($pdo,$aid,$kind,$id));
        }
        return OperationsService::transaction(function(PDO $pdo)use($uid,$aid,$kind,$id){
            $services=self::services($pdo,$aid,$kind,$id);
            $preview=self::previewFor($pdo,$aid,$services);
            if(!$preview['canApply'])
                throw new AuthException(422,'ابتدا اشکالات تعرفه یا اطلاعات دانش‌آموزان را مطابق پیش‌نمایش اصلاح کنید. هیچ تغییری اعمال نشد.','TAXI_APPLY_BLOCKED');
            $updated=0;$studentCount=0;
            foreach($services as $sv){
                $serviceId=(int)$sv['id'];
                $result=ServicePricing::synchronizeServiceVehicle($pdo,$aid,$serviceId,'taxi',true,true);
                $pdo->prepare("UPDATE services SET fee_source='taxi' WHERE id=? AND agency_id=?")
                    ->execute([$serviceId,$aid]);
                $updated++;$studentCount+=(int)$result['updatedStudents'];
            }
            return ['applied'=>true,'servicesUpdated'=>$updated,'studentsUpdated'=>$studentCount,
              'preview'=>$preview,
              'message'=>'تعرفه سازمان تاکسیرانی ثبت و مبالغ و صورت‌حساب‌های قابل به‌روزرسانی همگام‌سازی شدند. اسناد قطعی و دریافت‌های قبلی بدون تغییر باقی مانده‌اند.'];
        });
    }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/**
 * Consent-gated vehicle class transition. All mutations occur within a single
 * transaction; issued contracts, invoice amounts and payments remain immutable.
 * A contract appendix documents the change. Student debt adjustments are signed,
 * separately auditable and can be applied to outstanding balance independently.
 */
final class VehicleClassChangeWorkflow
{
    private static function json(array $value): string {
        return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    }
    private static function assertSchema(PDO $pdo): void {
        foreach(['vehicle_class_changes','vehicle_class_rate_periods','vehicle_class_contract_appendices','vehicle_class_debt_adjustments'] as $table){
            try{$pdo->query('SELECT id FROM '.$table.' LIMIT 0');}
            catch(\PDOException $e){
                if(($e->errorInfo[1]??null)===1146)
                    throw new AuthException(503,'ابتدا مهاجرت 0168 تاریخچه تغییر کلاس خودرو را اجرا کنید.','VEHICLE_CLASS_SCHEMA_REQUIRED');
                throw $e;
            }
        }
    }

    public static function apply(int $uid,int $aid,int $vehicleId,array $body): array {
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,'drivers.manage');
        $choices=VehicleClassEffectiveDates::parseChoices($body);
        if($choices['updateContract'])Authorization::requirePermission($uid,'services.manage');
        if($choices['updateStudentDebt']||$choices['updateDriverPay'])
            Authorization::requirePermission($uid,'finance.manage');

        $class=(string)($body['tariffVehicleClass']??'');
        if(array_key_exists('isActive',$body)&&in_array((string)$body['isActive'],['0'],true))
            throw new AuthException(422,'همزمان با تغییر کلاس، خودرو را غیرفعال نکنید؛ خودروی سرویس باید فعال بماند.','VEHICLE_MUST_REMAIN_ACTIVE');
        if(!in_array($class,['1','2','3'],true))
            throw new AuthException(422,'کلاس جدید خودرو باید ۱، ۲ یا ۳ باشد.','INVALID_VEHICLE_CLASS');

        VehicleClassSchema::ensure();
        return OperationsService::transaction(function(PDO $pdo) use($uid,$aid,$vehicleId,$body,$class,$choices){
            self::assertSchema($pdo);
            $q=$pdo->prepare('SELECT * FROM vehicles WHERE id=? AND agency_id=? FOR UPDATE');
            $q->execute([$vehicleId,$aid]);$target=$q->fetch(PDO::FETCH_ASSOC);
            if(!$target)throw new AuthException(404,'خودرو یافت نشد.','VEHICLE_NOT_FOUND');
            if((int)$target['is_active']!==1)
                throw new AuthException(422,'خودروی جایگزین باید فعال باشد.','VEHICLE_NOT_ACTIVE');

            $requested=$body['driverId']??$body['driver_id']??null;
            if($requested!==null&&$requested!==''&&(!ctype_digit((string)$requested)||(int)$requested<1))
                throw new AuthException(422,'راننده انتخاب‌شده معتبر نیست.','INVALID_DRIVER_ID');
            $owners=$pdo->prepare('SELECT id FROM drivers WHERE agency_id=? AND vehicle_id=? AND is_active=1 FOR UPDATE');
            $owners->execute([$aid,$vehicleId]);$assigned=array_map('intval',$owners->fetchAll(PDO::FETCH_COLUMN));
            if(count($assigned)>1)
                throw new AuthException(409,'این خودرو به چند راننده فعال متصل است؛ ابتدا ارتباط تکراری را اصلاح کنید.','VEHICLE_MULTIPLE_DRIVERS');
            $driverId=(int)($requested?:($assigned[0]??0));
            if($driverId<1)
                throw new AuthException(409,'راننده خودرو مشخص نیست؛ راننده را در ویرایش خودرو انتخاب کنید.','VEHICLE_DRIVER_REQUIRED');
            if($assigned && $assigned[0]!==$driverId)
                throw new AuthException(409,'این خودرو به راننده فعال دیگری تعلق دارد.','VEHICLE_ASSIGNED_TO_OTHER_DRIVER');
            $q=$pdo->prepare('SELECT id,vehicle_id,is_active,status FROM drivers WHERE id=? AND agency_id=? FOR UPDATE');
            $q->execute([$driverId,$aid]);$driver=$q->fetch(PDO::FETCH_ASSOC);
            if(!$driver||(int)$driver['is_active']!==1)
                throw new AuthException(422,'راننده فعال در شرکت یافت نشد.','DRIVER_NOT_AVAILABLE');
            $old=$target;
            if(!empty($driver['vehicle_id'])&&(int)$driver['vehicle_id']!==$vehicleId){
                $q=$pdo->prepare('SELECT * FROM vehicles WHERE id=? AND agency_id=? FOR UPDATE');
                $q->execute([(int)$driver['vehicle_id'],$aid]);
                $registered=$q->fetch(PDO::FETCH_ASSOC);
                if($registered)$old=$registered;
            }
            if((string)($old['tariff_vehicle_class']??'')===$class)
                throw new AuthException(409,'کلاس قبلی راننده با کلاس جدید یکسان است؛ ویرایش معمولی انجام دهید.','VEHICLE_CLASS_NOT_CHANGED');
            // The new registered vehicle and its existing service assignments
            // must remain capacity-compatible before any re-linking.
            $seats=(int)($body['capacity']??$target['capacity']);
            $occupancy=$pdo->prepare('SELECT sv.id,COUNT(ss.student_id) assigned FROM services sv
                LEFT JOIN service_students ss ON ss.service_id=sv.id
                WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1
                GROUP BY sv.id');
            $occupancy->execute([$aid,$driverId]);
            foreach($occupancy->fetchAll(PDO::FETCH_ASSOC) as $serviceCapacity)
                if((int)$serviceCapacity['assigned']>$seats)
                    throw new AuthException(409,'ظرفیت خودروی جدید از تعداد دانش‌آموزان سرویس فعال کمتر است.','VEHICLE_CAPACITY_TOO_SMALL');

            $services=$pdo->prepare("SELECT sv.id,sv.fee_source FROM services sv WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1 AND sv.status<>'cancelled' ORDER BY sv.id FOR UPDATE");
            $services->execute([$aid,$driverId]);$linked=$services->fetchAll(PDO::FETCH_ASSOC);
            $oldRates=[];
            $students=$pdo->prepare('SELECT st.*,ss.service_id FROM service_students ss JOIN students st ON st.id=ss.student_id AND st.agency_id=? AND st.is_active=1 WHERE ss.service_id=? ORDER BY st.id FOR UPDATE');
            foreach($linked as $service) {
                $students->execute([$aid,$service['id']]);
                foreach($students->fetchAll(PDO::FETCH_ASSOC) as $student) {
                    $sid=(int)$student['id'];
                    if(isset($oldRates[$sid]) && $oldRates[$sid]['serviceId']!==(int)$service['id'])
                        throw new AuthException(409,'دانش‌آموز به چند سرویس فعال متصل است؛ ابتدا تخصیص تکراری را اصلاح کنید.','DUPLICATE_STUDENT_ASSIGNMENT');
                    $newRow=$student;$newRow['tariff_vehicle_class']=(int)$class;
                    $mode=ServicePricing::source($service['fee_source']??'taxi');
                    $quote=(!$choices['updateStudentDebt']&&!$choices['updateDriverPay'])||$mode==='agreed'
                        ?['monthlyFee'=>(int)$student['monthly_fee'],'driverBaseMonthly'=>(int)($student['driver_base_monthly']??$student['monthly_fee'])]
                        :ServicePricing::quote($pdo,$aid,$newRow,$mode);
                    $oldRates[$sid]=[
                        'student'=>$student,'serviceId'=>(int)$service['id'],'source'=>$mode,
                        'oldParent'=>(int)$student['monthly_fee'],'newParent'=>(int)$quote['monthlyFee'],
                        'oldDriver'=>(int)($student['driver_base_monthly']??$student['monthly_fee']),
                        'newDriver'=>(int)$quote['driverBaseMonthly']
                    ];
                }
            }

            // All other vehicle fields use the existing validations.
            // Driver reassignment is atomic and audited separately below. The tariff class is written separately
            // to avoid the legacy unconditional financial repricing trigger.
            $update=$body;
            unset($update['driverId'],$update['driver_id']);
            unset($update['tariffVehicleClass'],$update['updateContract'],$update['contractEffectiveDate'],
                $update['updateStudentDebt'],$update['studentDebtEffectiveDate'],
                $update['updateDriverPay'],$update['driverPayEffectiveDate']);
            if($update)DomainService::save($uid,$aid,'vehicles',$update,$vehicleId);
            // Re-link a replacement vehicle without invoking legacy immediate
            // tariff synchronization, which would rewrite past student rates.
            if((int)($driver['vehicle_id']??0)!==$vehicleId){
                $pdo->prepare('UPDATE drivers SET vehicle_id=? WHERE id=? AND agency_id=?')
                    ->execute([$vehicleId,$driverId,$aid]);
                $pdo->prepare('UPDATE services SET capacity=? WHERE agency_id=? AND driver_id=? AND is_active=1')
                    ->execute([$seats,$aid,$driverId]);
            }
            $pdo->prepare('UPDATE vehicles SET tariff_vehicle_class=? WHERE id=? AND agency_id=?')
                ->execute([(int)$class,$vehicleId,$aid]);
            $q=$pdo->prepare('SELECT * FROM vehicles WHERE id=? AND agency_id=?');
            $q->execute([$vehicleId,$aid]);$new=$q->fetch(PDO::FETCH_ASSOC);

            $q=$pdo->prepare("INSERT INTO vehicle_class_changes
                (agency_id,driver_id,vehicle_id,old_vehicle_model,new_vehicle_model,old_vehicle_class,new_vehicle_class,
                 contract_effective_date,update_student_debt,student_debt_effective_date,
                 update_driver_pay,driver_pay_effective_date,old_vehicle_snapshot,new_vehicle_snapshot,decision_snapshot,created_by)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $q->execute([$aid,$driverId,$vehicleId,$old['model'],$new['model'],
                $old['tariff_vehicle_class'],(int)$class,
                $choices['contractEffectiveDate'],
                (int)$choices['updateStudentDebt'],$choices['studentDebtEffectiveDate'],
                (int)$choices['updateDriverPay'],$choices['driverPayEffectiveDate'],
                self::json($old),self::json($new),self::json($choices),$uid]);
            $changeId=(int)$pdo->lastInsertId();

            if($choices['updateContract']){
                $contracts=$pdo->prepare("SELECT c.* FROM contracts c
                    WHERE c.agency_id=? AND c.status<>'cancelled'
                      AND ((c.party_type='driver' AND c.party_id=?)
                        OR (c.party_type='student' AND c.party_id=?))
                      AND (c.end_date IS NULL OR c.end_date>=?) FOR UPDATE");
                // Driver-only contracts must receive the appendix even if
                // no student is currently assigned to the service.
                $contracts->execute([$aid,$driverId,0,$choices['contractEffectiveDate']]);
                foreach($contracts->fetchAll(PDO::FETCH_ASSOC) as $driverContract){
                    $pdo->prepare("INSERT IGNORE INTO vehicle_class_contract_appendices
                        (agency_id,change_id,contract_id,effective_date,old_class,new_class,original_contract_snapshot)
                        VALUES(?,?,?,?,?,?,?)")
                        ->execute([$aid,$changeId,$driverContract['id'],$choices['contractEffectiveDate'],
                            $old['tariff_vehicle_class'],(int)$class,self::json($driverContract)]);
                }
                foreach($oldRates as $sid=>$rate){
                    $contracts->execute([$aid,$driverId,$sid,$choices['contractEffectiveDate']]);
                    foreach($contracts->fetchAll(PDO::FETCH_ASSOC) as $contract){
                        $pdo->prepare("INSERT IGNORE INTO vehicle_class_contract_appendices
                            (agency_id,change_id,contract_id,effective_date,old_class,new_class,original_contract_snapshot)
                            VALUES(?,?,?,?,?,?,?)")
                            ->execute([$aid,$changeId,$contract['id'],$choices['contractEffectiveDate'],
                                $old['tariff_vehicle_class'],(int)$class,self::json($contract)]);
                    }
                }
            }

            $period=$pdo->prepare("INSERT INTO vehicle_class_rate_periods
                (agency_id,change_id,subject_type,subject_id,service_id,effective_date,old_class,new_class,
                 old_monthly_irr,new_monthly_irr,fee_source,calculation_snapshot)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
            $debt=$pdo->prepare("INSERT INTO vehicle_class_debt_adjustments
                (agency_id,change_id,student_id,service_id,academic_year,effective_date,amount_irr,rate_snapshot)
                VALUES(?,?,?,?,?,?,?,?)");
            $contract=$pdo->prepare("SELECT c.start_date,c.end_date,m.academic_year FROM student_contract_invoices m
              JOIN contracts c ON c.id=m.contract_id AND c.agency_id=m.agency_id
              WHERE m.agency_id=? AND m.student_id=? AND m.academic_year=?
              ORDER BY c.id DESC LIMIT 1 FOR UPDATE");
            foreach($oldRates as $sid=>$rate){
                $student=$rate['student'];
                if($choices['updateStudentDebt']){
                    $period->execute([$aid,$changeId,'student_debt',$sid,$rate['serviceId'],
                        $choices['studentDebtEffectiveDate'],$old['tariff_vehicle_class'],(int)$class,
                        $rate['oldParent'],$rate['newParent'],$rate['source'],self::json($rate)]);
                    $studentYear=ContractBilling::year((string)($student['academic_year']??''));
                    $contract->execute([$aid,$sid,$studentYear]);$bound=$contract->fetch(PDO::FETCH_ASSOC);
                    if(!$bound||!$bound['start_date']||!$bound['end_date'])
                        throw new AuthException(409,'برای تغییر بدهی دانش‌آموز، قرارداد صادرشده و بازه معتبر الزامی است. ابتدا پرونده مالی دانش‌آموز را تکمیل کنید.','CONTRACT_REQUIRED_FOR_DEBT_ADJUSTMENT');
                    if($bound&&$bound['start_date']&&$bound['end_date']){
                        $start=max($bound['start_date'],$choices['studentDebtEffectiveDate']);
                        $delta=0;
                        if($start<=$bound['end_date']){
                            $newQuote=ContractBilling::quote($bound['academic_year'],$start,$bound['end_date'],$rate['newParent']);
                            $oldQuote=ContractBilling::quote($bound['academic_year'],$start,$bound['end_date'],$rate['oldParent']);
                            $delta=$newQuote['amount']-$oldQuote['amount'];
                        }
                        $debt->execute([$aid,$changeId,$sid,$rate['serviceId'],$bound['academic_year'],
                            $choices['studentDebtEffectiveDate'],$delta,self::json(['rate'=>$rate,'contract'=>$bound])]);
                    }
                    // Due rates are applied below; future rates remain dormant.
                }
                if($choices['updateDriverPay']){
                    $period->execute([$aid,$changeId,'driver_pay',$sid,$rate['serviceId'],
                        $choices['driverPayEffectiveDate'],$old['tariff_vehicle_class'],(int)$class,
                        $rate['oldDriver'],$rate['newDriver'],$rate['source'],self::json($rate)]);
                    // The driver rate is applied when its independent date arrives.
                }
            }
            // Due-today rates are applied in this transaction, so any failure
            // rolls back the vehicle transfer and all financial/audit writes.
            // Future periods are idempotently activated on a later authorized API request.
            VehicleClassRateApplication::applyDue($aid);
            return ['changeId'=>$changeId,'vehicleId'=>$vehicleId,'driverId'=>$driverId,
                'affectedStudents'=>count($oldRates),'affectedServices'=>count($linked),'choices'=>$choices];
        });
    }
}

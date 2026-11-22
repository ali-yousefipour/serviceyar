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
        if(!in_array($class,['1','2','3'],true))
            throw new AuthException(422,'کلاس جدید خودرو باید ۱، ۲ یا ۳ باشد.','INVALID_VEHICLE_CLASS');

        return OperationsService::transaction(function(PDO $pdo) use($uid,$aid,$vehicleId,$body,$class,$choices){
            self::assertSchema($pdo);
            $q=$pdo->prepare('SELECT * FROM vehicles WHERE id=? AND agency_id=? FOR UPDATE');
            $q->execute([$vehicleId,$aid]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if(!$old)throw new AuthException(404,'خودرو یافت نشد.','VEHICLE_NOT_FOUND');
            if((string)($old['tariff_vehicle_class']??'')===$class)
                throw new AuthException(409,'کلاس خودرو تغییر نکرده است؛ ویرایش معمولی انجام دهید.','VEHICLE_CLASS_NOT_CHANGED');

            // Exactly one active driver can own this vehicle. Ambiguity would
            // otherwise silently reprice the wrong service/driver.
            $q=$pdo->prepare('SELECT id,first_name,last_name FROM drivers WHERE agency_id=? AND vehicle_id=? AND is_active=1 FOR UPDATE');
            $q->execute([$aid,$vehicleId]);$drivers=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($drivers)!==1)
                throw new AuthException(409,'برای این خودرو راننده فعال یکتا ثبت نشده است؛ ابتدا از قسمت رانندگان اتصال خودروی جدید را اصلاح کنید.','VEHICLE_DRIVER_ASSOCIATION_REQUIRED');
            $driverId=(int)$drivers[0]['id'];
            // A vehicle swap and a class-rate transition are distinct actions:
            // never silently transfer a vehicle to a different driver while
            // calculating the old driver's contractual rates.
            if(array_key_exists('driverId',$body)||array_key_exists('driver_id',$body)){
                $requested=$body['driverId']??$body['driver_id'];
                if((string)$requested!==(string)$driverId)
                    throw new AuthException(409,'ابتدا اتصال خودروی جدید به راننده را در بخش رانندگان اصلاح و ذخیره کنید؛ سپس تغییر کلاس را ثبت کنید.','DRIVER_REASSIGNMENT_FIRST');
            }

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

            // All other vehicle fields use the same normal validation and
            // driver-assignment rules. The tariff class is written separately
            // to avoid the legacy unconditional financial repricing trigger.
            $update=$body;
            unset($update['driverId'],$update['driver_id']);
            unset($update['tariffVehicleClass'],$update['updateContract'],$update['contractEffectiveDate'],
                $update['updateStudentDebt'],$update['studentDebtEffectiveDate'],
                $update['updateDriverPay'],$update['driverPayEffectiveDate']);
            if($update)DomainService::save($uid,$aid,'vehicles',$update,$vehicleId);
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
              WHERE m.agency_id=? AND m.student_id=?
              ORDER BY c.id DESC LIMIT 1 FOR UPDATE");
            foreach($oldRates as $sid=>$rate){
                $student=$rate['student'];
                if($choices['updateStudentDebt']){
                    $period->execute([$aid,$changeId,'student_debt',$sid,$rate['serviceId'],
                        $choices['studentDebtEffectiveDate'],$old['tariff_vehicle_class'],(int)$class,
                        $rate['oldParent'],$rate['newParent'],$rate['source'],self::json($rate)]);
                    $contract->execute([$aid,$sid]);$bound=$contract->fetch(PDO::FETCH_ASSOC);
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
                    // The current monthly tuition reflects the approved new
                    // class, but historic invoicing and cash receipts stay fixed.
                    $pdo->prepare('UPDATE students SET monthly_fee=?,tariff_vehicle_class=? WHERE id=? AND agency_id=?')
                        ->execute([$rate['newParent'],(int)$class,$sid,$aid]);
                }
                if($choices['updateDriverPay']){
                    $period->execute([$aid,$changeId,'driver_pay',$sid,$rate['serviceId'],
                        $choices['driverPayEffectiveDate'],$old['tariff_vehicle_class'],(int)$class,
                        $rate['oldDriver'],$rate['newDriver'],$rate['source'],self::json($rate)]);
                    $pdo->prepare('UPDATE students SET driver_base_monthly=? WHERE id=? AND agency_id=?')
                        ->execute([$rate['newDriver'],$sid,$aid]);
                }
            }
            // Service monthly net follows only explicit driver-pay consent.
            if($choices['updateDriverPay']){
                foreach($linked as $service) {
                    $gross=0;
                    foreach($oldRates as $rate)if($rate['serviceId']===(int)$service['id'])$gross+=$rate['newDriver'];
                    $net=OperationsService::driverDeductions($gross,$aid)['net_earned'];
                    $pdo->prepare('UPDATE services SET monthly_fee=? WHERE id=? AND agency_id=?')
                        ->execute([$net,$service['id'],$aid]);
                }
            }
            return ['changeId'=>$changeId,'vehicleId'=>$vehicleId,'driverId'=>$driverId,
                'affectedStudents'=>count($oldRates),'affectedServices'=>count($linked),'choices'=>$choices];
        });
    }
}

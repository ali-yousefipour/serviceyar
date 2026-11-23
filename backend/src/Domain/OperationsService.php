<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

final class OperationsService
{
    public static function validDate(string $date): bool
    {
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        return $d!==false && $d->format('Y-m-d')===$date;
    }

    private static function scope(int $uid,int $aid,string $permission): PDO
    {
        Authorization::requirePermission($uid,$permission);
        Authorization::requireAgencyAccess($uid,$aid);
        return Connection::get();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo=Connection::get();$owns=!$pdo->inTransaction();
        $savepoint='sy_'.bin2hex(random_bytes(5));
        if($owns)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT '.$savepoint);
        try{$result=$callback($pdo);if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT '.$savepoint);return $result;}
        catch(\Throwable $e){if($pdo->inTransaction()){if($owns)$pdo->rollBack();else $pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);}throw $e;}
    }

    public static function summary(int $uid,int $aid): array
    {
        $p=self::scope($uid,$aid,'dashboard.view');$counts=[];
        foreach(['schools','students','drivers','services'] as $t){$extra=$t==='drivers'?" AND status='active'":'';$q=$p->prepare("SELECT COUNT(*) FROM $t WHERE agency_id=:a AND is_active=1$extra");$q->execute(['a'=>$aid]);$counts[$t]=(int)$q->fetchColumn();}
        $q=$p->prepare('SELECT service_status,COUNT(*) total FROM students WHERE agency_id=:a AND is_active=1 GROUP BY service_status');$q->execute(['a'=>$aid]);$statuses=$q->fetchAll();
        // Operational assignment means a real, non-cancelled active service,
        // not merely the potentially stale students.service_status label.
        $q=$p->prepare("SELECT COUNT(DISTINCT st.id) FROM students st
            INNER JOIN service_students ss ON ss.student_id=st.id
            INNER JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=st.agency_id
               AND sv.is_active=1 AND sv.status<>'cancelled'
            WHERE st.agency_id=:a AND st.is_active=1");
        $q->execute(['a'=>$aid]);$assigned=(int)$q->fetchColumn();
        $q=$p->prepare("SELECT COUNT(*) FROM services sv
            INNER JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=sv.agency_id
            WHERE sv.agency_id=:a AND sv.is_active=1 AND sv.status<>'cancelled'
              AND d.is_active=1 AND d.status='active'");
        $q->execute(['a'=>$aid]);$staffedServices=(int)$q->fetchColumn();
        $q=$p->prepare("SELECT COUNT(DISTINCT d.id) FROM drivers d
            INNER JOIN services sv ON sv.driver_id=d.id AND sv.agency_id=d.agency_id
               AND sv.is_active=1 AND sv.status<>'cancelled'
            WHERE d.agency_id=:a AND d.is_active=1 AND d.status='active'");
        $q->execute(['a'=>$aid]);$servingDrivers=(int)$q->fetchColumn();
        $q=$p->prepare("SELECT COALESCE(SUM(sv.capacity),0) FROM services sv
            WHERE sv.agency_id=:a AND sv.is_active=1 AND sv.status<>'cancelled'");
        $q->execute(['a'=>$aid]);$totalCapacity=(int)$q->fetchColumn();
        $q=$p->prepare("SELECT COUNT(*) FROM services sv WHERE sv.agency_id=:a
            AND sv.is_active=1 AND sv.status<>'cancelled'");
        $q->execute(['a'=>$aid]);$activeServices=(int)$q->fetchColumn();
        $q=$p->prepare("SELECT sc.id,sc.name,sc.latitude,sc.longitude,sc.photo_path,
              COUNT(DISTINCT st.id) students,
              COUNT(DISTINCT CASE WHEN sv.id IS NOT NULL THEN st.id END) assigned,
              COUNT(DISTINCT st.id)-COUNT(DISTINCT CASE WHEN sv.id IS NOT NULL THEN st.id END) unassigned
            FROM schools sc
            LEFT JOIN students st ON st.school_id=sc.id AND st.agency_id=sc.agency_id AND st.is_active=1
            LEFT JOIN service_students ss ON ss.student_id=st.id
            LEFT JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=sc.agency_id
                 AND sv.is_active=1 AND sv.status<>'cancelled'
            WHERE sc.agency_id=:a AND sc.is_active=1
            GROUP BY sc.id,sc.name,sc.latitude,sc.longitude,sc.photo_path
            ORDER BY unassigned DESC,students DESC,sc.name");
        $q->execute(['a'=>$aid]);$schools=$q->fetchAll();
        $unassigned=max(0,$counts['students']-$assigned);
        // Count mismatching student records, not the absolute difference of
        // two totals (which can cancel opposite discrepancies out).
        $q=$p->prepare("SELECT COUNT(*) FROM students st
            WHERE st.agency_id=:a AND st.is_active=1 AND
              (COALESCE(st.service_status,'')='assigned') <>
              EXISTS(SELECT 1 FROM service_students ss
                JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=st.agency_id
                  AND sv.is_active=1 AND sv.status<>'cancelled'
                WHERE ss.student_id=st.id)");
        $q->execute(['a'=>$aid]);$statusMismatch=(int)$q->fetchColumn();
        return [
          'counts'=>$counts,'statuses'=>$statuses,'schools'=>$schools,
          'metrics'=>[
            'assignedStudents'=>$assigned,'unassignedStudents'=>$unassigned,
            'assignmentRate'=>$counts['students']>0?round(100*$assigned/$counts['students'],1):0,
            'activeServices'=>$activeServices,'staffedServices'=>$staffedServices,
            'servicesWithoutActiveDriver'=>max(0,$activeServices-$staffedServices),
            'servingDrivers'=>$servingDrivers,'serviceCapacity'=>$totalCapacity,
            'capacityUtilization'=>$totalCapacity>0?round(100*$assigned/$totalCapacity,1):0,
            'statusMismatch'=>$statusMismatch,
          ],
          'generatedAt'=>gmdate('c'),'dataScope'=>'agency','financialDataIncluded'=>false
        ];
    }

    public static function dashboardDetails(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'dashboard.view');
        $metric=$filter['metric']??'';
        $activeService="sv.is_active=1 AND sv.status<>'cancelled'";
        $assignment="EXISTS(SELECT 1 FROM service_students ss JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=st.agency_id AND $activeService WHERE ss.student_id=st.id)";
        $activeDriver="EXISTS(SELECT 1 FROM drivers d WHERE d.id=sv.driver_id AND d.agency_id=sv.agency_id AND d.is_active=1 AND d.status='active')";
        $params=['agency'=>$aid];
        if(in_array($metric,['students','assigned','unassigned','mismatch','student-status'],true)){
            Authorization::requirePermission($uid,'students.view');
            $condition=match($metric){
                'assigned'=>$assignment,
                'unassigned'=>"NOT $assignment",
                'mismatch'=>"(COALESCE(st.service_status,'')='assigned') <> $assignment",
                'student-status'=>"COALESCE(st.service_status,'')=:status",
                default=>'1=1',
            };
            if($metric==='student-status')$params['status']=(string)($filter['status']??'');
            if(isset($filter['schoolId'])){
                $params['school']=filter_var($filter['schoolId'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                if(!$params['school'])throw new AuthException(422,'مدرسه معتبر نیست.','INVALID_SCHOOL');
                $condition.=' AND st.school_id=:school';
            }
            $sql="SELECT st.id, CONCAT_WS(' ',st.first_name,st.last_name) name, sc.name detail, st.service_status state
                  FROM students st LEFT JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
                  WHERE st.agency_id=:agency AND st.is_active=1 AND $condition";
        }elseif(in_array($metric,['services','staffed','unstaffed'],true)){
            Authorization::requirePermission($uid,'services.view');
            $condition=match($metric){'staffed'=>$activeDriver,'unstaffed'=>"NOT $activeDriver",default=>'1=1'};
            $sql="SELECT sv.id,sv.title name,CONCAT_WS(' ',d.first_name,d.last_name) detail,sv.capacity state
                  FROM services sv LEFT JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=sv.agency_id
                  WHERE sv.agency_id=:agency AND $activeService AND $condition";
        }elseif($metric==='drivers'){
            Authorization::requirePermission($uid,'drivers.view');
            $sql="SELECT d.id,CONCAT_WS(' ',d.first_name,d.last_name) name,COUNT(*) detail,NULL state
                  FROM drivers d JOIN services sv ON sv.driver_id=d.id AND sv.agency_id=d.agency_id AND $activeService
                  WHERE d.agency_id=:agency AND d.is_active=1 AND d.status='active' GROUP BY d.id,d.first_name,d.last_name";
        }else{
            throw new AuthException(422,'شاخص داشبورد معتبر نیست.','INVALID_DASHBOARD_METRIC');
        }
        $page=max(1,min(100000,(int)($filter['page']??1)));
        $offset=($page-1)*25;
        $stmt=$p->prepare("SELECT details.*,COUNT(*) OVER() total FROM ($sql) details ORDER BY name,id LIMIT 25 OFFSET $offset");
        $stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['items'=>$rows,'total'=>(int)($rows[0]['total']??0)];
    }

    public static function assign(int $uid,int $aid,int $serviceId,int $studentId,?string $address=null,?int $order=null): void
    {
        self::scope($uid,$aid,'services.manage');
        if($order!==null&&$order<1)throw new AuthException(422,'ترتیب توقف نامعتبر است.','VALIDATION_ERROR');
        $canSetTariff=Authorization::isSuperAdmin($uid)||in_array('students.manage',Authorization::permissions($uid),true);
        self::transaction(function(PDO $p)use($aid,$serviceId,$studentId,$address,$order,$canSetTariff){
            $q=$p->prepare('SELECT * FROM services WHERE id=:id AND agency_id=:a AND is_active=1 FOR UPDATE');$q->execute(['id'=>$serviceId,'a'=>$aid]);$service=$q->fetch();
            if(!$service)throw new AuthException(404,'سرویس پیدا نشد.','NOT_FOUND');
            $q=$p->prepare('SELECT * FROM students WHERE id=:id AND agency_id=:a AND is_active=1 FOR UPDATE');$q->execute(['id'=>$studentId,'a'=>$aid]);$student=$q->fetch();
            if(!$student)throw new AuthException(404,'دانش‌آموز پیدا نشد.','NOT_FOUND');
            $q=$p->prepare('SELECT school_id FROM service_schools WHERE service_id=:s');$q->execute(['s'=>$serviceId]);$allowed=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if($service['school_id'])$allowed[]=(int)$service['school_id'];
            if(!$allowed||!in_array((int)$student['school_id'],$allowed,true))throw new AuthException(422,'مدرسه دانش‌آموز در مدارس سرویس نیست.','SCHOOL_MISMATCH');
            $q=$p->prepare('SELECT ss.service_id FROM service_students ss JOIN services s ON s.id=ss.service_id WHERE ss.student_id=:id AND s.agency_id=:a AND s.is_active=1');$q->execute(['id'=>$studentId,'a'=>$aid]);$assigned=$q->fetchAll(PDO::FETCH_COLUMN);
            foreach($assigned as $id)if((int)$id!==$serviceId)throw new AuthException(409,'دانش‌آموز سرویس فعال دیگری دارد. ابتدا تخصیص قبلی را حذف کنید.','ALREADY_ASSIGNED');
            if(!$assigned){$q=$p->prepare('SELECT COUNT(*) FROM service_students WHERE service_id=:s');$q->execute(['s'=>$serviceId]);if((int)$q->fetchColumn()>=(int)$service['capacity'])throw new AuthException(409,'ظرفیت سرویس تکمیل است.','CAPACITY_EXCEEDED');}
            $p->prepare('INSERT INTO service_students(service_id,student_id,pickup_address,pickup_order) VALUES(:s,:st,:p,:o) ON DUPLICATE KEY UPDATE pickup_address=VALUES(pickup_address),pickup_order=VALUES(pickup_order)')->execute(['s'=>$serviceId,'st'=>$studentId,'p'=>$address,'o'=>$order]);
            // A new assignment establishes the authoritative vehicle class.
            // Official tariff is default; explicitly agreed/agency student fees
            // are preserved as opt-outs and never silently converted to taxi.
            $vehicle=ServiceFinancialBasis::service($p,$aid,$serviceId);
            $class=(int)($vehicle['tariff_vehicle_class']??0);
            // Legacy services sometimes have no driver/vehicle assigned yet.
            // A missing driver cannot supply an authoritative vehicle class;
            // retain a pre-existing fee, or quote provisionally from the
            // student's saved class until a classified vehicle is assigned.
            $vehicleClassKnown=in_array($class,[1,2,3],true);
            if(!$vehicleClassKnown)$class=(int)($student['tariff_vehicle_class']??0);
            if(!in_array($class,[1,2,3],true)&&
                (float)($student['monthly_fee']??0)<=0&&
                (float)($student['tariff_distance_km']??0)>0)
                throw new AuthException(422,'کلاس خودروی راننده یا دانش‌آموز مشخص نیست.','VEHICLE_TARIFF_CLASS_REQUIRED');
            $source=(string)($student['fee_source']??'taxi');
            if($source==='taxi' && in_array($service['fee_source'],['agency','agreed'],true))
                $source=(string)$service['fee_source'];
            if($source!=='agreed'&&in_array($class,[1,2,3],true)
               && (float)($student['tariff_distance_km']??0)>0){
                try{
                    $quote=ServiceFinancialBasis::quoteStudent($p,$aid,$student,$source,$class);
                }catch(AuthException $e){
                    // Legacy schools without a valid district may retain their
                    // pre-existing agency rate ONLY until a district is fixed.
                    if($e->codeName!=='TAXI_DISTRICT_REQUIRED'||$vehicleClassKnown)throw $e;
                    $legacyFee=AgencySchoolTariffs::quote($p,$aid,(int)$student['school_id'],
                        (string)(($student['academic_year']??null)?:AgencySchoolTariffs::YEAR),
                        $student['tariff_distance_km'],$class);
                    if($legacyFee===null)throw $e;
                    $source='agency';
                    $quote=['monthlyFee'=>$legacyFee,'driverBaseMonthly'=>$legacyFee];
                }
                $p->prepare("UPDATE students SET tariff_vehicle_class=?,fee_source=?,monthly_fee=?,
                  driver_base_monthly=?,tariff_calculated_at=NOW() WHERE id=? AND agency_id=?")
                  ->execute([$class,$source,(int)$quote['monthlyFee'],(int)$quote['driverBaseMonthly'],$studentId,$aid]);
            }elseif($vehicleClassKnown){
                $p->prepare('UPDATE students SET tariff_vehicle_class=? WHERE id=? AND agency_id=?')
                    ->execute([$class,$studentId,$aid]);
            }
            $p->prepare("UPDATE students SET service_status='assigned' WHERE id=:id AND agency_id=:a")->execute(['id'=>$studentId,'a'=>$aid]);
            // A new-service wizard recalculates zero/legacy rates later in the same
            // transaction; it will synchronize the invoice after repricing.
            $feeCheck=$p->prepare('SELECT monthly_fee FROM students WHERE id=? AND agency_id=?');
            $feeCheck->execute([$studentId,$aid]);
            if((float)$feeCheck->fetchColumn()>0)StudentServiceBilling::sync($p,$aid,$studentId);
            // Keep the saved driver net consistent for direct assignments as well
            // as the new-service wizard; avoid leaving a zero legacy monthly total.
            $driverGross=ServiceFinancialBasis::grossOfService($p,$aid,$serviceId);
            $net=self::driverDeductions((float)$driverGross,$aid)['net_earned'];
            $p->prepare('UPDATE services SET monthly_fee=? WHERE id=? AND agency_id=?')->execute([$net,$serviceId,$aid]);
        });
        self::audit($uid,$aid,'assign_student','services',$serviceId);
    }

    public static function bulkFees(int $uid,int $aid,array $body): array
    {
        self::scope($uid,$aid,'services.manage');Authorization::requirePermission($uid,'students.manage');
        $ids=self::ids($body['ids']??[]);$mode=$body['mode']??'set';$value=$body['value']??null;
        if(!in_array($mode,['set','increase','decrease','percent'],true)||!is_numeric($value)||!is_finite((float)$value)||abs((float)$value)>1000000000000||($mode!=='percent'&&(float)$value<0)||($mode==='percent'&&(float)$value< -100))throw new AuthException(422,'نوع تغییر یا مبلغ نامعتبر است.','VALIDATION_ERROR');
        self::transaction(function(PDO $p)use($ids,$aid,$mode,$value){
            $q=$p->prepare('SELECT id,monthly_fee FROM students WHERE agency_id=? AND is_active=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).') FOR UPDATE');$q->execute(array_merge([$aid],$ids));$rows=$q->fetchAll();
            if(count($rows)!==count($ids))throw new AuthException(422,'انتخاب‌ها نامعتبر یا خارج از شرکت هستند.','CROSS_SCOPE_REFERENCE');
            $u=$p->prepare("UPDATE students SET monthly_fee=:v,driver_base_monthly=:base,fee_source='agreed' WHERE id=:id AND agency_id=:a");
            foreach($rows as $r){$old=(float)$r['monthly_fee'];$new=match($mode){'set'=>(float)$value,'increase'=>$old+(float)$value,'decrease'=>$old-(float)$value,'percent'=>$old*(1+(float)$value/100)};
                if($new<0||$new>999999999999999)throw new AuthException(422,'نتیجه تغییر هزینه خارج از محدوده است.','VALIDATION_ERROR');
                $amount=round($new,2);$u->execute(['v'=>$amount,'base'=>round($amount),'id'=>$r['id'],'a'=>$aid]);StudentServiceBilling::sync($p,$aid,(int)$r['id']);}
            $serviceIds=$p->prepare('SELECT DISTINCT ss.service_id FROM service_students ss JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=? AND sv.is_active=1 WHERE ss.student_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
            $serviceIds->execute(array_merge([$aid],$ids));
            foreach($serviceIds->fetchAll(PDO::FETCH_COLUMN) as $serviceId){
                $gross=ServiceFinancialBasis::grossOfService($p,$aid,(int)$serviceId);
                $net=ServiceFinancialBasis::netOfGross($gross,$aid)['net_earned'];
                $p->prepare('UPDATE services SET monthly_fee=? WHERE agency_id=? AND id=?')->execute([$net,$aid,$serviceId]);
            }
        });self::audit($uid,$aid,'bulk_fees','students',null);return ['updated'=>count($ids)];
    }

    public static function ids(mixed $raw): array
    {
        if(!is_array($raw)||!count($raw)||count($raw)>200)throw new AuthException(422,'بین ۱ تا ۲۰۰ مورد انتخاب کنید.','VALIDATION_ERROR');
        $ids=[];foreach($raw as $id){if(!ctype_digit((string)$id)||(int)$id<1)throw new AuthException(422,'شناسه نامعتبر است.','VALIDATION_ERROR');$ids[]=(int)$id;}return array_values(array_unique($ids));
    }

    public static function payroll(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'finance.view');[$from,$to]=self::range($filter);
        $estimateRequested=($filter['calculationMode']??'recorded')==='assignment_estimate';
        if(!in_array(($filter['calculationMode']??'recorded'),['recorded','assignment_estimate'],true))throw new AuthException(422,'روش محاسبه حقوق نامعتبر است.','VALIDATION_ERROR');
        $periodDays=(new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days+1;
        if($estimateRequested&&($periodDays<28||$periodDays>31))throw new AuthException(422,'برآورد ماهانه فقط برای بازه کامل ۲۸ تا ۳۱ روزه مجاز است.','INVALID_ESTIMATE_PERIOD');
        $q=$p->prepare('SELECT d.id,d.first_name,d.last_name,d.mobile,d.iban,d.bank_account,
          (SELECT COALESCE(SUM(w.amount),0) FROM driver_work w WHERE w.driver_id=d.id AND w.agency_id=d.agency_id AND w.work_date BETWEEN :wf AND :wt AND w.status<>\'cancelled\') earned,
          (SELECT COUNT(*) FROM driver_work w WHERE w.driver_id=d.id AND w.agency_id=d.agency_id AND w.work_date BETWEEN :cf AND :ct AND w.status<>\'cancelled\') recorded_work_count,
          (SELECT COALESCE(SUM(dp.amount),0) FROM driver_payments dp WHERE dp.driver_id=d.id AND dp.agency_id=d.agency_id AND dp.payment_date BETWEEN :pf AND :pt) paid,
          (SELECT COALESCE(SUM(CASE WHEN le.direction=\'payment\' THEN le.amount ELSE -le.amount END),0) FROM ledger_entries le WHERE le.party_type=\'driver\' AND le.party_id=d.id AND le.agency_id=d.agency_id AND le.is_active=1 AND le.entry_date BETWEEN :lf AND :lt) ledger_paid,
          (SELECT COALESCE(SUM(s.agreed_amount),0) FROM services s WHERE s.driver_id=d.id AND s.agency_id=d.agency_id AND s.is_active=1) agreed,
          (SELECT COALESCE(SUM(st.driver_base_monthly),0)
             FROM services s
             JOIN service_students ss ON ss.service_id=s.id
             JOIN students st ON st.id=ss.student_id AND st.agency_id=s.agency_id
             WHERE s.driver_id=d.id AND s.agency_id=d.agency_id AND s.is_active=1 AND s.status=\'active\' AND st.is_active=1) assigned_monthly_base,
          (SELECT COUNT(*)
             FROM services s
             JOIN service_students ss ON ss.service_id=s.id
             JOIN students st ON st.id=ss.student_id AND st.agency_id=s.agency_id
             WHERE s.driver_id=d.id AND s.agency_id=d.agency_id AND s.is_active=1 AND s.status=\'active\' AND st.is_active=1) assigned_student_count
          FROM drivers d WHERE d.agency_id=:a AND d.is_active=1 ORDER BY d.last_name,d.first_name');
        $q->execute(['a'=>$aid,'wf'=>$from,'wt'=>$to,'cf'=>$from,'ct'=>$to,'pf'=>$from,'pt'=>$to,'lf'=>$from,'lt'=>$to]);$rows=$q->fetchAll();
        // Use date-scoped signed vehicle-class rates in assignment estimates
        // only. Recorded driver_work and approved payroll remain immutable.
        $rateHistory=[];
        if(VehicleClassSchema::exists($p)){
            $rateQuery=$p->prepare("SELECT rp.subject_id,rp.service_id,rp.id,rp.effective_date,
                    rp.old_monthly_irr,rp.new_monthly_irr,ch.driver_id AS rate_driver_id
                FROM vehicle_class_rate_periods rp
                JOIN vehicle_class_changes ch ON ch.id=rp.change_id
                    AND ch.agency_id=rp.agency_id AND ch.update_driver_pay=1
                WHERE rp.agency_id=? AND rp.subject_type='driver_pay'
                ORDER BY rp.subject_id,rp.service_id,rp.effective_date,rp.id");
            $rateQuery->execute([$aid]);
            foreach($rateQuery->fetchAll(\PDO::FETCH_ASSOC) as $period)
                $rateHistory[(int)$period['subject_id'].':'.(int)$period['service_id']][]=$period;
        }
        // A current assignment is not a dated ledger. Never project a full
        // historical month across a driver change without a signed day split.
        $driverChangesByService=[];
        if($estimateRequested){
            $changed=$p->prepare("SELECT dc.service_id,dc.reviewed_at,dc.old_driver_id,dc.new_driver_id
                FROM driver_changes dc
                JOIN services sv ON sv.id=dc.service_id AND sv.agency_id=dc.agency_id
                WHERE dc.agency_id=? AND dc.status='approved'
                  AND dc.reviewed_at IS NOT NULL AND DATE(dc.reviewed_at)>=?
                ORDER BY dc.service_id,dc.reviewed_at,dc.id");
            $changed->execute([$aid,$from]);
            foreach($changed->fetchAll(\PDO::FETCH_ASSOC) as $change){
                // Changes after the reporting period also prove that the
                // current driver cannot be assumed to have served that month.
                $driverChangesByService[(int)$change['service_id']][]=$change;
            }
        }
        $details=$p->prepare("SELECT sv.id AS service_id,sv.driver_id,sv.title,sv.code,sv.fee_source,sv.created_at AS service_created_at,
            st.id AS student_id,st.first_name AS student_first_name,st.last_name AS student_last_name,st.created_at AS student_created_at,st.monthly_fee,st.driver_base_monthly,
            dr.first_name AS driver_first_name,dr.last_name AS driver_last_name,
            vh.model AS vehicle_model,vh.plate AS vehicle_plate,vh.tariff_vehicle_class AS vehicle_class
            FROM services sv JOIN service_students ss ON ss.service_id=sv.id
            JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id AND st.is_active=1
            LEFT JOIN drivers dr ON dr.id=sv.driver_id AND dr.agency_id=sv.agency_id
            LEFT JOIN vehicles vh ON vh.id=dr.vehicle_id AND vh.agency_id=sv.agency_id
            WHERE sv.agency_id=? AND sv.is_active=1 AND sv.status='active'
            ORDER BY sv.id,st.id");
        $details->execute([$aid]);$servicesMap=[];$conflicts=[];
        foreach($details->fetchAll(\PDO::FETCH_ASSOC) as $member){
            if($member['driver_id']===null)continue;
            $serviceId=(int)$member['service_id'];
            $studentId=(int)$member['student_id'];
            if(!isset($servicesMap[$serviceId]))$servicesMap[$serviceId]=[
                'service_id'=>$serviceId,'driver_id'=>$member['driver_id'],
                'title'=>$member['title'],'code'=>$member['code'],
                'fee_source'=>$member['fee_source'],'student_count'=>0,'monthly_gross'=>0];
            $history=array_values(array_filter(
                $rateHistory[$studentId.':'.$serviceId]??[],
                static fn(array $rate): bool=>(int)$rate['rate_driver_id']===(int)$member['driver_id']
            ));
            $changes=$driverChangesByService[$serviceId]??[];
            // Approval timestamp is evidence of a reassignment, not an
            // authoritative effective date for daily salary apportionment.
            $createdInOrAfterPeriod=substr((string)$member['student_created_at'],0,10)>$from
                || substr((string)$member['service_created_at'],0,10)>$from;
            if($estimateRequested && ($changes||$createdInOrAfterPeriod)){
                $reasonCode=$changes?'HISTORICAL_DRIVER_ASSIGNMENT_UNVERIFIED':'HISTORICAL_SERVICE_DAYS_UNVERIFIED';
                $reason=$changes
                    ?'راننده این سرویس تغییر کرده است؛ تاریخچه تخصیص برای برآورد کل ماه کافی نیست.'
                    :'دانش‌آموز یا سرویس پس از شروع دوره ثبت شده و خدمت کامل ماه تأیید نشده است.';
                $conflicts[]=[
                    'studentId'=>$studentId,
                    'studentName'=>trim((string)$member['student_first_name'].' '.(string)$member['student_last_name']),
                    'driverId'=>(int)$member['driver_id'],
                    'driverName'=>trim((string)$member['driver_first_name'].' '.(string)$member['driver_last_name']),
                    'serviceId'=>$serviceId,
                    'serviceName'=>(string)$member['title'],
                    'vehicleName'=>trim((string)$member['vehicle_model'].' '.(string)$member['vehicle_plate']),
                    'vehicleClass'=>$member['vehicle_class']===null?null:(int)$member['vehicle_class'],
                    'reasonCode'=>$reasonCode,'reason'=>$reason,'severity'=>'blocking',
                    'effectiveDate'=>$changes?substr((string)$changes[0]['reviewed_at'],0,10):max(substr((string)$member['student_created_at'],0,10),substr((string)$member['service_created_at'],0,10)),
                    'recordedBasis'=>$member['driver_base_monthly'],
                    'suggestedAction'=>'برای این دوره از کارکرد ثبت‌شده و مستند استفاده کنید؛ برآورد کامل ماه با تخصیص جاری مجاز نیست.'
                ];
                continue;
            }
            $currentBasisValid=$member['driver_base_monthly']!==null
                && $member['driver_base_monthly']!==''
                && is_numeric($member['driver_base_monthly'])
                && (float)$member['driver_base_monthly']>=0;
            // An approved, signed rate history is a valid independent source
            // even when the legacy current-rate field was never backfilled.
            $historyBasisValid=count($history)>0;
            $previousRate=null;
            foreach($history as $period){
                if(!is_numeric($period['old_monthly_irr'])||!is_numeric($period['new_monthly_irr'])
                    ||(float)$period['old_monthly_irr']<0||(float)$period['new_monthly_irr']<0
                    ||($previousRate!==null && (int)$period['old_monthly_irr']!==$previousRate)){
                    // A rate series with a discontinuity cannot be silently
                    // blended; it may reflect a later manual override.
                    $historyBasisValid=false;
                    break;
                }
                $previousRate=(int)$period['new_monthly_irr'];
            }
            if($estimateRequested && (($history && !$historyBasisValid) || (!$history && !$currentBasisValid))){
                $conflicts[]=[
                    'studentId'=>$studentId,
                    'studentName'=>trim((string)$member['student_first_name'].' '.(string)$member['student_last_name']),
                    'driverId'=>(int)$member['driver_id'],
                    'driverName'=>trim((string)$member['driver_first_name'].' '.(string)$member['driver_last_name']),
                    'serviceId'=>$serviceId,
                    'serviceName'=>(string)$member['title'],
                    'vehicleName'=>trim((string)$member['vehicle_model'].' '.(string)$member['vehicle_plate']),
                    'vehicleClass'=>$member['vehicle_class']===null?null:(int)$member['vehicle_class'],
                    'reasonCode'=>$history?'INVALID_HISTORIC_DRIVER_RATE':'DRIVER_BASE_MONTHLY_REQUIRED',
                    'reason'=>$history?'دوره تاریخی نرخ توافقی راننده دارای مبلغ نامعتبر است.':'مبنای ماهانه حقوق راننده ثبت نشده یا نامعتبر است.',
                    'severity'=>'blocking',
                    'effectiveDate'=>$from,
                    'recordedBasis'=>$member['driver_base_monthly'],
                    'suggestedAction'=>'مبنای توافقی حقوق راننده را در اطلاعات مالی دانش‌آموز و سرویس بررسی کنید.'
                ];
                continue;
            }
            $base=$currentBasisValid?(int)round((float)$member['driver_base_monthly']):0;
            $monthly=$estimateRequested && $history
                ?VehicleClassEffectiveDates::blendedRate($from,$to,$base,$history)
                :$base;
            $servicesMap[$serviceId]['student_count']++;
            $servicesMap[$serviceId]['monthly_gross']+=$monthly;
        }
        if($conflicts){
            throw new AuthException(409,'مبنای حقوق برخی دانش‌آموزان برای برآورد معتبر نیست؛ موارد را بررسی کنید.','DRIVER_BASE_MONTHLY_REQUIRED',[
                'period'=>['from'=>$from,'to'=>$to],
                'summary'=>['total'=>count($conflicts),'blocking'=>count($conflicts)],
                'conflicts'=>$conflicts
            ]);
        }
        $byDriver=[];
        foreach($servicesMap as $service)
            $byDriver[(int)$service['driver_id']][]=$service;
        $overrides=$filter['serviceDays']??[];
        if(is_string($overrides))$overrides=json_decode($overrides,true);
        if(!is_array($overrides)||count($overrides)>1000)
            throw new AuthException(422,'روزهای کارکرد سرویس معتبر نیست.','INVALID_SERVICE_DAYS');
        foreach($overrides as $serviceId=>$days){
            if(!ctype_digit((string)$serviceId)||!is_numeric($days)||floor((float)$days)!==(float)$days||(int)$days<0||(int)$days>30)
                throw new AuthException(422,'روزهای کارکرد هر سرویس باید عدد صحیح بین صفر تا ۳۰ باشد.','INVALID_SERVICE_DAYS');
        }
        foreach($rows as &$r){
            $serviceRows=[];$monthlyGross=0;$proratedGross=0;
            foreach($byDriver[(int)$r['id']]??[] as $service){
                $serviceId=(int)$service['service_id'];
                $days=array_key_exists($serviceId,$overrides)?(int)$overrides[$serviceId]:30;
                $amount=(int)round((float)$service['monthly_gross']);
                $prorated=ServiceFinancialBasis::prorate($amount,$days);
                $monthlyGross+=$amount;$proratedGross+=$prorated;
                $serviceRows[]=['serviceId'=>$serviceId,'title'=>$service['title'],
                  'code'=>$service['code'],'studentCount'=>(int)$service['student_count'],
                  'feeSource'=>$service['fee_source'],'monthlyGross'=>$amount,
                  'days'=>$days,'earnedGross'=>$prorated];
            }
            $r['service_details']=$serviceRows;
            $r['assigned_monthly_base']=$monthlyGross;
            $gross=(float)$r['earned'];
            $r['recorded_earned']=$gross;
            // Current assignments do not describe historical service dates. This
            // explicit, single-month estimate is never written to driver_work.
            $canEstimate=$estimateRequested && count($serviceRows)>0;
            if($canEstimate){
                $gross=(float)$proratedGross;
                $r['earned']=$gross;
            }
            $r['calculation_basis']=$canEstimate?'current_service_days_from_student_parent_fees':'recorded_driver_work';
            $r['estimate_requires_confirmation']=$canEstimate;
            $r['missing_work_records']=(int)$r['recorded_work_count']===0;
            $r['estimated_monthly_gross']=(float)$r['assigned_monthly_base'];
            $deductions=self::driverDeductions($gross,$aid);
            $r=array_merge($r,$deductions);
            $r['paid']=(float)$r['paid']+(float)$r['ledger_paid'];
            $r['gross_earned']=$gross;
            // Informational reconciliation ONLY: active assignments are a current
            // monthly reference, not historical work performed in this date range.
            // Never replace driver_work earnings or mutate approved payroll snapshots.
            $assignedMonthly=(float)$r['assigned_monthly_base'];
            $r['assigned_monthly_net']=self::driverDeductions($assignedMonthly,$aid)['net_earned'];
            $r['work_vs_assigned_monthly_delta']=(float)$r['recorded_earned']-$assignedMonthly;
            $r['reconciliation_basis']='current_active_assignments_monthly_vs_recorded_period_work';
            $r['reconciliation_requires_review']=abs((float)$r['recorded_earned']-$assignedMonthly)>=1;
            $r['balance']=$deductions['net_earned']-$r['paid'];
        }unset($r);
        return ['items'=>$rows,'from'=>$from,'to'=>$to,'calculationMode'=>$estimateRequested?'assignment_estimate':'recorded','estimatedOnly'=>$estimateRequested,'estimatedWarning'=>'برآورد بر اساس تخصیص‌های جاری و دوره‌های تاریخ‌دار نرخ خودرو است؛ سوابق کارکرد و اسناد قطعی تغییر نمی‌کنند. روزهای واقعی سرویس را پیش از تأیید کنترل کنید.'];
    }

    /** Rates are agency-specific; defaults apply when no policy has been saved. */
    public static function driverDeductions(float $gross, int $agencyId=0): array
    {
        return DriverDeductionPolicy::calculate(
            $gross,
            $agencyId>0?DriverDeductionPolicy::rates($agencyId):DriverDeductionPolicy::DEFAULTS
        );
    }

    public static function range(array $q): array
    {
        $from=(string)($q['from']??date('Y-m-01'));$to=(string)($q['to']??date('Y-m-t'));
        if(!self::validDate($from)||!self::validDate($to)||$from>$to)throw new AuthException(422,'بازه تاریخ معتبر انتخاب کنید.','VALIDATION_ERROR');return [$from,$to];
    }

    public static function ledgerReport(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'finance.view');[$from,$to]=self::range($filter);
        $q=$p->prepare('SELECT party_type,party_id,SUM(receipt_amount) receipts,SUM(payment_amount) payments FROM (
          SELECT party_type,party_id,CASE WHEN direction=\'receipt\' THEN amount ELSE 0 END receipt_amount,CASE WHEN direction=\'payment\' THEN amount ELSE 0 END payment_amount FROM ledger_entries WHERE agency_id=:a1 AND is_active=1 AND entry_date BETWEEN :f1 AND :t1
          UNION ALL SELECT COALESCE(i.party_type,\'invoice\'),COALESCE(i.party_id,i.id),p.amount,0 FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id WHERE p.agency_id=:a2 AND p.payment_date BETWEEN :f2 AND :t2
          UNION ALL SELECT \'driver\',driver_id,0,amount FROM driver_payments WHERE agency_id=:a3 AND payment_date BETWEEN :f3 AND :t3
        ) entries GROUP BY party_type,party_id ORDER BY party_type,party_id');$q->execute(['a1'=>$aid,'f1'=>$from,'t1'=>$to,'a2'=>$aid,'f2'=>$from,'t2'=>$to,'a3'=>$aid,'f3'=>$from,'t3'=>$to]);return ['items'=>$q->fetchAll(),'from'=>$from,'to'=>$to];
    }

    public static function schoolReport(int $uid,int $aid): array
    {
        $p=self::scope($uid,$aid,'reports.view');Authorization::requirePermission($uid,'schools.view');
        $q=$p->prepare('SELECT sc.id,sc.code,sc.name,sc.shift,sc.school_type,COUNT(st.id) students,SUM(CASE WHEN st.service_status=\'assigned\' THEN 1 ELSE 0 END) assigned,COALESCE(SUM(st.monthly_fee),0) monthly_fees FROM schools sc LEFT JOIN students st ON st.school_id=sc.id AND st.agency_id=sc.agency_id AND st.is_active=1 WHERE sc.agency_id=:a AND sc.is_active=1 GROUP BY sc.id,sc.code,sc.name,sc.shift,sc.school_type ORDER BY sc.name');$q->execute(['a'=>$aid]);return ['items'=>$q->fetchAll()];
    }

    public static function serviceSchools(int $uid,int $aid,int $service): array
    {
        self::scope($uid,$aid,'services.view');DomainService::get($uid,$aid,'services',$service);$q=Connection::get()->prepare('SELECT sc.id,sc.name,sc.latitude,sc.longitude FROM service_schools ss JOIN schools sc ON sc.id=ss.school_id WHERE ss.service_id=:s AND sc.agency_id=:a ORDER BY sc.name');$q->execute(['s'=>$service,'a'=>$aid]);return $q->fetchAll();
    }

    public static function setServiceSchools(int $uid,int $aid,int $service,array $body): array
    {
        self::scope($uid,$aid,'services.manage');$ids=self::ids($body['schoolIds']??[]);
        return self::transaction(function(PDO $p)use($uid,$aid,$service,$ids){
            $q=$p->prepare('SELECT id FROM services WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$service,'a'=>$aid]);if(!$q->fetchColumn())throw new AuthException(404,'سرویس پیدا نشد.','NOT_FOUND');
            foreach($ids as $id)DomainService::party($p,$aid,'school',$id);
            $q=$p->prepare('SELECT DISTINCT st.school_id FROM service_students ss JOIN students st ON st.id=ss.student_id WHERE ss.service_id=:s AND st.agency_id=:a');$q->execute(['s'=>$service,'a'=>$aid]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $school)if(!in_array((int)$school,$ids,true))throw new AuthException(409,'برای حذف این مدرسه، ابتدا تخصیص دانش‌آموزان آن را حذف کنید.','SCHOOL_MISMATCH');
            $p->prepare('DELETE FROM service_schools WHERE service_id=?')->execute([$service]);$q=$p->prepare('INSERT INTO service_schools(service_id,school_id) VALUES(?,?)');foreach($ids as $id)$q->execute([$service,$id]);$p->prepare('UPDATE services SET school_id=:school WHERE id=:id AND agency_id=:a')->execute(['school'=>$ids[0],'id'=>$service,'a'=>$aid]);self::audit($uid,$aid,'set_service_schools','services',$service);return ['schoolIds'=>$ids];
        });
    }

    public static function queueMessages(int $uid,int $aid,array $body): array
    {
        $p=self::scope($uid,$aid,'messages.manage');$type=(string)($body['recipientType']??'');$resource=['student'=>'students','driver'=>'drivers'][$type]??null;
        if(!$resource)throw new AuthException(422,'نوع گیرنده نامعتبر است.','VALIDATION_ERROR');
        Authorization::requirePermission($uid,$resource.'.view');$ids=self::ids($body['ids']??[]);$text=trim((string)($body['body']??''));
        if($text===''||strlen($text)>6000)throw new AuthException(422,'متن پیام را وارد کنید (حداکثر ۶۰۰۰ بایت).','VALIDATION_ERROR');
        self::transaction(function(PDO $p)use($aid,$uid,$type,$ids,$text){foreach($ids as $id){DomainService::party($p,$aid,$type,$id);DomainService::save($uid,$aid,'messages',['channel'=>'sms','recipientType'=>$type,'recipientId'=>$id,'body'=>$text,'status'=>'queued']);}});
        return ['queued'=>count($ids),'delivery'=>'pending_provider'];
    }

    public static function changeDriver(int $uid,int $aid,int $id,string $status): array
    {
        self::scope($uid,$aid,'services.manage');if(!in_array($status,['approved','rejected'],true))throw new AuthException(422,'وضعیت نامعتبر است.','VALIDATION_ERROR');
        return self::transaction(function(PDO $p)use($uid,$aid,$id,$status){
            $q=$p->prepare('SELECT * FROM driver_changes WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$id,'a'=>$aid]);$row=$q->fetch();
            if(!$row)throw new AuthException(404,'درخواست پیدا نشد.','NOT_FOUND');if($row['status']!=='pending')throw new AuthException(409,'این درخواست قبلاً بررسی شده است.','ALREADY_REVIEWED');
            if($status==='approved'){DomainService::party($p,$aid,'driver',(int)$row['new_driver_id']);$q=$p->prepare('SELECT driver_id FROM services WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$row['service_id'],'a'=>$aid]);$driver=$q->fetchColumn();if($driver===false)throw new AuthException(404,'سرویس پیدا نشد.','NOT_FOUND');if((int)$driver!==(int)$row['old_driver_id'])throw new AuthException(409,'راننده سرویس تغییر کرده است؛ درخواست جدید ثبت کنید.','STALE_DRIVER');$p->prepare('UPDATE services SET driver_id=:d WHERE id=:s AND agency_id=:a')->execute(['d'=>$row['new_driver_id'],'s'=>$row['service_id'],'a'=>$aid]);}
            $p->prepare('UPDATE driver_changes SET status=:s,reviewed_by=:u,reviewed_at=CURRENT_TIMESTAMP WHERE id=:id AND agency_id=:a')->execute(['s'=>$status,'u'=>$uid,'id'=>$id,'a'=>$aid]);self::audit($uid,$aid,'driver_change_'.$status,'driver_changes',$id);return ['status'=>$status];
        });
    }

    public static function grouping(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'services.view');Authorization::requirePermission($uid,'students.view');$school=(int)($filter['schoolId']??0);DomainService::party($p,$aid,'school',$school);
        $capacity=(int)($filter['capacity']??4);if($capacity<1||$capacity>50)throw new AuthException(422,'ظرفیت باید بین ۱ تا ۵۰ باشد.','VALIDATION_ERROR');
        $q=$p->prepare('SELECT st.id,st.first_name,st.last_name,st.latitude,st.longitude,st.address FROM students st WHERE st.agency_id=:a AND st.school_id=:s AND st.is_active=1 AND st.service_status<>\'cancelled\' AND NOT EXISTS(SELECT 1 FROM service_students ss JOIN services sv ON sv.id=ss.service_id WHERE ss.student_id=st.id AND sv.agency_id=st.agency_id AND sv.is_active=1) ORDER BY st.id LIMIT 1000');$q->execute(['a'=>$aid,'s'=>$school]);$rows=$q->fetchAll();$unlocated=[];$located=[];
        foreach($rows as $r){if($r['latitude']===null||$r['longitude']===null)$unlocated[]=$r;else $located[]=$r;}
        $groups=[];while($located){$group=[array_shift($located)];while(count($group)<$capacity&&$located){$last=$group[count($group)-1];$closest=0;$distance=INF;foreach($located as $i=>$r){$d=self::distance($last,$r);if($d<$distance){$distance=$d;$closest=$i;}}$group[]=$located[$closest];array_splice($located,$closest,1);}$groups[]=$group;}
        return ['groups'=>$groups,'unlocated'=>$unlocated,'algorithm'=>'nearest-neighbour straight-line distance; review before assigning'];
    }

    public static function createGroup(int $uid,int $aid,array $body): array
    {
        self::scope($uid,$aid,'services.manage');$ids=self::ids($body['ids']??[]);
        if(count($ids)>(int)($body['capacity']??0))throw new AuthException(422,'تعداد دانش‌آموزان از ظرفیت بیشتر است.','CAPACITY_EXCEEDED');
        return self::transaction(function()use($uid,$aid,$body,$ids){
            $service=DomainService::save($uid,$aid,'services',['title'=>$body['title']??'','schoolId'=>$body['schoolId']??null,'capacity'=>$body['capacity']??null,'status'=>'draft']);
            foreach($ids as $i=>$id)self::assign($uid,$aid,(int)$service['id'],$id,null,$i+1);
            return ['service'=>$service,'assigned'=>count($ids)];
        });
    }

    public static function distance(array $a,array $b): float
    {
        $lat1=deg2rad((float)$a['latitude']);$lat2=deg2rad((float)$b['latitude']);$dl=$lat2-$lat1;$dn=deg2rad((float)$b['longitude']-(float)$a['longitude']);$h=sin($dl/2)**2+cos($lat1)*cos($lat2)*sin($dn/2)**2;return 6371*2*asin(sqrt(min(1,max(0,$h))));
    }

    public static function distances(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'reports.view');Authorization::requirePermission($uid,'students.view');$threshold=(float)($filter['km']??20);
        if($threshold<=0||$threshold>1000)throw new AuthException(422,'فاصله نامعتبر است.','VALIDATION_ERROR');
        $q=$p->prepare('SELECT st.id,st.first_name,st.last_name,st.latitude,st.longitude,sc.name school,sc.latitude school_lat,sc.longitude school_lon FROM students st JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id WHERE st.agency_id=:a AND st.is_active=1 AND st.latitude IS NOT NULL AND st.longitude IS NOT NULL AND sc.latitude IS NOT NULL AND sc.longitude IS NOT NULL LIMIT 5000');$q->execute(['a'=>$aid]);$out=[];foreach($q as $r){$r['km']=round(self::distance($r,['latitude'=>$r['school_lat'],'longitude'=>$r['school_lon']]),2);if($r['km']>=$threshold)$out[]=$r;}return ['items'=>$out,'threshold'=>$threshold];
    }

    public static function audit(int $uid,int $aid,string $action,string $type,?int $id): void
    {
        $q=Connection::get()->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id) VALUES(:a,:u,:x,:t,:i)');$q->execute(['a'=>$aid,'u'=>$uid,'x'=>$action,'t'=>$type,'i'=>$id]);
    }

    public static function documents(int $uid,int $aid,int $driver): array
    {
        $p=self::scope($uid,$aid,'drivers.view');DomainService::party($p,$aid,'driver',$driver);
        $q=$p->prepare('SELECT id,document_type,document_no,expires_at,status FROM driver_documents WHERE driver_id=:d ORDER BY id DESC');$q->execute(['d'=>$driver]);return $q->fetchAll();
    }

    public static function saveDocument(int $uid,int $aid,int $driver,array $body,?int $id=null): array
    {
        $p=self::scope($uid,$aid,'drivers.manage');DomainService::party($p,$aid,'driver',$driver);
        $type=trim((string)($body['documentType']??''));$date=$body['expiresAt']??null;$status=$body['status']??'valid';
        if($type===''||strlen($type)>80||($date&&!self::validDate((string)$date))||!in_array($status,['valid','expired','revoked'],true))throw new AuthException(422,'نوع، تاریخ یا وضعیت مدرک نامعتبر است.','VALIDATION_ERROR');
        $data=['d'=>$driver,'t'=>$type,'n'=>$body['documentNo']??null,'e'=>$date?:null,'s'=>$status];
        if($id){$q=$p->prepare('SELECT id FROM driver_documents WHERE id=:id AND driver_id=:d');$q->execute(['id'=>$id,'d'=>$driver]);if(!$q->fetchColumn())throw new AuthException(404,'مدرک پیدا نشد.','NOT_FOUND');$data['id']=$id;$p->prepare('UPDATE driver_documents SET document_type=:t,document_no=:n,expires_at=:e,status=:s WHERE id=:id AND driver_id=:d')->execute($data);}
        else{$p->prepare('INSERT INTO driver_documents(driver_id,document_type,document_no,expires_at,status) VALUES(:d,:t,:n,:e,:s)')->execute($data);$id=(int)$p->lastInsertId();}
        self::audit($uid,$aid,'save_document','drivers',$driver);return ['id'=>$id];
    }
}

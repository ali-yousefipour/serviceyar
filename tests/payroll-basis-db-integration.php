<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
use ServiceYar\Domain\OperationsService;
use ServiceYar\Support\Uuid;

// Read/write only a disposable CI test database; roll back all fixtures.
if(getenv('SERVICEYAR_TEST_DATABASE')!=='1'
    ||!str_starts_with((string)config('DB_DATABASE'),'serviceyar_test')){
    throw new RuntimeException('Payroll integration requires explicit disposable test database');
}
$p=Connection::get();
$user=(int)$p->query("SELECT id FROM users WHERE username='preview'")->fetchColumn();
$agencyStmt=$p->prepare('SELECT agency_id FROM agency_users WHERE user_id=? LIMIT 1');
$agencyStmt->execute([$user]);$agency=(int)$agencyStmt->fetchColumn();
if(!$user||!$agency)throw new RuntimeException('Missing CI preview user/agency seed');
$q=$p->prepare("SELECT id,driver_id,school_id FROM services WHERE agency_id=? AND driver_id IS NOT NULL AND status='active' ORDER BY id LIMIT 1");
$q->execute([$agency]);$service=$q->fetch(PDO::FETCH_ASSOC);
$q=$p->prepare('SELECT id FROM students WHERE agency_id=? ORDER BY id LIMIT 1');
$q->execute([$agency]);$student=(int)$q->fetchColumn();
if(!$service||!$student)throw new RuntimeException('Missing CI service/student seed');

$from='2026-11-22';$to='2026-12-21';$driver=(int)$service['driver_id'];
$filter=['from'=>$from,'to'=>$to,'calculationMode'=>'assignment_estimate'];
function requireInvariant(bool $ok,string $message): void {
    if(!$ok)throw new RuntimeException($message);
}
function expectPayrollConflict(int $user,int $agency,array $filter,string $reason,int $student): array {
    try{
        OperationsService::payroll($user,$agency,$filter);
    }catch(AuthException $e){
        requireInvariant($e->status===409&&$e->codeName==='DRIVER_BASE_MONTHLY_REQUIRED','Expected financial basis HTTP 409');
        $items=$e->context['conflicts']??[];
        requireInvariant(is_array($items)&&count($items)>0,'Missing structured conflicts');
        foreach($items as $item){
            if(($item['reasonCode']??'')===$reason&&(int)($item['studentId']??0)===$student)
                return $e->context;
        }
        throw new RuntimeException('Missing expected conflict '.$reason.' for student '.$student);
    }
    throw new RuntimeException('Invalid payroll estimate was silently allowed');
}

$p->beginTransaction();
try{
    $p->prepare('UPDATE services SET created_at=? WHERE id=? AND agency_id=?')->execute(['2026-10-01 08:00:00',$service['id'],$agency]);
    $p->prepare('UPDATE students SET created_at=?,driver_base_monthly=NULL WHERE id=? AND agency_id=?')->execute(['2026-10-01 08:00:00',$student,$agency]);
    $p->prepare('INSERT IGNORE INTO service_students(service_id,student_id) VALUES(?,?)')->execute([$service['id'],$student]);
    $context=expectPayrollConflict($user,$agency,$filter,'DRIVER_BASE_MONTHLY_REQUIRED',$student);
    requireInvariant((int)$context['summary']['blocking']>=1,'Blocking conflict count missing');
    $p->prepare('UPDATE students SET driver_base_monthly=? WHERE id=? AND agency_id=?')->execute([1000000,$student,$agency]);
    $good=OperationsService::payroll($user,$agency,$filter);
    $row=array_values(array_filter($good['items'],static fn($r)=>(int)$r['id']===$driver))[0]??null;
    requireInvariant($row!==null&&(int)$row['gross_earned']>=1000000,'Valid driver remuneration not calculated');

    $draft=\ServiceYar\Domain\PayrollDocuments::create($user,$agency,[
        'from'=>$from,'to'=>$to,'calculationMode'=>'assignment_estimate',
        'confirmEstimate'=>true,'lines'=>[['driverId'=>$driver,'amount'=>800000]]
    ]);
    $docId=(int)$draft['id'];
    requireInvariant(str_starts_with((string)$draft['documentNo'],'EST-'),'Estimated payroll draft label missing');
    $p->prepare('UPDATE students SET driver_base_monthly=? WHERE id=? AND agency_id=?')
        ->execute([2000000,$student,$agency]);
    try{
        \ServiceYar\Domain\PayrollDocuments::approve($user,$agency,$docId,['confirmEstimate'=>true]);
        throw new RuntimeException('Stale salary estimate was approved');
    }catch(AuthException $e){
        requireInvariant($e->codeName==='PAYROLL_ESTIMATE_STALE','Unexpected approval rejection: '.$e->codeName);
    }
    $p->prepare('UPDATE students SET driver_base_monthly=? WHERE id=? AND agency_id=?')
        ->execute([1000000,$student,$agency]);
    $approved=\ServiceYar\Domain\PayrollDocuments::approve($user,$agency,$docId,['confirmEstimate'=>true]);
    requireInvariant(($approved['status']??'')==='approved','Unchanged estimate was not approved');
    try{
        \ServiceYar\Domain\PayrollDocuments::update($user,$agency,$docId,[
            'lines'=>[['driverId'=>$driver,'amount'=>1]]
        ]);
        throw new RuntimeException('Approved payroll could be altered');
    }catch(AuthException $e){
        requireInvariant($e->codeName==='PAYROLL_LOCKED','Approved document did not remain immutable');
    }

    $q=$p->prepare('SELECT vehicle_id FROM drivers WHERE id=? AND agency_id=?');
    $q->execute([$driver,$agency]);$vehicle=(int)$q->fetchColumn();
    requireInvariant($vehicle>0,'Missing CI driver vehicle');
    $p->prepare("INSERT INTO vehicle_class_changes
        (agency_id,driver_id,vehicle_id,old_vehicle_model,new_vehicle_model,old_vehicle_class,new_vehicle_class,
         contract_effective_date,update_student_debt,update_driver_pay,driver_pay_effective_date,
         old_vehicle_snapshot,new_vehicle_snapshot,decision_snapshot,created_by)
        VALUES(?,?,?,'قدیم','جدید',1,2,NULL,0,1,'2026-12-07','{}','{}','{}',?)")
       ->execute([$agency,$driver,$vehicle,$user]);
    $change=(int)$p->lastInsertId();
    $p->prepare("INSERT INTO vehicle_class_rate_periods
        (agency_id,change_id,subject_type,subject_id,service_id,effective_date,old_class,new_class,
         old_monthly_irr,new_monthly_irr,fee_source,calculation_snapshot)
        VALUES(?,?,'driver_pay',?,?,'2026-12-07',1,2,1000000,2000000,'agreed','{}')")
       ->execute([$agency,$change,$student,$service['id']]);
    $dated=OperationsService::payroll($user,$agency,$filter);
    $rateRow=array_values(array_filter($dated['items'],static fn($r)=>(int)$r['id']===$driver))[0]??null;
    requireInvariant((int)($rateRow['gross_earned']??0)===1500000,
        'Signed mid-month rate must be blended over the thirty-day statement');

    // The signed period must never be incorrectly applied to another driver.
    $p->prepare('UPDATE vehicle_class_changes SET driver_id=? WHERE id=?')->execute([$driver+100000,$change]);
    $unrelated=OperationsService::payroll($user,$agency,$filter);
    $unrelatedRow=array_values(array_filter($unrelated['items'],static fn($r)=>(int)$r['id']===$driver))[0]??null;
    requireInvariant((int)($unrelatedRow['gross_earned']??0)===1000000,
        'Old driver rate period leaked into another driver');

    $p->prepare('UPDATE students SET created_at=? WHERE id=? AND agency_id=?')->execute(['2026-11-25 08:00:00',$student,$agency]);
    expectPayrollConflict($user,$agency,$filter,'HISTORICAL_SERVICE_DAYS_UNVERIFIED',$student);
    $p->prepare('UPDATE students SET created_at=? WHERE id=? AND agency_id=?')->execute(['2026-10-01 08:00:00',$student,$agency]);

    $p->prepare("INSERT INTO services(uuid,agency_id,title,school_id,driver_id,capacity,status,is_active,created_at)
        VALUES(?,?,?, ?,?,1,'active',1,'2026-10-01 08:00:00')")
      ->execute([Uuid::v4(),$agency,'سرویس موازی آزمون',$service['school_id'],$driver]);
    $parallelService=(int)$p->lastInsertId();
    $p->prepare('INSERT INTO service_students(service_id,student_id) VALUES(?,?)')
      ->execute([$parallelService,$student]);
    expectPayrollConflict($user,$agency,$filter,'DUPLICATE_ACTIVE_SERVICE_ASSIGNMENT',$student);
    $p->prepare('DELETE FROM service_students WHERE service_id=? AND student_id=?')
      ->execute([$parallelService,$student]);
    $p->prepare('DELETE FROM services WHERE id=? AND agency_id=?')
      ->execute([$parallelService,$agency]);

    $p->prepare("INSERT INTO driver_changes
        (agency_id,service_id,old_driver_id,new_driver_id,status,reviewed_by,reviewed_at,created_at)
        VALUES(?,?,?,?,'approved',?,'2026-12-05 12:00:00','2026-12-04 12:00:00')")
        ->execute([$agency,$service['id'],$driver,$driver+100000,$user]);
    expectPayrollConflict($user,$agency,$filter,'HISTORICAL_DRIVER_ASSIGNMENT_UNVERIFIED',$student);
    // Recorded-work figures are independent of optional estimate history.
    $recorded=OperationsService::payroll($user,$agency,['from'=>$from,'to'=>$to,'calculationMode'=>'recorded']);
    requireInvariant(isset($recorded['items'])&&is_array($recorded['items']),'Recorded-payroll path blocked by estimate conflicts');

    $p->rollBack();
    echo "Payroll financial-basis, dated rates, driver ownership and history DB integration: OK\n";
}catch(Throwable $e){
    if($p->inTransaction())$p->rollBack();
    throw $e;
}

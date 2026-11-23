<?php
declare(strict_types=1);
require __DIR__.'/../backend/src/bootstrap.php';

use ServiceYar\Domain\VehicleClassSchema;

$dsn=getenv('SERVICEYAR_TEST_DSN')?:'';
if($dsn==='')throw new RuntimeException('SERVICEYAR_TEST_DSN required');
$pdo=new PDO($dsn,getenv('SERVICEYAR_TEST_USER')?:'root',getenv('SERVICEYAR_TEST_PASSWORD')?:'',
 [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE IF NOT EXISTS agencies(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('INSERT IGNORE INTO agencies(id) VALUES(1)');
$pdo->exec('CREATE TABLE IF NOT EXISTS agency_settings(agency_id BIGINT UNSIGNED NOT NULL, setting_key VARCHAR(100) NOT NULL, setting_value TEXT NULL, PRIMARY KEY(agency_id,setting_key)) ENGINE=InnoDB');
VehicleClassSchema::ensure($pdo);
foreach(['vehicle_class_changes','vehicle_class_rate_periods','vehicle_class_contract_appendices','vehicle_class_debt_adjustments'] as $table)
  $pdo->query("SELECT id FROM $table LIMIT 0");
$pdo->beginTransaction();
try{
  $q=$pdo->prepare("INSERT INTO vehicle_class_changes
  (agency_id,driver_id,vehicle_id,old_vehicle_model,new_vehicle_model,old_vehicle_class,new_vehicle_class,
   contract_effective_date,update_student_debt,student_debt_effective_date,update_driver_pay,driver_pay_effective_date,
   old_vehicle_snapshot,new_vehicle_snapshot,decision_snapshot,created_by)
   VALUES(1,11,22,'پراید','ساینا',1,2,'2026-11-22',1,'2026-11-22',1,'2026-11-25','{}','{}','{}',99)");
  $q->execute();$changeId=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO vehicle_class_rate_periods
  (agency_id,change_id,subject_type,subject_id,service_id,effective_date,old_class,new_class,old_monthly_irr,new_monthly_irr,fee_source,calculation_snapshot)
  VALUES(1,?,'driver_pay',101,102,'2026-11-25',1,2,1000000,1400000,'taxi','{}')")->execute([$changeId]);
  $pdo->prepare("INSERT INTO vehicle_class_contract_appendices
  (agency_id,change_id,contract_id,effective_date,old_class,new_class,original_contract_snapshot)
  VALUES(1,?,103,'2026-11-22',1,2,'{}')")->execute([$changeId]);
  $pdo->prepare("INSERT INTO vehicle_class_debt_adjustments
  (agency_id,change_id,student_id,service_id,academic_year,effective_date,amount_irr,rate_snapshot)
  VALUES(1,?,101,102,'1405-1406','2026-11-22',-300000,'{}')")->execute([$changeId]);
  $total=(int)$pdo->query('SELECT SUM(amount_irr) FROM vehicle_class_debt_adjustments')->fetchColumn();
  if($total!==-300000)throw new RuntimeException('Signed credit adjustment failed');
  $pdo->rollBack();
  $count=(int)$pdo->query('SELECT COUNT(*) FROM vehicle_class_changes')->fetchColumn();
  if($count!==0)throw new RuntimeException('Atomic audit rollback failed');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
// Real deferred-rate application; no finalized invoice or contract is touched.
$pdo->exec("CREATE TABLE IF NOT EXISTS students(
 id BIGINT UNSIGNED PRIMARY KEY,agency_id BIGINT UNSIGNED NOT NULL,
 monthly_fee BIGINT NOT NULL,tariff_vehicle_class TINYINT NULL,
 driver_base_monthly BIGINT NULL,is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB");
$pdo->exec("INSERT INTO students(id,agency_id,monthly_fee,tariff_vehicle_class,driver_base_monthly)
 VALUES(101,1,1000000,1,800000)");
$pdo->exec("CREATE TABLE IF NOT EXISTS services(
 id BIGINT UNSIGNED PRIMARY KEY,agency_id BIGINT UNSIGNED NOT NULL,
 driver_id BIGINT UNSIGNED NULL,
 is_active TINYINT NOT NULL DEFAULT 1,status VARCHAR(20) NOT NULL DEFAULT 'active',
 monthly_fee BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS service_students(
 service_id BIGINT UNSIGNED NOT NULL,student_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(service_id,student_id)
) ENGINE=InnoDB");
$pdo->exec("INSERT INTO services(id,agency_id,driver_id,is_active,status) VALUES(102,1,11,1,'active')");
$pdo->exec("INSERT INTO service_students(service_id,student_id) VALUES(102,101)");
$pdo->exec("INSERT INTO vehicle_class_changes
 (agency_id,driver_id,vehicle_id,old_vehicle_model,new_vehicle_model,old_vehicle_class,new_vehicle_class,
 contract_effective_date,update_student_debt,student_debt_effective_date,update_driver_pay,driver_pay_effective_date,
 old_vehicle_snapshot,new_vehicle_snapshot,decision_snapshot,created_by)
 VALUES(1,11,22,'پراید','ساینا',1,2,NULL,1,'2000-01-01',0,NULL,'{}','{}','{}',99)");
$change=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO vehicle_class_rate_periods
 (agency_id,change_id,subject_type,subject_id,service_id,effective_date,old_class,new_class,
 old_monthly_irr,new_monthly_irr,fee_source,calculation_snapshot)
 VALUES(1,?,'student_debt',101,102,'2000-01-01',1,2,1000000,1400000,'taxi','{}')")
 ->execute([$change]);
$pdo->exec("INSERT INTO vehicle_class_changes
 (agency_id,driver_id,vehicle_id,old_vehicle_model,new_vehicle_model,old_vehicle_class,new_vehicle_class,
 contract_effective_date,update_student_debt,student_debt_effective_date,update_driver_pay,driver_pay_effective_date,
 old_vehicle_snapshot,new_vehicle_snapshot,decision_snapshot,created_by)
 VALUES(1,11,23,'ساینا','دنا',2,3,NULL,1,'2099-01-01',0,NULL,'{}','{}','{}',99)");
$futureChange=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO vehicle_class_rate_periods
 (agency_id,change_id,subject_type,subject_id,service_id,effective_date,old_class,new_class,
 old_monthly_irr,new_monthly_irr,fee_source,calculation_snapshot)
 VALUES(1,?,'student_debt',101,102,'2099-01-01',2,3,1400000,1800000,'taxi','{}')")
 ->execute([$futureChange]);
$property=new ReflectionProperty(\ServiceYar\Database\Connection::class,'pdo');
$property->setValue(null,$pdo);
$applied=\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1);
if($applied!==1)throw new RuntimeException('Expected exactly one due period');
$row=$pdo->query('SELECT monthly_fee,tariff_vehicle_class FROM students WHERE id=101')->fetch();
if((int)$row['monthly_fee']!==1400000||(int)$row['tariff_vehicle_class']!==2)
 throw new RuntimeException('Due class did not update student tuition');
if(\ServiceYar\Domain\ServiceFinancialBasis::grossOfService($pdo,1,102)!==800000)
 throw new RuntimeException('Driver remuneration was incorrectly taken from parent tuition');
$pdo->exec('UPDATE students SET driver_base_monthly=NULL WHERE id=101');
try{
 \ServiceYar\Domain\ServiceFinancialBasis::grossOfService($pdo,1,102);
 throw new RuntimeException('Missing driver rate was silently replaced by parent tuition');
}catch(\ServiceYar\Auth\AuthException $e){
 if($e->codeName!=='DRIVER_BASE_MONTHLY_REQUIRED')throw $e;
}
$pdo->exec('UPDATE students SET driver_base_monthly=800000 WHERE id=101');

// Same service, different driver: a dated approval for the former
// driver must NEVER reprice the successor's students or salary.
$pdo->exec("INSERT INTO vehicle_class_changes
 (agency_id,driver_id,vehicle_id,old_vehicle_model,new_vehicle_model,old_vehicle_class,new_vehicle_class,
 contract_effective_date,update_student_debt,student_debt_effective_date,update_driver_pay,driver_pay_effective_date,
 old_vehicle_snapshot,new_vehicle_snapshot,decision_snapshot,created_by)
 VALUES(1,11,22,'پراید','ساینا',1,2,NULL,0,NULL,1,'2001-01-01','{}','{}','{}',99)");
$oldDriverChange=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO vehicle_class_rate_periods
 (agency_id,change_id,subject_type,subject_id,service_id,effective_date,old_class,new_class,
 old_monthly_irr,new_monthly_irr,fee_source,calculation_snapshot)
 VALUES(1,?,'driver_pay',101,102,'2001-01-01',1,2,800000,1600000,'taxi','{}')")
 ->execute([$oldDriverChange]);
$pdo->exec('UPDATE services SET driver_id=12 WHERE id=102');
if(\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1)!==0)
 throw new RuntimeException('Former driver rate was applied to newly assigned driver');
if((int)$pdo->query("SELECT driver_base_monthly FROM students WHERE id=101")->fetchColumn()!==800000)
 throw new RuntimeException('Wrong driver changed student payroll basis');
if((int)$pdo->query("SELECT monthly_fee FROM services WHERE id=102")->fetchColumn()!==0)
 throw new RuntimeException('A successor driver inherited an old driver's service recalculation');
if((int)$pdo->query("SELECT COUNT(*) FROM vehicle_class_rate_periods WHERE change_id=$oldDriverChange AND applied_at IS NULL")->fetchColumn()!==1)
 throw new RuntimeException('Former driver rate must remain pending for reconciliation');
$pdo->exec('UPDATE services SET driver_id=11 WHERE id=102');
if(\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1)!==1)
 throw new RuntimeException('Reinstated approved driver did not receive its due rate');
if((int)$pdo->query("SELECT driver_base_monthly FROM students WHERE id=101")->fetchColumn()!==1600000)
 throw new RuntimeException('Approved driver remuneration not applied');
$expectedNet=(int)\ServiceYar\Domain\OperationsService::driverDeductions(1600000,1)['net_earned'];
if((int)$pdo->query("SELECT monthly_fee FROM services WHERE id=102")->fetchColumn()!==$expectedNet)
 throw new RuntimeException('Stored service payable did not follow approved dated driver remuneration');
$pdo->exec('UPDATE students SET driver_base_monthly=800000 WHERE id=101');

if(\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1)!==0)
 throw new RuntimeException('Due rate was applied twice');
$future=(int)$pdo->query("SELECT COUNT(*) FROM vehicle_class_rate_periods WHERE applied_at IS NULL")->fetchColumn();
if($future!==1)throw new RuntimeException('Future rate should remain pending');
// A period belonging to a former service must not modify the current student's
// parent charge or payroll rate. Reconciliation is required, not auto-application.
$pdo->exec("INSERT INTO vehicle_class_changes
 (agency_id,driver_id,vehicle_id,old_vehicle_model,new_vehicle_model,old_vehicle_class,new_vehicle_class,
 contract_effective_date,update_student_debt,student_debt_effective_date,update_driver_pay,driver_pay_effective_date,
 old_vehicle_snapshot,new_vehicle_snapshot,decision_snapshot,created_by)
 VALUES(1,11,24,'ساینا','دنا',2,3,NULL,1,'2001-01-01',0,NULL,'{}','{}','{}',99)");
$staleChange=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO vehicle_class_rate_periods
 (agency_id,change_id,subject_type,subject_id,service_id,effective_date,old_class,new_class,
 old_monthly_irr,new_monthly_irr,fee_source,calculation_snapshot)
 VALUES(1,?,'student_debt',101,999,'2001-01-01',2,3,1400000,9999999,'taxi','{}')")
 ->execute([$staleChange]);
if(\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1)!==0)
 throw new RuntimeException('Orphaned rate incorrectly applied to a new student assignment');
if((int)$pdo->query("SELECT COUNT(*) FROM vehicle_class_rate_periods WHERE change_id=$staleChange AND applied_at IS NULL")->fetchColumn()!==1)
 throw new RuntimeException('Unreconciled orphaned rate should remain pending');
if((int)$pdo->query('SELECT monthly_fee FROM students WHERE id=101')->fetchColumn()!==1400000)
 throw new RuntimeException("Stale adjustment modified the student's tuition");

// Two successive overdue approved rates must be applied in the same
// transaction/request, using the actual preceding amount as their base.
// A manual change of the rate after approval must remain untouched until
// it is explicitly reconciled with the approved financial record.
$pdo->exec("INSERT INTO students(id,agency_id,monthly_fee,tariff_vehicle_class,driver_base_monthly)
 VALUES(201,1,1000000,1,700000)");
$pdo->exec("INSERT INTO services(id,agency_id,driver_id,is_active,status)
 VALUES(202,1,11,1,'active')");
$pdo->exec('INSERT INTO service_students(service_id,student_id) VALUES(202,201)');
$chainSql="INSERT INTO vehicle_class_changes
 (agency_id,driver_id,vehicle_id,old_vehicle_model,new_vehicle_model,old_vehicle_class,new_vehicle_class,
 contract_effective_date,update_student_debt,student_debt_effective_date,update_driver_pay,driver_pay_effective_date,
 old_vehicle_snapshot,new_vehicle_snapshot,decision_snapshot,created_by)
 VALUES(1,11,22,'پراید','ساینا',1,2,NULL,1,'2000-01-01',0,NULL,'{}','{}','{}',99)";
$chainRateSql="INSERT INTO vehicle_class_rate_periods
 (agency_id,change_id,subject_type,subject_id,service_id,effective_date,old_class,new_class,
 old_monthly_irr,new_monthly_irr,fee_source,calculation_snapshot)
 VALUES(1,?,'student_debt',201,202,?,1,2,?,?,'taxi','{}')";
$chainRate=$pdo->prepare($chainRateSql);
foreach([['2000-01-01',1000000,1400000],['2000-02-01',1400000,1800000]] as [$day,$oldBase,$newBase]){
 $pdo->exec($chainSql);$changeId=(int)$pdo->lastInsertId();
 $chainRate->execute([$changeId,$day,$oldBase,$newBase]);
}
if(\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1)!==2)
 throw new RuntimeException('Consecutive approved rates were not applied in a single request');
if((int)$pdo->query('SELECT monthly_fee FROM students WHERE id=201')->fetchColumn()!==1800000)
 throw new RuntimeException('Consecutive approved tuition rate did not reach the final amount');
$pdo->exec($chainSql);$manualChange=(int)$pdo->lastInsertId();
$chainRate->execute([$manualChange,'2000-03-01',1800000,2000000]);
$pdo->exec('UPDATE students SET monthly_fee=1700000 WHERE id=201');
if(\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1)!==0)
 throw new RuntimeException('Manually overridden tuition was silently replaced by a later scheduled rate');
if((int)$pdo->query("SELECT COUNT(*) FROM vehicle_class_rate_periods WHERE change_id=$manualChange AND applied_at IS NULL")->fetchColumn()!==1)
 throw new RuntimeException('Unreconciled scheduled tuition rate was not preserved for review');
$pdo->exec('UPDATE students SET monthly_fee=1800000 WHERE id=201');
if(\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1)!==1)
 throw new RuntimeException('A reconciled scheduled rate was not applied');
if((int)$pdo->query('SELECT monthly_fee FROM students WHERE id=201')->fetchColumn()!==2000000)
 throw new RuntimeException('Reconciled scheduled tuition amount did not take effect');

// Accounting invariant with an issued invoice, prior receipts, signed and
// future-dated adjustments. Historical invoice and cash must not be rewritten.
$pdo->exec("CREATE TABLE invoices(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,party_type VARCHAR(20) NOT NULL,
 party_id BIGINT UNSIGNED NOT NULL,amount BIGINT NOT NULL,status VARCHAR(20) NOT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE payments(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,invoice_id BIGINT UNSIGNED NOT NULL,
 amount BIGINT NOT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE ledger_entries(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,party_type VARCHAR(20) NOT NULL,
 party_id BIGINT UNSIGNED NOT NULL,direction VARCHAR(20) NOT NULL,
 amount BIGINT NOT NULL,is_active TINYINT NOT NULL) ENGINE=InnoDB");
$pdo->exec("INSERT INTO invoices(agency_id,party_type,party_id,amount,status)
 VALUES(1,'student',101,2000000,'open')");
$pdo->exec("INSERT INTO payments(agency_id,invoice_id,amount) VALUES(1,1,500000)");
$pdo->exec("INSERT INTO ledger_entries(agency_id,party_type,party_id,direction,amount,is_active)
 VALUES(1,'student',101,'receipt',100000,1)");
$debtSql="INSERT INTO vehicle_class_debt_adjustments
 (agency_id,change_id,student_id,service_id,academic_year,effective_date,amount_irr,rate_snapshot)
 VALUES(1,?,101,102,'1405-1406',?,?,'{}')";
$pdo->prepare($debtSql)->execute([$change,'2000-01-01',600000]);
$pdo->prepare($debtSql)->execute([$futureChange,'2099-01-01',-200000]);
$amounts=$pdo->query("SELECT ".\ServiceYar\Domain\StudentBalance::invoicesSql()." charges,
 ".\ServiceYar\Domain\StudentBalance::receiptsSql()." received
 FROM students WHERE id=101 AND agency_id=1")->fetch(PDO::FETCH_ASSOC);
if((int)$amounts['charges']!==2600000||(int)$amounts['received']!==600000)
 throw new RuntimeException('Issued invoice, paid amount, future adjustment or signed debt mismatch');
if((int)$amounts['charges']-(int)$amounts['received']!==2000000)
 throw new RuntimeException('Student balance must reconcile to the immutable invoice and actual cash');
try{
 $pdo->prepare($debtSql)->execute([$change,'2000-01-01',600000]);
 throw new RuntimeException('Duplicate debt memo accepted');
}catch(PDOException $e){
 if((string)$e->getCode()!=='23000')throw $e;
}
if((int)$pdo->query('SELECT COUNT(*) FROM vehicle_class_debt_adjustments')->fetchColumn()!==2)
 throw new RuntimeException('Duplicate debt adjustment was persisted');
if((int)$pdo->query('SELECT amount FROM invoices WHERE id=1')->fetchColumn()!==2000000)
 throw new RuntimeException('Issued invoice was rewritten');
if((int)$pdo->query('SELECT amount FROM payments WHERE id=1')->fetchColumn()!==500000)
 throw new RuntimeException('Existing student payment was rewritten');

echo "Vehicle class schema and signed adjustment DB smoke: OK\n";

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
if(\ServiceYar\Domain\VehicleClassRateApplication::applyDue(1)!==0)
 throw new RuntimeException('Due rate was applied twice');
$future=(int)$pdo->query("SELECT COUNT(*) FROM vehicle_class_rate_periods WHERE applied_at IS NULL")->fetchColumn();
if($future!==1)throw new RuntimeException('Future rate should remain pending');
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

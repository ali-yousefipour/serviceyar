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
echo "Vehicle class schema and signed adjustment DB smoke: OK\n";

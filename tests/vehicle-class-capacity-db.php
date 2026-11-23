<?php
declare(strict_types=1);
require __DIR__.'/../backend/src/bootstrap.php';

use ServiceYar\Auth\AuthException;
use ServiceYar\Domain\VehicleClassChangeWorkflow;

$dsn=getenv('SERVICEYAR_TEST_DSN')?:'';
if($dsn==='')throw new RuntimeException('SERVICEYAR_TEST_DSN required');
$pdo=new PDO($dsn,getenv('SERVICEYAR_TEST_USER')?:'root',getenv('SERVICEYAR_TEST_PASSWORD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
// Temporary tables isolate this fixture even when the test database has data.
$pdo->exec('CREATE TEMPORARY TABLE services(id INT,agency_id INT,driver_id INT,is_active INT,status VARCHAR(20))');
$pdo->exec('CREATE TEMPORARY TABLE students(id INT,agency_id INT,is_active INT)');
$pdo->exec('CREATE TEMPORARY TABLE service_students(service_id INT,student_id INT)');
$pdo->exec("INSERT INTO services VALUES(1,1,31,1,'active'),(2,1,31,1,'cancelled'),(3,2,31,1,'active'),(4,1,31,0,'active'),(5,1,32,1,'active')");
$pdo->exec('INSERT INTO students VALUES(1,1,1),(2,1,1),(3,1,1),(4,1,1),(5,1,1),(6,1,0),(7,2,1)');
$check=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
$check(count(VehicleClassChangeWorkflow::capacityWarnings($pdo,1,31,4))===1,'Empty service must warn, not block');
$pdo->exec('INSERT INTO service_students VALUES(1,1),(1,2),(1,3),(1,3),(1,6),(1,7),(1,999)');
foreach([2,3,4,5] as $id)$pdo->exec("INSERT INTO service_students VALUES($id,1),($id,2),($id,3),($id,4),($id,5)");
$check(count(VehicleClassChangeWorkflow::capacityWarnings($pdo,1,31,4))===1,'Three real passengers must warn despite duplicate, inactive, foreign or orphan rows');
$pdo->exec('INSERT INTO service_students VALUES(1,4)');
$check(VehicleClassChangeWorkflow::capacityWarnings($pdo,1,31,4)===[],'Equal capacity must succeed without warning');
$pdo->exec('INSERT INTO service_students VALUES(1,5)');
try{
    VehicleClassChangeWorkflow::capacityWarnings($pdo,1,31,4);
    throw new RuntimeException('Five active passengers in four seats must block');
}catch(AuthException $e){
    $check($e->status===409&&$e->codeName==='VEHICLE_CAPACITY_TOO_SMALL','Wrong overflow error');
    $check($e->context===['serviceId'=>1,'studentCount'=>5,'vehicleCapacity'=>4],'Overflow must identify real counts');
}
echo "Vehicle class capacity DB rules: OK\n";

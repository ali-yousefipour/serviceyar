<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Database\Connection;
use ServiceYar\Domain\DomainService as D;
use ServiceYar\Support\Uuid;
if(getenv('SERVICEYAR_TEST_DATABASE')!=='1'||!str_starts_with((string)config('DB_DATABASE'),'serviceyar_test'))throw new RuntimeException('Preview fixtures require a serviceyar_test* database and explicit test opt-in.');
$p=Connection::get();
if($p->query("SELECT id FROM users WHERE username='preview'")->fetchColumn()){echo "Preview already seeded.\n";return;}
$p->beginTransaction();
try{
 $p->prepare('INSERT INTO users(uuid,username,password_hash,first_name,last_name) VALUES(?,?,?,?,?)')->execute([Uuid::v4(),'preview',password_hash('Preview-Only-2026!',PASSWORD_DEFAULT),'کاربر','آزمایشی']);$uid=(int)$p->lastInsertId();
 $p->prepare("INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE name='super_admin'")->execute([$uid]);
 $p->prepare('INSERT INTO agencies(uuid,name) VALUES(?,?)')->execute([Uuid::v4(),'شرکت نمونه سرویسیار']);$aid=(int)$p->lastInsertId();$p->prepare('INSERT INTO agency_users(agency_id,user_id) VALUES(?,?)')->execute([$aid,$uid]);
 $school=D::save($uid,$aid,'schools',['name'=>'مدرسه نمونه','code'=>'101','gender'=>'mixed','schoolType'=>'غیردولتی','shift'=>'صبح','region'=>'یک','latitude'=>36.3,'longitude'=>59.6]);
 $vehicle=D::save($uid,$aid,'vehicles',['plate'=>'۱۲ الف ۳۴۵','model'=>'سواری','capacity'=>4]);
 $driver=D::save($uid,$aid,'drivers',['firstName'=>'راننده','lastName'=>'نمونه','mobile'=>'09120000000','status'=>'active','vehicleId'=>$vehicle['id']]);
 D::save($uid,$aid,'services',['title'=>'سرویس صبح نمونه','code'=>'S-101','schoolId'=>$school['id'],'driverId'=>$driver['id'],'capacity'=>4,'status'=>'active','agreedAmount'=>30000000]);
 foreach(['آرمان','مینا','سارا'] as $i=>$name)D::save($uid,$aid,'students',['firstName'=>$name,'lastName'=>'آزمایشی','schoolId'=>$school['id'],'mobile'=>'0912000000'.($i+1),'guardianFirstName'=>'ولی','guardianLastName'=>'نمونه','academicYear'=>'1405/1406','monthlyFee'=>1000000,'serviceStatus'=>'waiting','latitude'=>36.31+$i/1000,'longitude'=>59.61+$i/1000]);
 $p->commit();echo "Local preview fixtures created.\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}

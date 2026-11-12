<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Domain\MonthlyStatements;
use ServiceYar\Auth\AuthException;

$range=new ReflectionMethod(MonthlyStatements::class,'range');
foreach([
 [1405,7,'2026-09-23','2026-10-22'],
 [1405,8,'2026-10-23','2026-11-21'],
 [1405,12,'2027-02-20','2027-03-20'],
 [1406,1,'2027-03-21','2027-04-20'],
] as [$year,$month,$from,$to]){
 $actual=$range->invoke(null,['year'=>$year,'month'=>$month,'from'=>$from,'to'=>$to]);
 if($actual!==[$year,$month,$from,$to])throw new RuntimeException('Jalali month boundary failed: '.$year.'/'.$month);
}
try{
 $range->invoke(null,['year'=>1405,'month'=>7,'from'=>'2026-09-23','to'=>'2027-09-22']);
 throw new RuntimeException('Year-long period unexpectedly accepted');
}catch(AuthException $e){
 if($e->status!==422)throw $e;
}
echo "Monthly statement date validation passed\n";

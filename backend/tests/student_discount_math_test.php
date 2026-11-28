<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Domain/StudentDiscountMath.php';
use ServiceYar\Domain\StudentDiscountMath as M;
function check(array $actual,array $expected): void {
    if ($actual !== $expected) throw new RuntimeException(json_encode([$actual,$expected]));
}
check(M::calculate(50000000,40000000,20000,true,true),['studentDiscount'=>10000000,'driverDiscount'=>8000000,'studentNet'=>40000000,'driverNet'=>32000000,'companyShare'=>8000000]);
check(M::calculate(50000000,40000000,20000,true,false),['studentDiscount'=>10000000,'driverDiscount'=>0,'studentNet'=>40000000,'driverNet'=>40000000,'companyShare'=>0]);
check(M::calculate(50000000,40000000,20000,false,true),['studentDiscount'=>0,'driverDiscount'=>8000000,'studentNet'=>50000000,'driverNet'=>32000000,'companyShare'=>18000000]);
check(M::calculate(101,99,12500,true,true),['studentDiscount'=>13,'driverDiscount'=>12,'studentNet'=>88,'driverNet'=>87,'companyShare'=>1]);
foreach ([[-1,0,20000,true,true],[1,1,100001,true,true],[1,1,20000,false,false]] as $args) {
    try { M::calculate(...$args); } catch (InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Invalid input accepted');
}
echo "Student discount arithmetic acceptance tests passed\n";

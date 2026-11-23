<?php
declare(strict_types=1);
require __DIR__.'/../backend/src/Auth/AuthException.php';
require __DIR__.'/../backend/src/Domain/ServiceFinancialBasis.php';
require __DIR__.'/../backend/src/Domain/DriverDeductionPolicy.php';

use ServiceYar\Domain\ServiceFinancialBasis as Finance;
use ServiceYar\Domain\DriverDeductionPolicy as Deductions;
use ServiceYar\Auth\AuthException;

function same(mixed $want,mixed $got,string $message): void {
    if($want!==$got)throw new RuntimeException($message.': '.json_encode([$want,$got]));
}
same(30000000,Finance::prorate(30000000,30),'Full month');
same(15000000,Finance::prorate(30000000,15),'Half month');
same(0,Finance::prorate(30000000,0),'No service');
same(9999999,Finance::prorate(29999997,10),'Precise thirty-day pro rata');
foreach([-1,31] as $invalid) {
    try{Finance::prorate(1000000,$invalid);throw new RuntimeException('Expected rejection');}
    catch(AuthException $e){same('INVALID_SERVICE_DAYS',$e->codeName,'Days boundaries');}
}
$policy=Deductions::calculate(100000000,Deductions::DEFAULTS);
same(3000000,(int)$policy['supervision_deduction'],'Supervision 3%');
same(1000000,(int)$policy['coordination_deduction'],'Coordination 1%');
same(7000000,(int)$policy['overhead_deduction'],'Overhead 7%');
same(7800000,(int)$policy['insurance_deduction'],'Insurance 7.8%');
same(18800000,(int)$policy['total_deductions'],'Combined 18.8%');
same(81200000,(int)$policy['net_earned'],'Net from driver gross');
same(8.5,Finance::monthsOfStudent(['school_type'=>'دبستان','grade'=>'اول']),'Primary duration');
same(9.0,Finance::monthsOfStudent(['school_type'=>'متوسطه اول','grade'=>'1']),'Secondary precedence');
same(8.5,Finance::monthsOfStudent(['school_type'=>'غیردولتی','education_levels'=>'دبستان','grade'=>'اول']),'Private primary');
same(9.0,Finance::monthsOfStudent(['school_type'=>'غیردولتی','education_levels'=>'متوسطه دوره اول','grade'=>'اول']),'Private secondary');
same(9.0,Finance::monthsOfStudent(['education_levels'=>'ابتدایی، متوسطه','grade'=>'پایه ۷']),'Mixed school');
same(8.5,Finance::monthsOfStudent(['education_levels'=>'ابتدایی، متوسطه','grade'=>'۶']),'Mixed primary');
try{Finance::monthsOfStudent(['school_type'=>'غیردولتی']);throw new RuntimeException('Expected ambiguous grade rejection');}
catch(AuthException $e){same('EDUCATION_LEVEL_REQUIRED',$e->codeName,'Unknown stage');}
same(179051812,(int)round(21064919*Finance::monthsOfStudent(['education_levels'=>'دبستان'])),'Marina annual tariff');
same(203045796,(int)round(22560644*Finance::monthsOfStudent(['education_levels'=>'متوسطه'])),'Secondary annual tariff');
echo "Service financial basis and 30-day payroll contract passed\n";

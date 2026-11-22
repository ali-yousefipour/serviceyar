<?php
declare(strict_types=1);
require __DIR__.'/../backend/src/bootstrap.php';

use ServiceYar\Domain\VehicleClassEffectiveDates as Rates;

function check(bool $condition,string $message): void {
 if(!$condition)throw new RuntimeException($message);
}
$choices=Rates::parseChoices([
 'updateContract'=>true,
 'contractEffectiveDate'=>'2026-11-22',
 'updateStudentDebt'=>false,
 'updateDriverPay'=>true,
 'driverPayEffectiveDate'=>'2026-11-25',
]);
check($choices['studentDebtEffectiveDate']===null,'Declined debt update must not carry a date');
check($choices['driverPayEffectiveDate']==='2026-11-25','Driver date must be independent');

$split=Rates::splitRates('2026-11-01','2026-11-30','2026-11-16',3000000,6000000);
check($split['oldDays']===15&&$split['newDays']===15,'Change date must be inclusive on new rate');
check($split['amountIrr']===4500000,'November prorating must be exact');

$previous=Rates::splitRates('2026-11-01','2026-11-30','2026-12-01',3000000,6000000);
check($previous['newDays']===0&&$previous['amountIrr']===3000000,'Future change must preserve old period');
$before=Rates::splitRates('2026-11-01','2026-11-30','2026-10-30',3000000,6000000);
check($before['oldDays']===0&&$before['amountIrr']===6000000,'Earlier change must apply new rate');
try{
 Rates::parseChoices(['updateContract'=>false,'updateStudentDebt'=>true,'updateDriverPay'=>false]);
 throw new RuntimeException('Missing effective date accepted');
}catch(\ServiceYar\Auth\AuthException $e){
 check($e->status===422,'Expected validation error');
}
$periods=[
 ['id'=>1,'effective_date'=>'2026-11-11','old_monthly_irr'=>3000000,'new_monthly_irr'=>6000000],
 ['id'=>2,'effective_date'=>'2026-11-21','old_monthly_irr'=>6000000,'new_monthly_irr'=>9000000],
];
check(Rates::blendedRate('2026-11-01','2026-11-30',9000000,$periods)===6000000,'Multi-change November blend must be exact');
check(Rates::blendedRate('2026-10-01','2026-10-31',9000000,$periods)===3000000,'Prior months must retain original class rate');
check(Rates::blendedRate('2026-12-01','2026-12-31',9000000,$periods)===9000000,'Future months must use final class rate');
echo "Vehicle class effective-date rules: OK\n";

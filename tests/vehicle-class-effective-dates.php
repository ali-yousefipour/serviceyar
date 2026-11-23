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
check(Rates::annualEntitlement('1405-1406',true,100,[])===850,
 'Primary entitlement must remain eight and a half months');
check(Rates::annualEntitlement('1405-1406',false,100,[])===900,
 'Secondary entitlement must remain nine months');
check(Rates::annualEntitlement('1405-1406',true,900,[
 ['id'=>1,'effective_date'=>'2028-01-01','old_monthly_irr'=>100,'new_monthly_irr'=>900],
])===850,'Change after school year must not rewrite prior driver entitlement');
$weights=Rates::classWeights('2026-11-01','2026-11-30',[
 ['id'=>1,'effective_date'=>'2026-11-16','old_class'=>1,'new_class'=>2],
],2);
check(abs(($weights[1]??0)-0.5)<0.000001&&abs(($weights[2]??0)-0.5)<0.000001,
 'The class change date must belong to the new class');
$futureWeights=Rates::classWeights('2026-11-01','2026-11-30',[
 ['id'=>1,'effective_date'=>'2026-12-01','old_class'=>1,'new_class'=>2],
],2);
check(abs(($futureWeights[1]??0)-1.0)<0.000001,
 'Future vehicle swaps must preserve the old class in statements');
// A signed credit note larger than an issued invoice must not fabricate cash.
$creditInvoices=[
 ['id'=>1,'issue_date'=>'2026-11-01','amount'=>-500000],
 ['id'=>2,'issue_date'=>'2026-11-02','amount'=>1000000],
];
\ServiceYar\Domain\StudentBalance::allocate($creditInvoices,300000);
check($creditInvoices[0]['paidAmount']===0.0 || $creditInvoices[0]['paidAmount']===0,
 'Over-credit must never be allocated as a negative receipt');
check(abs($creditInvoices[1]['paidAmount']-300000)<0.001,
 'Over-credit must not inflate the available cash for later invoices');
check($creditInvoices[0]['remainingAmount']===0.0 || $creditInvoices[0]['remainingAmount']===0,
 'Over-credit invoice must not show a negative receivable');

echo "Vehicle class effective-date rules: OK\n";

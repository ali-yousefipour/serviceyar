<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
use ServiceYar\Domain\ContractBilling;
use ServiceYar\Domain\StudentDiscountBillingQuote as Quote;

$start = '2026-11-01';
$end = '2026-11-30';
$year = '1405-1406';
$student = 50000000;
$driver = 40000000;
$originalStudent = ContractBilling::quote($year,$start,$end,$student)['amount'];
$originalDriver = ContractBilling::quote($year,$start,$end,$driver)['amount'];
$scopes = [
    [true,true],
    [true,false],
    [false,true],
];
foreach ($scopes as [$s,$d]) {
    $record = ['effective_from'=>'2026-11-16','effective_to'=>null,
        'rate_milli_percent'=>20000,'apply_to_student'=>$s,'apply_to_driver'=>$d];
    $out=Quote::calculate($year,$start,$end,$student,$driver,[$record]);
    if ($out['studentBase']!==$originalStudent || $out['driverBase']!==$originalDriver)
        throw new RuntimeException('Discount split changed undiscounted contract totals');
    if (count($out['segments'])!==2)
        throw new RuntimeException('Effective date must split contract at exact day');
    if ($out['segments'][0]['studentDiscount']??false)
        throw new RuntimeException('Pre-effective segment must not be discounted');
    if ($s && $out['studentDiscount']<=0 || !$s && $out['studentDiscount']!==0 ||
        $d && $out['driverDiscount']<=0 || !$d && $out['driverDiscount']!==0)
        throw new RuntimeException('Student and driver discounts must be independent');
    if ($out['studentNet']!==$out['studentBase']-$out['studentDiscount'] ||
        $out['driverNet']!==$out['driverBase']-$out['driverDiscount'])
        throw new RuntimeException('Net totals not reconciled');
}
$undiscounted=Quote::calculate($year,$start,$end,$student,$driver,[]);
if ($undiscounted['studentDiscount']!==0 || $undiscounted['driverDiscount']!==0 ||
    $undiscounted['studentBase']!==$originalStudent || $undiscounted['driverBase']!==$originalDriver)
    throw new RuntimeException('No discount must preserve original amounts');
echo "Student contract discount quote tests passed\n";

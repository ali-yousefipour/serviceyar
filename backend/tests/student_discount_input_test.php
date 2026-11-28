<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
use ServiceYar\Domain\StudentDiscountInput;
$valid = [
 [['percent'=>'۲۰٪'],20000],
 [['percent'=>'٢٠٫٥'],20500],
 [['percent'=>'12.345'],12345],
 [['percent'=>'100'],100000],
 [[],20000],
 [['rateMilliPercent'=>20000],20000],
];
foreach ($valid as [$input,$expected]) {
    if (StudentDiscountInput::rate($input)!==$expected) throw new RuntimeException('Localized rate parsing mismatch');
}
foreach ([['percent'=>'۰'],['percent'=>'101'],['percent'=>'20.1234'],
          ['percent'=>'20e2'],['percent'=>[]],['rateMilliPercent'=>'20000'],
          ['percent'=>'-1']] as $invalid) {
    try { StudentDiscountInput::rate($invalid); }
    catch (\ServiceYar\Auth\AuthException $e) {
        if ($e->status===422) continue;
        throw $e;
    }
    throw new RuntimeException('Invalid discount rate accepted');
}
echo "Student discount localized inputs passed\n";

<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/src/Reports/ExcelLocalization.php';
use ServiceYar\Reports\ExcelLocalization as XL;
$cases=[
    ['2026-11-13','۱۴۰۵/۰۸/۲۲'],
    ['2024-03-20','۱۴۰۳/۰۱/۰۱'],
    ['2025-03-20','۱۴۰۳/۱۲/۳۰'],
    ['2026-11-13 17:35:09','۱۴۰۵/۰۸/۲۲ ۱۷:۳۵:۰۹'],
    ['active','فعال'],
    ['pending','در انتظار تأیید'],
    ['approved','تأییدشده'],
    ['card_to_card','کارت به کارت'],
    ['IR830170000000115338903004','IR830170000000115338903004'],
    ['PAY-123456','PAY-۱۲۳۴۵۶'],
    [1234,1234],
];
foreach($cases as [$input,$expected]){
    $out=XL::value($input);
    if($out!==$expected)throw new RuntimeException('Excel localization failed: '.var_export([$input,$expected,$out],true));
}
if(XL::header('تاریخ صدور میلادی')!=='تاریخ صدور شمسی')throw new RuntimeException('Jalali header failed');
if(XL::value('=SUM(A1:A10)')!=='=SUM(A۱:A۱۰)') {
    // Spreadsheet formulas must never be passed to the display converter;
    // this test explicitly prevents any caller from using it for formulas.
    throw new RuntimeException('Unexpected formula transformation');
}
echo "Persian Excel localization OK\n";

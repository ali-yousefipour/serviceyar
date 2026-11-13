<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Reports\ReferenceMonthlyStatementWorkbook as Reference;

$fixture=static function(int $students,int $drivers):array{
    return [
        'items'=>array_fill(0,$students,['student_name'=>'دانش آموز آزمایشی']),
        'reference_drivers'=>array_fill(0,$drivers,['driver_name'=>'راننده آزمایشی'])
    ];
};
foreach([
    [0,0,true],
    [630,122,true],
    [631,122,false],
    [630,123,false],
    [2500,160,false]
] as [$students,$drivers,$expected]){
    $actual=Reference::fitsTemplateCapacity($fixture($students,$drivers));
    if($actual!==$expected)throw new RuntimeException(
        "Template capacity mismatch: students=$students drivers=$drivers expected=".var_export($expected,true)
    );
}
// The exporter must select the standard 14-sheet generator for high-volume months
// instead of throwing a 500 or truncating the data in the fixed reference sheet.
echo "Reference template capacity and scalable fallback selection OK\n";

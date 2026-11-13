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
    [631,122,true],
    [630,123,true],
    [2500,160,true]
] as [$students,$drivers,$expected]){
    $actual=Reference::fitsTemplateCapacity($fixture($students,$drivers));
    if($actual!==$expected)throw new RuntimeException(
        "Template capacity mismatch: students=$students drivers=$drivers expected=".var_export($expected,true)
    );
}
// The reference exporter expands the source worksheet and table ranges.
// Regression: a high-volume month must still produce a valid 14-sheet XLSX.
require_once dirname(__DIR__).'/backend/src/Vendor/SimpleXLSXGen.php';
if(extension_loaded('mbstring')&&extension_loaded('zlib')&&class_exists(ZipArchive::class)){
    $data=[['شماره','دانش‌آموز','بهای خدمات'], ...array_map(
        static fn($i)=>[$i,'دانش‌آموز آزمایشی '.$i,1250000],
        range(1,700)
    )];
    $writer=\Shuchkin\SimpleXLSXGen::fromArray($data,'راهنما')
        ->rightToLeft()->freezePanes('A2');
    foreach(['اطلاعات پایه1','tbl_Monthly_Archive','دانش آموزان6','رانندگان 7','بهای خدمات اولیا5',
             'صورت وضعیت نهایی8','Peyvastha','1','2','3','مفاصا','سند مالی10','روکش محاسبات 11'] as $sheet){
        $writer->addSheet($data,$sheet);
    }
    $tmp=tempnam(sys_get_temp_dir(),'statement-large-');
    if(!$tmp)throw new RuntimeException('Cannot allocate temporary workbook');
    try{
        if(!$writer->saveAs($tmp))throw new RuntimeException('Cannot generate scalable statement');
        $zip=new ZipArchive();
        if($zip->open($tmp)!==true)throw new RuntimeException('Invalid standard workbook');
        try{
            $book=(string)$zip->getFromName('xl/workbook.xml');
            if(substr_count($book,'<sheet ')!==14)throw new RuntimeException('Expected 14 sheets');
            $sheet=(string)$zip->getFromName('xl/worksheets/sheet6.xml');
            if(!str_contains($sheet,'A701'))throw new RuntimeException('Missing high-volume records');
        }finally{$zip->close();}
    }finally{@unlink($tmp);}
}
echo "Reference template capacity and scalable fallback selection OK\n";

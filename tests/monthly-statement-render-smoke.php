<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Reports\ReferenceMonthlyStatementWorkbook as Template;
use ServiceYar\Domain\SchoolServiceStatement;

$action=$argv[1]??'';
if($action==='render'){
    $_ENV['SCHOOL_STATEMENT_REFERENCE_TEMPLATE_ENABLED']='1';
    $ref=SchoolServiceStatement::fromSchoolTariff('2',4,'ناحیه 6',30);
    $items=[];
    for($i=1;$i<=2;$i++){
        $items[]=[
            'student_id'=>$i,'student_name'=>'دانش آموز نمونه '.$i,
            'student_national_code'=>'000000000'.$i,
            'grade'=>'دبستان','guardian_first_name'=>'ولی','guardian_last_name'=>'نمونه',
            'mobile'=>'','address'=>'نشانی آزمایشی','school_name'=>'مدرسه آزمایشی',
            'region'=>'ناحیه 6','tariff_distance_km'=>4,'tariff_vehicle_class'=>'2',
            'driver_id'=>1,'driver_name'=>'راننده آزمایشی','driver_iban'=>'',
            'driver_national_code'=>'','driver_mobile'=>'','vehicle_model'=>'سواری',
            'vehicle_plate'=>'','bank_name'=>'','gross'=>(float)$ref['parent_total'],
            'reference'=>$ref
        ];
    }
    $keys=['driver_fee','oversight_3pct','municipality_2_75pct','municipality_vat_10pct',
           'road_police_0_25pct','education_1pct','company_7pct','certificate_fee',
           'insurance_fee','parent_total','statement_excluding_certificate_insurance'];
    $totals=[];
    foreach($keys as $key)$totals[$key]=2*(float)$ref[$key];
    $drivers=[['driver_id'=>1,'driver_name'=>'راننده آزمایشی','driver_iban'=>'',
        'student_count'=>2,'driver_fee'=>$totals['driver_fee']]];
    $d=['year'=>1405,'month'=>8,'month_name'=>'آبان','items'=>$items,
        'totals'=>['students'=>2,'drivers'=>1,'schools'=>1],
        'reference_totals'=>$totals,'reference_drivers'=>$drivers];
    Template::downloadIfAvailable($d,'شرکت آزمایشی',9876);
    throw new RuntimeException('Reference template was not activated');
}
if($action!=='verify'||empty($argv[2]))throw new InvalidArgumentException('Usage: render | verify file.xlsx');
$path=$argv[2];
$check=Template::validateTemplate($path);
if(!($check['valid']??false))throw new RuntimeException('Invalid rendered XLSX: '.json_encode($check));
$zip=new ZipArchive();
if($zip->open($path)!==true)throw new RuntimeException('Cannot open rendered workbook');
try{
    foreach([3,4,5,6,7] as $sheet){
        $xml=(string)$zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml');
        if(!str_contains($xml,'دانش') && $sheet!==7)
            throw new RuntimeException('Output does not populate sheet '.$sheet);
    }
    $parent=(string)$zip->getFromName('xl/worksheets/sheet6.xml');
    foreach(['SUM(K3:K4)','K3*3%','S3*7.8%'] as $formula)
        if(!str_contains($parent,$formula))
            throw new RuntimeException('Parent formula not populated: '.$formula);
    $final=(string)$zip->getFromName('xl/worksheets/sheet7.xml');
    if(!str_contains($final,'SUM(D4:G4)'))
        throw new RuntimeException('Driver statement summary formula missing');
    $table=(string)$zip->getFromName('xl/tables/table4.xml');
    if(!str_contains($table,'A2:W5'))
        throw new RuntimeException('Parent table did not resize to two students');
    if($zip->locateName('xl/vbaProject.bin')!==false)
        throw new RuntimeException('Rendered workbook contains macros');
}finally{$zip->close();}
echo "Rendered reference workbook structure and formulas verified\n";

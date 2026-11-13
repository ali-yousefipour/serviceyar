<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Reports\ReferenceMonthlyStatementWorkbook as Template;
use ServiceYar\Domain\SchoolServiceStatement;

$action=$argv[1]??'';
if(in_array($action,['render','render-preview','render-large'],true)){
    $_ENV['SCHOOL_STATEMENT_REFERENCE_TEMPLATE_ENABLED']='1';
    $ref=SchoolServiceStatement::fromSchoolTariff('2',4,'ناحیه 6',30);
    $items=[];
    $studentCount=$action==='render-large'?750:2;
    $driverCount=$action==='render-large'?240:1;
    for($i=1;$i<=$studentCount;$i++){
        $driverId=(($i-1)%$driverCount)+1;
        $items[]=[
            'student_id'=>$i,'student_name'=>'دانش آموز نمونه '.$i,
            'student_national_code'=>'000000000'.$i,
            'grade'=>'دبستان','guardian_first_name'=>'ولی','guardian_last_name'=>'نمونه',
            'mobile'=>'','address'=>'نشانی آزمایشی','school_name'=>'مدرسه آزمایشی',
            'region'=>'ناحیه 6','tariff_distance_km'=>4,'tariff_vehicle_class'=>'2',
            'driver_id'=>$driverId,'driver_name'=>'راننده آزمایشی '.$driverId,'driver_iban'=>'',
            'driver_national_code'=>'','driver_mobile'=>'','vehicle_model'=>'سواری',
            'vehicle_plate'=>'','bank_name'=>'','gross'=>(float)$ref['parent_total'],
            'reference'=>$ref
        ];
    }
    $keys=['driver_fee','oversight_3pct','municipality_2_75pct','municipality_vat_10pct',
           'road_police_0_25pct','education_1pct','company_7pct','certificate_fee',
           'insurance_fee','parent_total','statement_excluding_certificate_insurance'];
    $totals=[];
    foreach($keys as $key)$totals[$key]=$studentCount*(float)$ref[$key];
    $drivers=[];
    for($i=1;$i<=$driverCount;$i++){
        $count=intdiv($studentCount,$driverCount)+($i<=($studentCount%$driverCount)?1:0);
        $drivers[]=['driver_id'=>$i,'driver_name'=>'راننده آزمایشی '.$i,'driver_iban'=>'',
            'student_count'=>$count,'driver_fee'=>$count*(float)$ref['driver_fee']];
    }
    $d=['year'=>1405,'month'=>8,'month_name'=>'آبان','items'=>$items,
        'totals'=>['students'=>$studentCount,'drivers'=>$driverCount,'schools'=>1],
        'reference_totals'=>$totals,'reference_drivers'=>$drivers];
    Template::downloadIfAvailable($d,'شرکت آزمایشی',9876,$action==='render-preview');
    throw new RuntimeException('Reference template was not activated');
}
if($action!=='verify'||empty($argv[2]))throw new InvalidArgumentException('Usage: render | verify file.xlsx');
$path=$argv[2];
$isLarge=in_array('--large',array_slice($argv,3),true);
$check=Template::validateTemplate($path);
if(!($check['valid']??false))throw new RuntimeException('Invalid rendered XLSX: '.json_encode($check));
$zip=new ZipArchive();
if($zip->open($path)!==true)throw new RuntimeException('Cannot open rendered workbook');
try{
    foreach([3,4,5,6,7] as $sheet){
        $xml=(string)$zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml');
        $marker=in_array($sheet,[3,4,6],true)?'دانش':'راننده';
        if(!str_contains($xml,$marker))
            throw new RuntimeException('Output does not populate sheet '.$sheet);
    }
    $parent=(string)$zip->getFromName('xl/worksheets/sheet6.xml');
    foreach([$isLarge?'SUM(K3:K752)':'SUM(K3:K4)','K3*3%','S3*7.8%'] as $formula)
        if(!str_contains($parent,$formula))
            throw new RuntimeException('Parent formula not populated: '.$formula);
    $final=(string)$zip->getFromName('xl/worksheets/sheet7.xml');
    if(!str_contains($final,'SUM(D4:G4)'))
        throw new RuntimeException('Driver statement summary formula missing');
    $table=(string)$zip->getFromName('xl/tables/table4.xml');
    if(!str_contains($table,$isLarge?'A2:W753':'A2:W5'))
        throw new RuntimeException('Parent table did not resize to two students');
    if($isLarge){
        $finalDoc=new DOMDocument();$finalDoc->loadXML($final,LIBXML_NONET);
        $rows=[];foreach($finalDoc->getElementsByTagName('row') as $row)$rows[]=(int)$row->getAttribute('r');
        if(count($rows)!==count(array_unique($rows)))throw new RuntimeException('Duplicate row IDs after expansion');
        $driverTotal=4+240;
        if(!str_contains($final,'SUM(D4:D243)'))throw new RuntimeException('Expanded driver total is missing');
        if(!str_contains($final,'r="A240"'))throw new RuntimeException('Expanded final driver data missing');
        if(max($rows)<=230)throw new RuntimeException('Reference footer did not shift for extra drivers');
        if(!str_contains((string)$zip->getFromName('xl/tables/table2.xml'),'A2:L752'))
            throw new RuntimeException('Student table range did not expand');
        if(!str_contains((string)$zip->getFromName('xl/tables/table3.xml'),'A2:L242'))
            throw new RuntimeException('Driver table range did not expand');
    }
    if($isLarge){
        $book=new DOMDocument();
        $book->loadXML((string)$zip->getFromName('xl/workbook.xml'),LIBXML_NONET);
        $printAreas=[];
        foreach($book->getElementsByTagName('definedName') as $defined){
            if($defined->getAttribute('name')==='_xlnm.Print_Area')
                $printAreas[(int)$defined->getAttribute('localSheetId')]=$defined->textContent;
        }
        foreach([3=>752,4=>242,5=>753,6=>257] as $sheetIndex=>$endRow){
            $range=$printAreas[$sheetIndex]??'';
            if(!str_contains($range,chr(36).$endRow))
                throw new RuntimeException('Original print area is truncated: '.$sheetIndex.' = '.$range);
        }
    }
    // Repair-regression: shared formula followers require existing anchors.
    foreach([7,12] as $sheetId){
        $sheetXml=(string)$zip->getFromName('xl/worksheets/sheet'.$sheetId.'.xml');
        if(preg_match('/<f[^>]*t="shared"/',$sheetXml))
            throw new RuntimeException('Dangling shared formula in sheet'.$sheetId);
    }
    $period='آبان ۱۴۰۵';
    foreach([4,5,6,7,12,13,14] as $sheetId){
        $sheetXml=(string)$zip->getFromName('xl/worksheets/sheet'.$sheetId.'.xml');
        if(!str_contains($sheetXml,$period))
            throw new RuntimeException('Wrong month/year heading in sheet'.$sheetId);
    }
    // Structured table names must match the visible header text exactly.
    foreach([2=>[4,2],3=>[5,2],4=>[6,2],5=>[12,4]] as $tableNo=>$spec){
        [$sheetNo,$headerRow]=$spec;
        $table=new DOMDocument();$table->loadXML((string)$zip->getFromName('xl/tables/table'.$tableNo.'.xml'),LIBXML_NONET);
        $sheetDoc=new DOMDocument();$sheetDoc->loadXML((string)$zip->getFromName('xl/worksheets/sheet'.$sheetNo.'.xml'),LIBXML_NONET);
        $labels=[];
        foreach($sheetDoc->getElementsByTagName('row') as $row){
            if((int)$row->getAttribute('r')!==$headerRow)continue;
            foreach($row->getElementsByTagName('c') as $cell){
                $column=preg_replace('/[0-9]/','',$cell->getAttribute('r'));
                $labels[$column]=$cell->textContent;
            }
            break;
        }
        $columns=$table->getElementsByTagName('tableColumn');
        foreach($columns as $i=>$column){
            $n=$i+1;$letter='';
            while($n>0){$n--;$letter=chr(65+$n%26).$letter;$n=intdiv($n,26);}
            if(($labels[$letter]??null)!==$column->getAttribute('name'))
                throw new RuntimeException('Table header mismatch in table'.$tableNo.' col '.$letter);
        }
    }
    if($zip->locateName('xl/vbaProject.bin')!==false)
        throw new RuntimeException('Rendered workbook contains macros');
}finally{$zip->close();}
echo "Rendered reference workbook structure and formulas verified\n";
.$endRow))
                throw new RuntimeException('Original print area is truncated for sheet index '.$sheetIndex.': '.$range);
        }
    }
    // Repair-regression: Excel rejects shared followers when their original
    // formula base was replaced or removed.
    foreach([7,12] as $sheetId){
        $sheetXml=(string)$zip->getFromName('xl/worksheets/sheet'.$sheetId.'.xml');
        if(preg_match('/<f[^>]*t="shared"/',$sheetXml))
            throw new RuntimeException('Dangling shared formula in sheet'.$sheetId);
    }
    $period='آبان ۱۴۰۵';
    foreach([4,5,6,7,12,13,14] as $sheetId){
        $sheetXml=(string)$zip->getFromName('xl/worksheets/sheet'.$sheetId.'.xml');
        if(!str_contains($sheetXml,$period))
            throw new RuntimeException('Wrong month/year heading in sheet'.$sheetId);
    }
    // Structured table names must match the visible header text exactly.
    foreach([2=>[4,2],3=>[5,2],4=>[6,2],5=>[12,4]] as $tableNo=>$spec){
        [$sheetNo,$headerRow]=$spec;
        $table=new DOMDocument();$table->loadXML((string)$zip->getFromName('xl/tables/table'.$tableNo.'.xml'),LIBXML_NONET);
        $sheetDoc=new DOMDocument();$sheetDoc->loadXML((string)$zip->getFromName('xl/worksheets/sheet'.$sheetNo.'.xml'),LIBXML_NONET);
        $labels=[];
        foreach($sheetDoc->getElementsByTagName('row') as $row){
            if((int)$row->getAttribute('r')!==$headerRow)continue;
            foreach($row->getElementsByTagName('c') as $cell){
                $column=preg_replace('/[0-9]/','',$cell->getAttribute('r'));
                $labels[$column]=$cell->textContent;
            }
            break;
        }
        $columns=$table->getElementsByTagName('tableColumn');
        foreach($columns as $i=>$column){
            $n=$i+1;$letter='';
            while($n>0){$n--;$letter=chr(65+$n%26).$letter;$n=intdiv($n,26);}
            if(($labels[$letter]??null)!==$column->getAttribute('name'))
                throw new RuntimeException('Table header mismatch in table'.$tableNo.' col '.$letter);
        }
    }
    if($zip->locateName('xl/vbaProject.bin')!==false)
        throw new RuntimeException('Rendered workbook contains macros');
}finally{$zip->close();}
echo "Rendered reference workbook structure and formulas verified\n";

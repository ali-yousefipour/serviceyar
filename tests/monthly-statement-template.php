<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Reports\ReferenceMonthlyStatementWorkbook as Audit;

if(!class_exists(ZipArchive::class)||!class_exists(DOMDocument::class)){
    echo "Template structural checks skipped (ZIP/DOM unavailable)\n";
    exit(0);
}
$missing=Audit::validateTemplate(sys_get_temp_dir().'/missing-school-statement-reference.xlsx');
if($missing['valid']!==false||$missing['reason']!=='TEMPLATE_MISSING')
    throw new RuntimeException('Missing template was unexpectedly accepted');

$names=['راهنما','اطلاعات پایه1','tbl_Monthly_Archive','دانش آموزان6',
    'رانندگان 7','بهای خدمات اولیا5','صورت وضعیت نهایی8','Peyvastha',
    '1','2','3','مفاصا','سند مالی10','روکش محاسبات 11'];
$path=tempnam(sys_get_temp_dir(),'statement-audit-');
if($path===false)throw new RuntimeException('Cannot allocate template test');
try{
    $zip=new ZipArchive();
    if($zip->open($path,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create XLSX fixture');
    $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $sheets='';
    foreach($names as $i=>$name){
        $sheets.='<sheet name="'.htmlspecialchars($name,ENT_XML1|ENT_QUOTES,'UTF-8').'" sheetId="'.($i+1).'"/>';
        $zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml',
            '<?xml version="1.0"?><worksheet xmlns="'.$ns.'"><sheetData/></worksheet>');
    }
    $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="'.$ns.'"><sheets>'.$sheets.'</sheets></workbook>');
    $zip->close();
    if(!(Audit::validateTemplate($path)['valid']??false))
        throw new RuntimeException('Correct ordered 14-sheet template rejected');
    $zip=new ZipArchive();$zip->open($path);
    $zip->addFromString('xl/vbaProject.bin','macro');
    $zip->close();
    $unsafe=Audit::validateTemplate($path);
    if($unsafe['valid']!==false||$unsafe['reason']!=='UNSAFE_TEMPLATE_CONTENT')
        throw new RuntimeException('Unsafe VBA in template accepted');
    $zip=new ZipArchive();$zip->open($path);
    $zip->deleteName('xl/vbaProject.bin');
    $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="'.$ns.'"><sheets><sheet name="invalid" sheetId="1"/></sheets></workbook>');
    $zip->close();
    if((Audit::validateTemplate($path)['reason']??'')!=='SHEET_ORDER_MISMATCH')
        throw new RuntimeException('Wrong sheet order was accepted');
}finally{if(is_file($path))unlink($path);}
echo "Reference template validation passed\n";

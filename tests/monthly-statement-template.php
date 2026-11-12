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
    // Test the actual worksheet renderer against structured Table4 references.
    $zip=new ZipArchive();$zip->open($path);
    $zip->addFromString('xl/tables/table4.xml',
        '<?xml version="1.0"?><table xmlns="'.$ns.'" name="Table4" displayName="Table4" ref="A2:W633" totalsRowCount="1"><autoFilter ref="A2:W632"/></table>');
    $zip->addFromString('xl/worksheets/sheet6.xml',
        '<?xml version="1.0"?><worksheet xmlns="'.$ns.'"><dimension ref="A1:AM664"/><sheetData><row r="1"><c r="B1" t="inlineStr"><is><t>عنوان</t></is></c></row><row r="2"><c r="B2" t="inlineStr"><is><t>دانش‌آموز</t></is></c></row><row r="3"><c r="B3" s="2"/></row><row r="633"><c r="B633" t="inlineStr"><is><t>جمع کل قدیمی</t></is></c></row></sheetData></worksheet>');
    $zip->close();
    $replace=new ReflectionMethod(Audit::class,'replaceRows');
    $zip=new ZipArchive();$zip->open($path);
    $replace->invoke(null,$zip,6,3,[
        ['A'=>1,'B'=>'دانش‌آموز اول','K'=>1000.0],
        ['A'=>2,'B'=>'دانش‌آموز دوم','K'=>2000.0]
    ]);
    $table=$zip->getFromName('xl/tables/table4.xml');
    $sheet=$zip->getFromName('xl/worksheets/sheet6.xml');
    $zip->close();
    if(!is_string($table)||!str_contains($table,'ref="A2:W5"'))
        throw new RuntimeException('Table4 totals row/range did not resize');
    if(!is_string($sheet)||!str_contains($sheet,'SUM(K3:K4)')||str_contains($sheet,'جمع کل قدیمی'))
        throw new RuntimeException('Reference sheet did not replace totals/formulas');
    if(!(Audit::validateTemplate($path)['valid']??false))
        throw new RuntimeException('Edited 14-sheet workbook is invalid');
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

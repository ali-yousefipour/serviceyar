<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Reports\ReferenceMonthlyStatementWorkbook as Reference;

$template=dirname(__DIR__).'/backend/src/Reports/templates/school-statement-reference.xlsx';
if(!is_file($template)){
    echo "Reference template not installed: synthetic export remains active\n";
    exit(0);
}
$validation=Reference::validateTemplate($template);
if(!($validation['valid']??false)){
    throw new RuntimeException('The installed reference template failed security/structure validation: '
        .json_encode($validation,JSON_UNESCAPED_UNICODE));
}
$zip=new ZipArchive();
if($zip->open($template)!==true)throw new RuntimeException('Reference XLSX cannot be opened');
try{
    foreach(['xl/tables/table2.xml','xl/tables/table3.xml','xl/tables/table4.xml',
             'xl/worksheets/_rels/sheet6.xml.rels'] as $path){
        if($zip->locateName($path)===false)
            throw new RuntimeException('Required worksheet relation/table missing: '.$path);
    }
    if($zip->locateName('xl/sharedStrings.xml')!==false)
        throw new RuntimeException('Private reference shared strings must be inlined or removed');
}finally{$zip->close();}
echo "Installed reference template audit passed\n";

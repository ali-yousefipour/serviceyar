<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

$path=tempnam(sys_get_temp_dir(),'sy_sepand_');
if($path===false)throw new RuntimeException('Temporary test file unavailable');
$zip=new ZipArchive();
if($zip->open($path,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot construct XLSX fixture');
$zip->addFromString('xl/sharedStrings.xml','<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="4" uniqueCount="4"><si><t>شناسه درخواست</t></si><si><t>نام</t></si><si><t>SEPAND-REQ-123</t></si><si><t>علی</t></si></sst>');
$zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2" t="s"><v>3</v></c></row></sheetData></worksheet>');
$zip->close();
try{
 $method=new ReflectionMethod(\ServiceYar\Domain\SepandRegistryImport::class,'rows');
 $rows=$method->invoke(null,$path,['A'=>'شناسه درخواست','B'=>'نام']);
 if(count($rows)!==1||$rows[0]['fields']['A']!=='SEPAND-REQ-123'||$rows[0]['fields']['B']!=='علی')
  throw new RuntimeException('Sepand shared strings must preserve each column and value');
 echo "Sepand XLSX shared strings: OK\n";
}finally{@unlink($path);}

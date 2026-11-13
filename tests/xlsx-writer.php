<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/src/Vendor/SimpleXLSXGen.php';
if (!extension_loaded('mbstring') || !extension_loaded('zlib') || !extension_loaded('zip')) {
    fwrite(STDERR, "Missing mbstring, zlib or zip required for XLSX smoke test\n"); exit(1);
}
$book=\Shuchkin\SimpleXLSXGen::fromArray([
    ['نام راننده','شماره شبا','مبلغ (ریال)'],
    [chr(0).'آزمون فارسی',chr(0).'IR001234567890123456789012',1250000],
], 'گزارش')->setDefaultFont('Vazirmatn')->rightToLeft()->freezePanes('A2')->autoFilter('A1:C2');
$book->setColWidth('A',24)->setColWidth('B',32)->setColWidth('C',20);
$tmp=tempnam(sys_get_temp_dir(),'sy-xlsx-test-');
if(!$tmp || !$book->saveAs($tmp))throw new RuntimeException('Could not generate XLSX');
$zip=new ZipArchive();
try {
    if($zip->open($tmp)!==true)throw new RuntimeException('Generated XLSX not a ZIP');
    $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
    if(!is_string($sheet)||!str_contains($sheet,'rightToLeft="1"')||!str_contains($sheet,'autoFilter')||!str_contains($sheet,'pane'))throw new RuntimeException('Missing sheet properties');
    if(!str_contains($sheet,'1250000'))throw new RuntimeException('Numeric amount not found');
    if(!str_contains($sheet,'IR001234567890123456789012'))throw new RuntimeException('IBAN text not preserved');
    echo "XLSX writer smoke OK\n";
} finally { $zip->close(); @unlink($tmp); }

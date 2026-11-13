<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Reports\ReferenceMonthlyStatementWorkbook as Reference;

$source=file_get_contents(__DIR__.'/../backend/src/Reports/MonthlyStatementWorkbook.php');
if(!is_string($source))throw new RuntimeException('Cannot read statement exporter');
if(!str_contains($source,'ReferenceMonthlyStatementWorkbook::downloadIfAvailable($d,$agency,$id,$preview)'))
    throw new RuntimeException('Preview and final must share the reference exporter');
if(!str_contains($source,'STATEMENT_REFERENCE_TEMPLATE_CAPACITY')||
   !str_contains($source,'STATEMENT_REFERENCE_TEMPLATE_UNAVAILABLE'))
    throw new RuntimeException('Reference-only export must fail clearly rather than change visual layout');
if(!Reference::fitsTemplateCapacity(['items'=>array_fill(0,2500,[]),'reference_drivers'=>array_fill(0,160,[])]))
    throw new RuntimeException('Reference layout must accept expanded monthly data');
$frontend=file_get_contents(__DIR__.'/../frontend/assets/operations.js');
if(str_contains($frontend,'اکسل استاندارد ۱۴ شیتی'))
    throw new RuntimeException('Unapproved alternate statement layout still visible');
if(!str_contains($frontend,'دریافت اکسل آزمایشی ۱۴ شیتی'))
    throw new RuntimeException('Trial statement download missing');
echo "Trial and final statements are both restricted to the original layout\n";

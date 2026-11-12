<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Domain\SchoolServiceStatement;

// One audited row from the user's 1405 reference workbook, amounts in IRR.
// The final driver-statement subtotal intentionally excludes certification
// and insurance, but not the management/coordination/company components.
$row=SchoolServiceStatement::fromWorkbookRate('15625000',30,true,true);
$expected=[
    'driver_fee'=>15625000.0,
    'oversight_3pct'=>468750.0,
    'municipality_2_75pct'=>429687.5,
    'municipality_vat_10pct'=>42968.75,
    'road_police_0_25pct'=>39062.5,
    'education_1pct'=>156250.0,
    'company_7pct'=>1093750.0,
    'statement_excluding_certificate_insurance'=>17343750.0,
    'certificate_fee'=>115741.0,
    'insurance_fee'=>1361840.298,
    'parent_total'=>18821331.298,
];
foreach($expected as $key=>$value){
    if(!array_key_exists($key,$row)||abs((float)$row[$key]-$value)>0.001){
        throw new RuntimeException("Reference workbook mismatch: {$key}");
    }
}
$zero=SchoolServiceStatement::fromWorkbookRate('15625000',0,true,true);
if((float)$zero['parent_total']!==0.0||(float)$zero['statement_excluding_certificate_insurance']!==0.0){
    throw new RuntimeException('Zero-service-day amount is not zero');
}
echo "Reference workbook amount checks passed\n";

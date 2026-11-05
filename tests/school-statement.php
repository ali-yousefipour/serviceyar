<?php
require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Domain\SchoolStatement as S;
$r=S::calculate(['vehicleClass'=>'2','distanceKm'=>'4','region'=>'ناحیه 6','days'=>30]);
foreach(['base'=>'15625000.000000','work'=>'15625000.000000','management'=>'468750.000000','municipality'=>'429687.500000','vat'=>'42968.750000','police'=>'39062.500000','qualification'=>'115741.000000','subtotal'=>'17459491.000000','insurance'=>'1361840.298000','parent'=>'18821331.298000','statement'=>'17343750.000000'] as $k=>$expected)if($r['amounts'][$k]!==$expected)throw new RuntimeException($k.' mismatch');
$zero=S::calculate(['vehicleClass'=>'2','distanceKm'=>4,'region'=>'ناحیه 6','days'=>0]);if($zero['amounts']['parent']!=='0.000000')throw new RuntimeException('zero work');
foreach([['distanceKm'=>'4.123'],['days'=>31],['vehicleClass'=>4],['region'=>'unknown']] as $bad){try{S::calculate(array_merge(['vehicleClass'=>'2','distanceKm'=>4,'region'=>'ناحیه 6','days'=>30],$bad));throw new RuntimeException('Expected validation');}catch(\ServiceYar\Auth\AuthException $e){}}
echo "School statement: workbook example (12 amounts), zero work and four invalid inputs passed.\n";

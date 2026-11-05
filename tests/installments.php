<?php
require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Domain\InstallmentPlan as P;
$r=P::preview(['total'=>'100','advance'=>'10','count'=>3,'dates'=>['2026-11-01','2026-12-01','2027-01-01']]);
if(array_sum(array_column($r['items'],'amount'))+$r['advance']!==100)throw new Exception('Sum mismatch');
foreach([['advance'=>'101'],['count'=>0],['dates'=>['invalid']],['dates'=>['2026-11-01','2026-11-01','2027-01-01']]] as $bad){try{P::preview(array_merge(['total'=>'100','advance'=>'10','count'=>3,'dates'=>['2026-11-01','2026-12-01','2027-01-01']],$bad));throw new Exception('Validation missing');}catch(ServiceYar\Auth\AuthException $e){}}
echo "Installment sum and validation checks passed\n";

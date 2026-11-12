<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Domain\MonthlyStatements;
$reflection=new ReflectionMethod(MonthlyStatements::class,'approvalDigest');
$preview=[
    'year'=>1405,'month'=>8,'from'=>'2026-10-23','to'=>'2026-11-21',
    'rates'=>['supervision'=>3.0,'coordination'=>1.0,'overhead'=>7.0,'insurance'=>7.8],
    'items'=>[['student_id'=>10,'driver_id'=>2,'gross'=>11000000.0,'net'=>5000000.0]],
    'reference_totals'=>['parent_total'=>11800000.0],
    'reference_drivers'=>[['driver_id'=>2,'driver_fee'=>10000000.0]]
];
$first=$reflection->invoke(null,$preview);
$second=$reflection->invoke(null,$preview);
if(!preg_match('/^[a-f0-9]{64}$/D',$first) || !hash_equals($first,$second))
    throw new RuntimeException('Preview digest is not deterministic');
$modified=$preview;
$modified['items'][0]['gross']=11000001.0;
if(hash_equals($first,$reflection->invoke(null,$modified)))
    throw new RuntimeException('Modified gross is not detected');
$modified=$preview;
$modified['reference_drivers'][0]['driver_fee']=10000001.0;
if(hash_equals($first,$reflection->invoke(null,$modified)))
    throw new RuntimeException('Modified driver fee is not detected');
$modified=$preview;
$modified['month']=9;
if(hash_equals($first,$reflection->invoke(null,$modified)))
    throw new RuntimeException('Modified financial period is not detected');
echo "Monthly statement preview-integrity checks passed\n";

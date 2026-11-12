<?php
require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Domain\InstallmentPlan as P;
$r=P::preview(['total'=>'100','advance'=>'10','count'=>3,'dates'=>['2026-11-01','2026-12-01','2027-01-01']]);
if(array_sum(array_column($r['items'],'amount'))+$r['advance']!==100)throw new Exception('Sum mismatch');
foreach([['advance'=>'101'],['count'=>0],['dates'=>['invalid']],['dates'=>['2026-11-01','2026-11-01','2027-01-01']]] as $bad){try{P::preview(array_merge(['total'=>'100','advance'=>'10','count'=>3,'dates'=>['2026-11-01','2026-12-01','2027-01-01']],$bad));throw new Exception('Validation missing');}catch(ServiceYar\Auth\AuthException $e){}}

$approved=P::preview(['studentId'=>12,'issueDate'=>'2026-10-10','total'=>'100','advance'=>'10','count'=>3,'dates'=>['2026-11-01','2026-12-01','2027-01-01']]);
if(!preg_match('/^[a-f0-9]{64}$/',$approved['approvalDigest']??''))throw new Exception('Missing secure preview fingerprint');
$same=P::preview(['studentId'=>12,'issueDate'=>'2026-10-10','total'=>'100','advance'=>'10','count'=>3,'dates'=>['2026-11-01','2026-12-01','2027-01-01']]);
if(!hash_equals($approved['approvalDigest'],$same['approvalDigest']))throw new Exception('Preview fingerprint must be stable');
foreach([
 ['studentId'=>13],
 ['issueDate'=>'2026-10-11'],
 ['advance'=>'11'],
 ['dates'=>['2026-11-01','2026-12-02','2027-01-01']]
] as $changed){
 $input=array_merge(['studentId'=>12,'issueDate'=>'2026-10-10','total'=>'100','advance'=>'10','count'=>3,'dates'=>['2026-11-01','2026-12-01','2027-01-01']],$changed);
 $different=P::preview($input);
 if(hash_equals($approved['approvalDigest'],$different['approvalDigest']))throw new Exception('Changed installment inputs were not detected');
}
echo "Installment sum and validation checks passed\n";

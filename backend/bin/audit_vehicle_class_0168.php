<?php
declare(strict_types=1);

// Read-only, CLI-only verification. Never apply schema changes during an audit.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/src/bootstrap.php';

use ServiceYar\Database\Connection;

$pdo=Connection::get();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$schema=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($schema==='') throw new RuntimeException('No selected database');
$tables=[
 'vehicle_class_changes'=>['id','agency_id','driver_id','vehicle_id','created_by','decision_snapshot'],
 'vehicle_class_rate_periods'=>['id','agency_id','change_id','subject_type','subject_id','effective_date','applied_at'],
 'vehicle_class_contract_appendices'=>['id','agency_id','change_id','contract_id','effective_date'],
 'vehicle_class_debt_adjustments'=>['id','agency_id','change_id','student_id','service_id','academic_year','effective_date','amount_irr'],
];
$names=array_keys($tables);
$schemaProblems=[];
foreach($tables as $table=>$columns){
 $q=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
 $q->execute([$schema,$table]);
 $actual=$q->fetchAll(PDO::FETCH_COLUMN);
 foreach($columns as $col)if(!in_array($col,$actual,true))$schemaProblems[]="$table.$col";
}
$requiredIndexes=[
 'vehicle_class_changes'=>['idx_vehicle_class_change_driver','idx_vehicle_class_change_vehicle'],
 'vehicle_class_rate_periods'=>['uq_vehicle_change_subject','idx_vehicle_class_period_subject','idx_vehicle_class_period_due'],
 'vehicle_class_contract_appendices'=>['uq_vehicle_class_contract_appendix','idx_vehicle_contract_appendix'],
 'vehicle_class_debt_adjustments'=>['uq_vehicle_debt_change_student','idx_vehicle_debt_student_year'],
];
$missingIndexes=[];
$missingForeignKeys=[];
foreach($requiredIndexes as $table=>$indexes){
 $q=$pdo->prepare('SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
 $q->execute([$schema,$table]);
 $found=$q->fetchAll(PDO::FETCH_COLUMN);
 foreach($indexes as $index)if(!in_array($index,$found,true))$missingIndexes[]="$table.$index";
 $q=$pdo->prepare('SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL');
 $q->execute([$schema,$table]);
 if(!$q->fetchColumn())$missingForeignKeys[]=$table;
}
$migrationRecorded=false;
$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='migrations'");
$q->execute([$schema]);
if((int)$q->fetchColumn()){
 $q=$pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration=?");
 $q->execute(['0168_vehicle_class_change_history.sql']);
 $migrationRecorded=(int)$q->fetchColumn()===1;
}
$result=[
 'migration'=>'0168_vehicle_class_change_history.sql',
 'migration_recorded'=>$migrationRecorded,
 'missing_columns'=>$schemaProblems,
 'missing_indexes'=>$missingIndexes,
 'missing_foreign_keys'=>$missingForeignKeys,
 'tables'=>[],
 'tenant_reference_mismatches'=>[],
];
if(!$schemaProblems){
 foreach($names as $table)
  $result['tables'][$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
 $checks=[
  'rate_periods'=> "SELECT COUNT(*) FROM vehicle_class_rate_periods p LEFT JOIN vehicle_class_changes c ON c.id=p.change_id AND c.agency_id=p.agency_id WHERE c.id IS NULL",
  'contract_appendices'=> "SELECT COUNT(*) FROM vehicle_class_contract_appendices a LEFT JOIN vehicle_class_changes c ON c.id=a.change_id AND c.agency_id=a.agency_id LEFT JOIN contracts ct ON ct.id=a.contract_id AND ct.agency_id=a.agency_id WHERE c.id IS NULL OR ct.id IS NULL",
  'debt_adjustments'=> "SELECT COUNT(*) FROM vehicle_class_debt_adjustments a LEFT JOIN vehicle_class_changes c ON c.id=a.change_id AND c.agency_id=a.agency_id LEFT JOIN students st ON st.id=a.student_id AND st.agency_id=a.agency_id WHERE c.id IS NULL OR st.id IS NULL",
 ];
 foreach($checks as $key=>$sql)
  $result['tenant_reference_mismatches'][$key]=(int)$pdo->query($sql)->fetchColumn();
}
$result['ok']=$migrationRecorded && !$schemaProblems && !$missingIndexes && !$missingForeignKeys && array_sum($result['tenant_reference_mismatches'])===0;
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
if(in_array('--strict',$argv,true) && !$result['ok'])exit(1);

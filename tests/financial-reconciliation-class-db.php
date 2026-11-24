<?php
declare(strict_types=1);
namespace ServiceYar\Auth {
    // Authorization is outside this isolated reporting fixture; SQL is real MySQL.
    final class Authorization {
        public static function requireAgencyAccess(...$args):void{}
        public static function requirePermission(...$args):void{}
    }
}
namespace ServiceYar\Database {
    final class Connection {
        public static \PDO $pdo;
        public static function get():\PDO{return self::$pdo;}
    }
}
namespace {
require __DIR__.'/../backend/src/bootstrap.php';
$dsn=getenv('SERVICEYAR_TEST_DSN')?:'';
if($dsn==='')throw new RuntimeException('SERVICEYAR_TEST_DSN required');
$p=new PDO($dsn,getenv('SERVICEYAR_TEST_USER')?:'root',getenv('SERVICEYAR_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
\ServiceYar\Database\Connection::$pdo=$p;
foreach([
 'students'=>'id INT,agency_id INT,first_name VARCHAR(50),last_name VARCHAR(50),monthly_fee BIGINT,grade VARCHAR(40),school_id INT,academic_year VARCHAR(40)',
 'schools'=>'id INT,agency_id INT,name VARCHAR(50),school_type VARCHAR(50),education_levels VARCHAR(50)',
 'contracts'=>'id INT,agency_id INT,party_type VARCHAR(20),party_id INT,amount BIGINT,start_date DATE,end_date DATE,status VARCHAR(20)',
 'invoices'=>'id INT,agency_id INT,party_type VARCHAR(20),party_id INT,amount BIGINT,status VARCHAR(20),title VARCHAR(50),issue_date DATE',
 'student_contract_invoices'=>'agency_id INT,student_id INT,contract_id INT,invoice_id INT,academic_year VARCHAR(40)',
 'student_service_debt_invoices'=>'agency_id INT,student_id INT,invoice_id INT,academic_year VARCHAR(40)',
 'vehicle_class_changes'=>'id INT,agency_id INT,update_student_debt INT',
 'vehicle_class_contract_appendices'=>'id INT',
 'vehicle_class_rate_periods'=>'id INT,agency_id INT,change_id INT,subject_id INT,subject_type VARCHAR(30),effective_date DATE,old_monthly_irr BIGINT,new_monthly_irr BIGINT',
 'vehicle_class_debt_adjustments'=>'id INT,agency_id INT,student_id INT,academic_year VARCHAR(40),effective_date DATE,amount_irr BIGINT'
] as $table=>$columns)$p->exec("CREATE TEMPORARY TABLE $table($columns) DEFAULT CHARSET=utf8mb4");
$bounds=\ServiceYar\Domain\ContractBilling::defaults('1405-1406',false);
$p->exec("INSERT INTO schools VALUES(1,1,'School','secondary','secondary')");
$p->exec("INSERT INTO students VALUES(591,1,'Saleh','Ebrahimi',35274306,'7',1,'1405-1406')");
$p->prepare("INSERT INTO contracts VALUES(547,1,'student',591,270353421,?,?,'active')")->execute([$bounds['startDate'],$bounds['endDate']]);
$p->exec("INSERT INTO invoices VALUES(300,1,'student',591,270353421,'open','Invoice','2026-11-14')");
$p->exec("INSERT INTO student_contract_invoices VALUES(1,591,547,300,'1405/1406')");
$p->exec("INSERT INTO vehicle_class_changes VALUES(11,1,1)");
$p->exec("INSERT INTO vehicle_class_rate_periods VALUES(1,1,11,591,'student_debt','2026-10-23',30039269,35274306)");
$p->exec("INSERT INTO vehicle_class_debt_adjustments VALUES
 (1,1,591,'1405-1406','2026-10-23',41880296),
 (2,1,591,'1405-1406','2099-01-01',1000000),
 (3,2,591,'1405-1406','2026-10-23',1000000),
 (4,1,591,'1404-1405','2025-10-23',1000000)");
$report=\ServiceYar\Domain\FinancialReconciliation::report(1,1,['academicYear'=>'1405-1406']);
if($report['scanned']!==1||$report['count']!==0)throw new RuntimeException('Approved dated class change must not produce false discrepancies: '.json_encode($report));
// A real invoice mismatch must still be reported, with revised amounts and period pricing.
$p->exec('UPDATE invoices SET amount=270353420 WHERE id=300');
$report=\ServiceYar\Domain\FinancialReconciliation::report(1,1,['academicYear'=>'1405-1406']);
$row=$report['items'][0]??[];
if(($row['expected']??0)!==312233717||($row['invoice_total']??0)!==312233716||($row['difference']??0)!==1)
 throw new RuntimeException('Real mismatch or historic pricing lost');
if(($row['contracts'][0]['amount']??0)!==270353421||($row['contracts'][0]['effective_amount']??0)!==312233717)
 throw new RuntimeException('Original contract must remain distinct from adjusted amount');
echo "Financial reconciliation dated adjustments DB: OK\n";
}

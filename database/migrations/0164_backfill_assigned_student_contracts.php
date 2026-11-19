<?php
declare(strict_types=1);

use ServiceYar\Auth\AuthException;
use ServiceYar\Domain\ContractBilling;
use ServiceYar\Domain\ContractDocuments;
use ServiceYar\Domain\AgencySchoolTariffs;

return static function (PDO $pdo): void {
    // Keyset pagination avoids buffering every student, including on large installations.
    $last=0;
    $issued=0;
    $existing=0;
    $skipped=[];
    $batch=$pdo->prepare("SELECT st.id,st.agency_id,st.academic_year
        FROM students st
        WHERE st.id>? AND st.is_active=1
          AND EXISTS(SELECT 1 FROM service_students ss
            JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=st.agency_id
            WHERE ss.student_id=st.id AND sv.is_active=1 AND sv.status='active')
        ORDER BY st.id LIMIT 250");
    $mapping=$pdo->prepare("SELECT 1 FROM student_contract_invoices WHERE agency_id=? AND student_id=? AND academic_year=?");
    $overlap=$pdo->prepare("SELECT 1 FROM contracts WHERE agency_id=? AND party_type='student' AND party_id=?
        AND status<>'cancelled' AND (start_date IS NULL OR end_date IS NULL OR (start_date<=? AND end_date>=?)) LIMIT 1");
    do {
        $batch->execute([$last]);
        $rows=$batch->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row){
            $last=(int)$row['id'];
            $agency=(int)$row['agency_id'];
            try {
                $year=ContractBilling::year((string)($row['academic_year']?:AgencySchoolTariffs::YEAR));
                $mapping->execute([$agency,$last,$year]);
                if($mapping->fetchColumn()){++$existing;continue;}
                $period=ContractBilling::defaults($year,false);
                $overlap->execute([$agency,$last,$period['endDate'],$period['startDate']]);
                if($overlap->fetchColumn()){++$existing;continue;}
                $result=ContractDocuments::issueAssignedStudentForMigration($agency,$last);
                if(!empty($result['alreadyIssued']))++$existing;else ++$issued;
            } catch(AuthException $e) {
                // Validation and historic financial conflicts must not create a second debt.
                // The original issuance transaction rolls back both contract and invoice.
                $skipped[]=['agency'=>$agency,'student'=>$last,'reason'=>$e->getMessage()];
            }
        }
    } while(count($rows)===250);
    echo "Student contracts: issued={$issued}, existing={$existing}, skipped=".count($skipped)."\n";
    foreach($skipped as $row) echo json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
};

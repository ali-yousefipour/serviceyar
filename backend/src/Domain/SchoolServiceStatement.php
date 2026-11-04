<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use ServiceYar\Auth\AuthException;

/** Calculation from the 1405 school transport workbook, amounts in IRR. */
final class SchoolServiceStatement
{
    public static function fromWorkbookRate(string $baseFee, int $serviceDays, bool $certificate = true, bool $insured = true): array
    {
        if (!is_numeric($baseFee) || !is_finite((float)$baseFee) || (float)$baseFee<0 || (float)$baseFee>1000000000000 || $serviceDays<0 || $serviceDays>30) {
            throw new AuthException(422,'مبلغ پایه یا تعداد روزهای خدمت نامعتبر است.','VALIDATION_ERROR');
        }
        $fee=(float)$baseFee*$serviceDays/30;
        $certificateFee=($certificate&&$fee>0)?115741.0:0.0;
        $insuranceFee=$insured?($fee*1.11+$certificateFee)*0.078:0.0;
        return self::calculate((string)$fee,(string)$certificateFee,(string)$insuranceFee)
            +['base_fee'=>(float)$baseFee,'service_days'=>$serviceDays,'nominal_days'=>30];
    }
    /** Source-backed, read-only preview; imported fees are not settled transactions. */
    public static function importedReport(int $userId, int $agencyId, string $period = 'Mehr', ?string $from = null, ?string $to = null): array
    {
        Authorization::requirePermission($userId, 'finance.view');
        Authorization::requireAgencyAccess($userId, $agencyId);
        if ($period !== 'Mehr') throw new AuthException(422,'برای این دوره اطلاعات صورت‌وضعیت وارد نشده است.','VALIDATION_ERROR');
        if (($from===null)!==($to===null) || ($from!==null && (!OperationsService::validDate($from) || !OperationsService::validDate($to) || $from>$to))) {
            throw new AuthException(422,'بازه دریافت معتبر نیست.','VALIDATION_ERROR');
        }
        $pdo=Connection::get();
        $where=" WHERE f.agency_id=:agency AND f.academic_year='1405-1406' AND f.period=:period";
        $params=['agency'=>$agencyId,'period'=>$period];
        // A single historical source row per national code; do not copy these
        // calculated amounts into invoice/payment ledgers as actual transactions.
        $query=$pdo->prepare('SELECT f.*,s.id AS student_id FROM school_service_import_fees f LEFT JOIN students s ON s.agency_id=f.agency_id AND s.national_code=f.student_national_code'.$where.' ORDER BY f.driver_name,f.student_name');
        $query->execute($params);$items=$query->fetchAll();
        $receipts=[];
        if ($from!==null) {
            $q=$pdo->prepare("SELECT i.party_id,SUM(p.amount) received FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id WHERE p.agency_id=:a AND i.party_type='student' AND p.payment_date BETWEEN :f AND :t GROUP BY i.party_id");
            $q->execute(['a'=>$agencyId,'f'=>$from,'t'=>$to]);
            foreach($q->fetchAll() as $r)$receipts[(int)$r['party_id']]=(float)$r['received'];
        }
        $fields=['driver_fee','oversight_fee','municipality_fee','municipality_vat','road_police_fee','education_fee','company_fee','certificate_fee','insurance_fee','parent_total','statement_total'];
        $totals=array_fill_keys($fields,0.0);$totals+=['student_count'=>count($items),'matched_students'=>0,'received'=>0.0,'remaining'=>0.0];$byDriver=[];
        foreach($items as &$row){
            foreach($fields as $key)$totals[$key]+=(float)$row[$key];
            if($row['student_id']!==null)$totals['matched_students']++;
            $received=$from!==null?($receipts[(int)$row['student_id']]??0.0):null;
            $row['received']=$received;$row['remaining']=$received===null?null:(float)$row['parent_total']-$received;
            if($received!==null){$totals['received']+=$received;$totals['remaining']+=$row['remaining'];}
            $name=trim((string)$row['driver_name'])?:'راننده نامشخص';
            if(!isset($byDriver[$name]))$byDriver[$name]=['driver'=>$name,'students'=>0,'driver_fee'=>0.0,'statement_total'=>0.0,'parent_total'=>0.0,'received'=>$from===null?null:0.0,'remaining'=>$from===null?null:0.0];
            $byDriver[$name]['students']++;
            foreach(['driver_fee','statement_total','parent_total'] as $k)$byDriver[$name][$k]+=(float)$row[$k];
            if($from!==null){$byDriver[$name]['received']+=$received;$byDriver[$name]['remaining']+=$row['remaining'];}
        }unset($row);
        $totals['student_count']=count($items);
        if($from===null){$totals['received']=null;$totals['remaining']=null;}
        $totals['formula_difference']=round($totals['statement_total']-($totals['driver_fee']+$totals['oversight_fee']+$totals['education_fee']+$totals['company_fee']),2);
        return ['period'=>$period,'academicYear'=>'1405-1406','source'=>'workbook','isActualPayment'=>false,'receiptScope'=>$from===null?null:['from'=>$from,'to'=>$to,'source'=>'registered_invoice_payments_only'],'totals'=>$totals,'drivers'=>array_values($byDriver),'items'=>$items];
    }
    public static function calculate(string $driverFee, string $certificateFee = '0', string $insuranceFee = '0'): array
    {
        foreach ([$driverFee, $certificateFee, $insuranceFee] as $value) {
            if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) {
                throw new \InvalidArgumentException('Invalid nonnegative amount');
            }
        }
        $fee=(float)$driverFee;
        $oversight=$fee*0.03;
        $municipality=$fee*0.0275;
        $roadPolice=$fee*0.0025;
        $municipalityVat=$municipality*0.10;
        $education=$fee*0.01;
        $company=$fee*0.07;
        $statement=$fee+$oversight+$education+$company;
        $parentDue=$statement+(float)$certificateFee+(float)$insuranceFee;
        return [
            'driver_fee'=>$fee,'oversight_3pct'=>$oversight,
            'municipality_2_75pct'=>$municipality,'road_police_0_25pct'=>$roadPolice,
            'municipality_vat_10pct'=>$municipalityVat,'education_1pct'=>$education,
            'company_7pct'=>$company,'statement_excluding_certificate_insurance'=>$statement,
            'certificate_fee'=>(float)$certificateFee,'insurance_fee'=>(float)$insuranceFee,
            'parent_total'=>$parentDue,
        ];
    }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Calculation from the 1405 school transport workbook, amounts in IRR. */
final class SchoolServiceStatement
{
    /** Source-backed, read-only preview; imported fees are not settled transactions. */
    public static function importedReport(int $userId, int $agencyId, string $period = 'Mehr'): array
    {
        Authorization::requirePermission($userId, 'finance.view');
        Authorization::requireAgencyAccess($userId, $agencyId);
        if (!in_array($period, ['Mehr'], true)) throw new \InvalidArgumentException('Unsupported source period');
        $query=Connection::get()->prepare('SELECT COUNT(*) AS student_count,COALESCE(SUM(driver_fee),0) AS driver_fee,COALESCE(SUM(oversight_fee),0) AS oversight_fee,COALESCE(SUM(municipality_fee),0) AS municipality_fee,COALESCE(SUM(municipality_vat),0) AS municipality_vat,COALESCE(SUM(road_police_fee),0) AS road_police_fee,COALESCE(SUM(education_fee),0) AS education_fee,COALESCE(SUM(company_fee),0) AS company_fee,COALESCE(SUM(certificate_fee),0) AS certificate_fee,COALESCE(SUM(insurance_fee),0) AS insurance_fee,COALESCE(SUM(parent_total),0) AS parent_total,COALESCE(SUM(statement_total),0) AS statement_total FROM school_service_import_fees WHERE agency_id=:agency AND academic_year=:year AND period=:period');
        $query->execute(['agency'=>$agencyId,'year'=>'1405-1406','period'=>$period]);
        return ['period'=>$period,'academicYear'=>'1405-1406','source'=>'workbook','isActualPayment'=>false,'totals'=>$query->fetch()];
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

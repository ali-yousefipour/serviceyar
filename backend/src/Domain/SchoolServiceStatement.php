<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

/** Calculation from the 1405 school transport workbook, amounts in IRR. */
final class SchoolServiceStatement
{
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

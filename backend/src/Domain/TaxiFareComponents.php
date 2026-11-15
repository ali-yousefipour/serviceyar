<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;

/**
 * Read-only breakdown of the OFFICIAL parent tariff.
 *
 * None of these parent-side items is a payroll withholding. Coordination
 * receipts must not be distributed until an actual traffic-council decision
 * defines beneficiaries and allocations.
 */
final class TaxiFareComponents
{
    public const SUPERVISION=0.03;
    public const COORDINATION=0.01;
    public const OVERHEAD=0.07;
    public const INSURANCE=0.078;
    public const CERTIFICATE_TOTAL_IRR=10000000;
    public const CERTIFICATE_PERIOD_MONTHS=9;
    public const CERTIFICATE_ALLOCATION_FACTOR=9.6;

    /** Quote the official table item, without issuing a payment or ledger entry.
     * $licensed must be explicitly verified; null means unverified, never 90% discount.
     * $special may be normal, exceptional_school, or vocational_disabled.
     */
    public static function breakdown(
        int $driverBase,
        ?bool $licensed=null,
        string $special='normal',
        ?int $referenceParent=null
    ): array {
        if($driverBase<0)throw new AuthException(422,'مبلغ پایه راننده نامعتبر است.','INVALID_DRIVER_BASE');
        if(!in_array($special,['normal','exceptional_school','vocational_disabled'],true))
            throw new AuthException(422,'نوع خدمت ویژه نامعتبر است.','INVALID_SPECIAL_SERVICE');

        $isSpecial=$special!=='normal';
        $driverPay=(int)round($driverBase*($isSpecial?1.2:1));
        $supervision=$isSpecial?0:$driverBase*self::SUPERVISION;
        $coordination=$isSpecial?0:$driverBase*self::COORDINATION;
        // Footnote 5-5: overhead applies to the base BEFORE 1.2.
        $overhead=$driverBase*self::OVERHEAD;
        $certificate=$isSpecial?0:self::CERTIFICATE_TOTAL_IRR*($licensed===true?0.10:1)
            /self::CERTIFICATE_ALLOCATION_FACTOR/self::CERTIFICATE_PERIOD_MONTHS;
        $beforeInsurance=$driverPay+$supervision+$coordination+$overhead+$certificate;
        $insurance=$beforeInsurance*self::INSURANCE;
        $computed=(int)round($beforeInsurance+$insurance);
        // An explicit table override is authoritative; its components are NOT
        // asserted to equal statutory defaults. Keep the difference visible.
        $parentTotal=$referenceParent??$computed;
        $difference=$parentTotal-$computed;

        $payerShares=null;
        if($special==='exceptional_school'){
            // Three equal shares, with the one-rial remainder assigned to parents.
            $oneThird=intdiv($parentTotal,3);
            $payerShares=['municipality'=>$oneThird,'special_education'=>$oneThird,
                'parents'=>$parentTotal-2*$oneThird,'agreement_required'=>true];
        }
        return [
            'driverBaseMonthly'=>$driverBase,
            'driverMonthly'=>$driverPay,
            'parentMonthly'=>$parentTotal,
            'supervisionParentShare'=>(int)round($supervision),
            'coordinationParentShare'=>(int)round($coordination),
            'overheadParentShare'=>(int)round($overhead),
            'certificateParentShare'=>(int)round($certificate),
            'insuranceParentShare'=>(int)round($insurance),
            'roundingAdjustment'=>$computed-(
                $driverPay+(int)round($supervision)+(int)round($coordination)
                +(int)round($overhead)+(int)round($certificate)+(int)round($insurance)),
            'tariffOverrideDifference'=>$difference,
            'coordinationAllocationStatus'=>$isSpecial?'not_applicable':'pending_traffic_council_decision',
            'coordinationBeneficiaryShares'=>null,
            'certificateLicenseVerified'=>$licensed!==null,
            'certificateDiscountApplied'=>$licensed===true&&!$isSpecial,
            'specialService'=>$special,
            'payerShares'=>$payerShares,
            'vocationalMunicipalityProgramCapIrr'=>$special==='vocational_disabled'?7000000000:null,
            'driverWithholdingsIncluded'=>false,
            'currency'=>'IRR',
        ];
    }
    /**
     * Non-posting preview only. Beneficiaries and ratios MUST come from the
     * actual Mashhad Traffic Council resolution, not guessed defaults.
     * Never creates any ledger or payroll transaction.
     */
    public static function previewCoordinationAllocation(
        int $amount, array $beneficiaryPercentages, string $councilDecisionReference
    ): array {
        if($amount<0||trim($councilDecisionReference)==='')
            throw new AuthException(422,'شماره مصوبه شورای ترافیک و مبلغ معتبر الزامی است.','COORDINATION_RESOLUTION_REQUIRED');
        if(!$beneficiaryPercentages||count($beneficiaryPercentages)>30)
            throw new AuthException(422,'ذی‌نفعان مصوب مشخص نشده‌اند.','COORDINATION_BENEFICIARIES_REQUIRED');
        $units=[];
        foreach($beneficiaryPercentages as $beneficiary=>$percent) {
            if(!is_string($beneficiary)||trim($beneficiary)===''||
               !is_numeric($percent)||!is_finite((float)$percent)||
               (float)$percent<0||(float)$percent>100)
                throw new AuthException(422,'درصد تسهیم ذی‌نفع نامعتبر است.','INVALID_COORDINATION_ALLOCATION');
            // Resolution percentages are expressed with up to three decimals.
            $scaled=round((float)$percent*1000);
            if(abs($scaled-(float)$percent*1000)>0.000001)
                throw new AuthException(422,'درصد تسهیم حداکثر سه رقم اعشار دارد.','INVALID_COORDINATION_ALLOCATION');
            $units[$beneficiary]=(int)$scaled;
        }
        if(array_sum($units)!==100000)
            throw new AuthException(422,'جمع سهم‌های مصوب باید دقیقاً ۱۰۰٪ باشد.','COORDINATION_PERCENT_TOTAL');
        $result=[];$left=$amount;$names=array_keys($units);
        foreach($names as $i=>$name){
            $share=$i===count($names)-1?$left:intdiv($amount*$units[$name],100000);
            $result[$name]=$share;$left-=$share;
        }
        return ['approvedResolutionReference'=>trim($councilDecisionReference),
            'amountIrr'=>$amount,'previewOnly'=>true,'posted'=>false,
            'beneficiaryAmountsIrr'=>$result];
    }

}

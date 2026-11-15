<?php
declare(strict_types=1);
require __DIR__.'/../backend/src/Auth/AuthException.php';
require __DIR__.'/../backend/src/Domain/TaxiFareComponents.php';

use ServiceYar\Domain\TaxiFareComponents as Fare;
function eq(mixed $expected,mixed $actual,string $label): void {
    if($expected!==$actual)throw new RuntimeException("$label: expected ".json_encode($expected)." got ".json_encode($actual));
}
// Workbook class 1, 3 km (D5, F5:M5): amounts come from
// base driver + 3% supervision + 1% coordination + 7% overhead
// + 10m/9.6/9 certificate, with insurance 7.8% of the subtotal.
$normal=Fare::breakdown(10000000,null,'normal',12090569);
eq(10000000,$normal['driverMonthly'],'driver remains base');
eq(300000,$normal['supervisionParentShare'],'3% parent');
eq(100000,$normal['coordinationParentShare'],'1% parent');
eq(700000,$normal['overheadParentShare'],'7% parent');
eq(115741,$normal['certificateParentShare'],'certificate spread');
eq(12090569,$normal['parentMonthly'],'official parents fare');
eq('pending_traffic_council_decision',$normal['coordinationAllocationStatus'],'no invented beneficiaries');
eq(null,$normal['coordinationBeneficiaryShares'],'council allocation must not be invented');
eq(false,$normal['driverWithholdingsIncluded'],'parent shares never treated as driver withholding');
$licensed=Fare::breakdown(10000000,true);
eq(11574,$licensed['certificateParentShare'],'90% certificate discount only with verified permit');
if($licensed['parentMonthly'] >= $normal['parentMonthly'])throw new RuntimeException('Discount must reduce parents fare');
$special=Fare::breakdown(10000000,null,'exceptional_school');
eq(12000000,$special['driverMonthly'],'special service 1.2 on driver only');
eq(700000,$special['overheadParentShare'],'overhead calculated before 1.2');
eq(0,$special['coordinationParentShare'],'no 1% for special');
eq(0,$special['supervisionParentShare'],'no 3% for special');
eq(0,$special['certificateParentShare'],'no certificate for special');
eq($special['parentMonthly'],array_sum([
    $special['payerShares']['municipality'],
    $special['payerShares']['special_education'],
    $special['payerShares']['parents']]),'three-party total conservation');
$vocational=Fare::breakdown(10000000,null,'vocational_disabled');
eq(7000000000,$vocational['vocationalMunicipalityProgramCapIrr'],'program-wide cap is not per-student credit');
eq(null,$vocational['payerShares'],'no unfounded one-third split for vocational program');
$alloc=Fare::previewCoordinationAllocation(101,['city'=>33.333,'school'=>66.667],'traffic-council-verified-001');
eq(101,array_sum($alloc['beneficiaryAmountsIrr']),'allocation conservation');
eq(false,$alloc['posted'],'preview cannot move money');
echo "Taxi parent-side breakdown and council-only preview: OK\n";

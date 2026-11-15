<?php
declare(strict_types=1);

/**
 * No database credentials or writes: runs the actual production pricing
 * classes against an in-memory PDO test double. Suitable for CI and deploy
 * gates; not a substitute for MariaDB transaction and posted-ledger tests.
 */
require __DIR__.'/../backend/src/Auth/AuthException.php';
require __DIR__.'/../backend/src/Domain/TaxiFareComponents.php';
require __DIR__.'/../backend/src/Domain/TaxiOrganizationTariffs.php';
require __DIR__.'/../backend/src/Domain/ServicePricing.php';
require __DIR__.'/../backend/src/Domain/DriverDeductionPolicy.php';

use ServiceYar\Auth\AuthException;
use ServiceYar\Domain\DriverDeductionPolicy;
use ServiceYar\Domain\ServicePricing;
use ServiceYar\Domain\TaxiFareComponents;
use ServiceYar\Domain\TaxiOrganizationTariffs as Taxi;

final class TaxiFixtureStatement extends PDOStatement {
    public array $params=[];
    public function __construct(private string $query,private TaxiFixturePdo $fixture) {}
    public function execute(?array $params=null): bool {$this->params=$params??[];return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {
        if(str_contains($this->query,'FROM taxi_organization_tariffs'))return $this->fixture->overrides;
        throw new RuntimeException('Unexpected query in price test: '.$this->query);
    }
    public function fetchColumn(int $column=0): mixed {
        if(str_contains($this->query,'FROM schools'))return $this->fixture->region;
        throw new RuntimeException('Unexpected query in school test: '.$this->query);
    }
}
final class TaxiFixturePdo extends PDO {
    public string $region='ناحیه ۶';
    public array $overrides=[];
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        if(!str_contains($query,'FROM taxi_organization_tariffs')&&!str_contains($query,'FROM schools'))
            throw new RuntimeException('Unexpected SQL in read-only integration test: '.$query);
        return new TaxiFixtureStatement($query,$this);
    }
}
function check(bool $ok,string $label): void {
    if(!$ok)throw new RuntimeException('FAILED: '.$label);
}
function equals(mixed $a,mixed $b,string $label): void {check($a===$b,$label.' expected '.var_export($a,true).' got '.var_export($b,true));}
function expectedFailure(callable $fn,string $code): void {
    try {$fn();} catch(AuthException $e) {equals($code,$e->codeName,'expected financial safeguard');return;}
    throw new RuntimeException('Expected validation error '.$code);
}
$pdo=new TaxiFixturePdo();
$student=['school_id'=>2,'academic_year'=>Taxi::YEAR,'tariff_vehicle_class'=>'1','tariff_distance_km'=>3];
$classes=['1','2','3'];
foreach(range(1,8) as $district) {
    $pdo->region=$district===8?'تبادکان':'ناحیه '.$district;
    foreach($classes as $class)foreach([3,4,10,20,21,22,40] as $distance) {
        $student['tariff_vehicle_class']=$class;
        $student['tariff_distance_km']=$distance;
        $q=ServicePricing::quote($pdo,1,$student,'taxi');
        $raw=Taxi::quote($pdo,$district,$class,$distance);
        equals((int)$raw['parentMonthly'],$q['monthlyFee'],'student invoice quote matches official parent tariff');
        equals((int)$raw['driverMonthly'],$q['driverBaseMonthly'],'driver base matches official remuneration');
        equals(false,$q['parentFareComponents']['driverWithholdingsIncluded'],'parent tariff never booked as deduction');
        equals('pending_traffic_council_decision',$q['parentFareComponents']['coordinationAllocationStatus'],'no automatic 1-percent distribution');
        check($q['parentFareComponents']['coordinationBeneficiaryShares']===null,'unresolved council beneficiaries');
        check($q['driverBaseMonthly']>=0&&$q['monthlyFee']>=0,'nonnegative fares');
    }
}
$pdo->region='ناحیه ۶';
$student['tariff_vehicle_class']='1';$student['tariff_distance_km']=3;
$q=ServicePricing::quote($pdo,1,$student,'taxi');
equals(12500000,$q['driverBaseMonthly'],'district 6 uplifts driver base by 25 percent');
equals(15082019,$q['monthlyFee'],'official parent tariff district 6');
equals(375000,$q['parentFareComponents']['supervisionParentShare'],'parent 3 percent based on driver remuneration');
equals(125000,$q['parentFareComponents']['coordinationParentShare'],'parent 1 percent based on driver remuneration');
$deductions=DriverDeductionPolicy::calculate((float)$q['driverBaseMonthly'],DriverDeductionPolicy::DEFAULTS);
equals('agency_driver_payroll_policy',$deductions['deduction_origin'],'contractual payroll policy kept separate');
equals(true,$deductions['independent_of_parent_fare_shares'],'parent shares not automatic driver withholding');

// Beyond 20km: the incremental parent rate must include traffic on incremental driver pay.
$at20=Taxi::quote($pdo,6,'1',20);$at21=Taxi::quote($pdo,6,'1',21);
equals(850000,$at21['driverMonthly']-$at20['driverMonthly'],'21st km includes district-6 25% traffic');
equals((int)round($at20['parentMonthly']+680000*1.25*1.19658),$at21['parentMonthly'],'21km parent escalation includes traffic');

// A manual override contains final approved amounts; do not uplift twice.
$pdo->overrides=[['distance_km'=>20,'driver_monthly_irr'=>32000000,'parent_monthly_irr'=>40000000]];
$custom=Taxi::quote($pdo,6,'1',20);
equals(32000000,$custom['driverMonthly'],'20km override keeps final driver tariff');
equals(40000000,$custom['parentMonthly'],'20km override keeps final parent tariff');
$custom21=Taxi::quote($pdo,6,'1',21);
equals(32850000,$custom21['driverMonthly'],'override 21km uplifts only additional 680k by traffic');
equals((int)round(40000000+680000*1.25*1.19658),$custom21['parentMonthly'],'override extrapolation parent rise');
expectedFailure(static fn()=>Taxi::quote($pdo,6,'1',20,['licenseVerified'=>true]),'SPECIAL_OVERRIDE_REQUIRES_APPROVAL');
$pdo->overrides=[];

// Certificate eligibility cannot be assumed.
$unverified=Taxi::quote($pdo,6,'1',3);
$licensed=Taxi::quote($pdo,6,'1',3,['licenseVerified'=>true]);
equals(false,$unverified['parentComponents']['certificateDiscountApplied'],'unverified permit never discounted');
equals(true,$licensed['parentComponents']['certificateDiscountApplied'],'verified permit is discounted');
check($licensed['parentMonthly']<$unverified['parentMonthly'],'discount reduces billed parent fare');
expectedFailure(static fn()=>Taxi::quote($pdo,6,'1',3,['licenseVerified'=>'yes']),'INVALID_LICENSE_STATUS');

// Exceptional school fee is one-third only with an agreement; full cost and
// driver entitlement stay independently available.
expectedFailure(static fn()=>Taxi::quote($pdo,6,'1',3,['specialService'=>'exceptional_school']),'TRIPARTITE_AGREEMENT_REQUIRED');
$special=Taxi::quote($pdo,6,'1',3,['specialService'=>'exceptional_school','tripartiteAgreementReference'=>'SIGNED-TEST-001']);
equals(15000000,$special['driverMonthly'],'exceptional driver gets 1.2x base including traffic');
equals(0,$special['parentComponents']['coordinationParentShare'],'no coordination for exceptional service');
equals(0,$special['parentComponents']['supervisionParentShare'],'no supervision for exceptional service');
equals(875000,$special['parentComponents']['overheadParentShare'],'7 percent overhead calculated before 1.2');
equals($special['parentComponents']['payerShares']['parents'],$special['parentPayableMonthly'],'exceptional parents one-third');
$student['tariff_distance_km']=3;
$specialPricing=ServicePricing::quote($pdo,1,$student,'taxi',['specialService'=>'exceptional_school','tripartiteAgreementReference'=>'SIGNED-TEST-001']);
equals($special['parentPayableMonthly'],$specialPricing['monthlyFee'],'special student quote invoices only parent share');
equals($special['parentMonthly'],$specialPricing['fullParentSideFare'],'full service cost retained separately');

$coordination=TaxiFareComponents::previewCoordinationAllocation(
    125001,['municipality'=>30,'agency'=>30,'other_approved_party'=>40],'CITY-COUNCIL-TEST-001');
equals(125001,array_sum($coordination['beneficiaryAmountsIrr']),'approved shares conserve all rials');
equals(false,$coordination['posted'],'allocation remains non-posting');
expectedFailure(static fn()=>TaxiFareComponents::previewCoordinationAllocation(
    100,['agency'=>100],''),'COORDINATION_RESOLUTION_REQUIRED');
expectedFailure(static fn()=>TaxiFareComponents::previewCoordinationAllocation(
    100,['agency'=>80,'municipality'=>10],'DECISION-001'),'COORDINATION_PERCENT_TOTAL');
echo "Integrated taxi fare -> parent shares -> service pricing -> driver contractual deductions -> council and special protections: OK\n";

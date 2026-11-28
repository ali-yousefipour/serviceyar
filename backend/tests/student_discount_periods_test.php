<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Domain/StudentDiscountMath.php';
require_once __DIR__.'/../src/Domain/StudentDiscountPeriods.php';
use ServiceYar\Domain\StudentDiscountPeriods as P;
function same(mixed $actual,mixed $expected): void {
    if ($actual !== $expected) throw new RuntimeException(json_encode([$actual,$expected]));
}
$d=['effective_from'=>'2026-11-15','effective_to'=>'2026-11-25','rate_milli_percent'=>20000,'apply_to_student'=>1,'apply_to_driver'=>0];
$s=P::split('2026-11-01','2026-12-01',[$d]);
same($s,[
 ['from'=>'2026-11-01','to'=>'2026-11-15','discount'=>null],
 ['from'=>'2026-11-15','to'=>'2026-11-25','discount'=>$d],
 ['from'=>'2026-11-25','to'=>'2026-12-01','discount'=>null]
]);
same(P::calculateSegment(1000,800,$s[0]['discount'])['studentNet'],1000);
same(P::calculateSegment(1000,800,$s[1]['discount'])['studentNet'],800);
same(P::calculateSegment(1000,800,$s[1]['discount'])['driverNet'],800);
same(P::calculateSegment(1000,800,$s[2]['discount'])['studentNet'],1000);
$d2=array_replace($d,['effective_from'=>'2026-11-25','effective_to'=>null,'apply_to_student'=>0,'apply_to_driver'=>1]);
$s=P::split('2026-11-01','2026-12-01',[$d,$d2]);
same(P::calculateSegment(1000,800,$s[2]['discount'])['driverNet'],640);
same(P::calculateSegment(1000,800,$s[2]['discount'])['studentNet'],1000);
same(P::split('2026-11-01','2026-11-15',[$d])[0]['discount'],null);
try {
 P::split('2026-11-01','2026-12-01',[$d,array_replace($d2,['effective_from'=>'2026-11-24'])]);
 throw new RuntimeException('Overlap accepted');
} catch (DomainException $e) {}
try {
 P::split('2026-02-30','2026-03-15',[]);
 throw new RuntimeException('Invalid date accepted');
} catch (InvalidArgumentException $e) {}
// A period can contain several tariff-derived segments; the authoritative quote
// must be called once per segment and never receive a previously discounted base.
$quotes=[];
$aggregate=P::calculatePeriod('2026-11-01','2026-12-01',[$d,$d2],
    static function(string $from,string $to) use (&$quotes): array {
        $quotes[]=$from.'..'.$to;
        return ['studentBase'=>1000,'driverBase'=>800];
    });
same($quotes,['2026-11-01..2026-11-15','2026-11-15..2026-11-25','2026-11-25..2026-12-01']);
same($aggregate['studentBase'],3000);
same($aggregate['driverBase'],2400);
same($aggregate['studentDiscount'],200);
same($aggregate['driverDiscount'],160);
same($aggregate['studentNet'],2800);
same($aggregate['driverNet'],2240);
same($aggregate['companyShare'],560);
same(count($aggregate['segments']),3);
try {
 P::calculatePeriod('2026-11-01','2026-12-01',[],static fn(): array=>['studentBase'=>1000.0,'driverBase'=>800]);
 throw new RuntimeException('Non-integer quote accepted');
} catch (InvalidArgumentException $e) {}
echo "Student discount period tests passed\n";

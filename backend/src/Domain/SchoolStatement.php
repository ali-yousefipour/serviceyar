<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\AuthException;
final class SchoolStatement {
 private static function gcd(int $a,int $b): int {while($b){[$a,$b]=[$b,$a%$b];}return max(1,$a);}
 private static function ratio(int $n,int $d=1): array {$g=self::gcd($n,$d);return [intdiv($n,$g),intdiv($d,$g)];}
 private static function mul(array $a,int $n,int $d=1): array {$g=self::gcd($a[0],$d);$h=self::gcd($n,$a[1]);return self::ratio(intdiv($a[0],$g)*intdiv($n,$h),intdiv($a[1],$h)*intdiv($d,$g));}
 private static function add(array $a,array $b): array {$g=self::gcd($a[1],$b[1]);return self::ratio($a[0]*intdiv($b[1],$g)+$b[0]*intdiv($a[1],$g),$a[1]*intdiv($b[1],$g));}
 private static function text(array $a): string {$whole=intdiv($a[0],$a[1]);$fraction=intdiv(($a[0]%$a[1])*1000000,$a[1]);return $whole.'.'.str_pad((string)$fraction,6,'0',STR_PAD_LEFT);}
 public static function calculate(array $input): array {
  $class=$input['vehicleClass']??'';$distance=$input['distanceKm']??'';$region=$input['region']??'';$days=$input['days']??30;
  if(!is_scalar($class)||!in_array((string)$class,['1','2','3'],true)||!is_scalar($distance)||!is_numeric($distance)||!is_int($days)||$days<0||$days>30||!is_string($region))self::invalid();
  $rates=json_decode(file_get_contents(__DIR__.'/school-tariffs-1405.json'),true,512,JSON_THROW_ON_ERROR);$key=(string)(0+$distance);$rate=$rates[(string)$class][$key]??null;
  $factors=['ناحیه 1'=>100,'ناحیه 2'=>110,'ناحیه 3'=>125,'ناحیه 4'=>125,'ناحیه 5'=>110,'ناحیه 6'=>125,'ناحیه 7'=>110,'تبادکان'=>100];
  if($rate===null||!isset($factors[$region]))throw new AuthException(422,'مسافت باید دقیقاً در جدول تعرفه و ناحیه در فهرست مبنا وجود داشته باشد.','TARIFF_NOT_FOUND');
  if(!ctype_digit((string)$rate)||(int)$rate>1000000000)self::invalid();
  $base=self::ratio((int)$rate*$factors[$region],100);$work=self::mul($base,$days,30);
  $management=self::mul($work,3,100);$municipality=self::mul($work,275,10000);$vat=self::mul($municipality,10,100);$police=self::mul($work,25,10000);$coordination=self::mul($work,1,100);$company=self::mul($work,7,100);$qualification=self::ratio($work[0]>0?115741:0);
  $subtotal=$work;foreach([$management,$coordination,$company,$qualification] as $part)$subtotal=self::add($subtotal,$part);
  $insurance=self::mul($subtotal,78,1000);$parent=self::add($subtotal,$insurance);$statement=self::add(self::add(self::add($work,$management),$coordination),$company);
  $out=[];foreach(compact('base','work','management','municipality','vat','police','coordination','company','qualification','subtotal','insurance','parent','statement') as $k=>$v)$out[$k]=self::text($v);
  return ['currency'=>'IRR','academicYear'=>'1405/1406','amounts'=>$out,'precision'=>'6 decimal places, truncated for display; rational arithmetic internally','input'=>['vehicleClass'=>(string)$class,'distanceKm'=>$key,'region'=>$region,'days'=>$days]];
 }
 private static function invalid(): never {throw new AuthException(422,'کلاس خودرو، مسافت، ناحیه و کارکرد صحیح بین صفر تا ۳۰ روز لازم است.','VALIDATION_ERROR');}
}

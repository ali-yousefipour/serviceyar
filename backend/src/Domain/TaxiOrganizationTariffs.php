<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use PDOException;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Global, super-admin managed fares, initialized from the supplied 1405-1406 Taxi Organization workbook.
 * A district/class/distance override does not change historical ledger transactions or other districts.
 */
final class TaxiOrganizationTariffs
{
    public const YEAR='1405-1406';
    public const CLASSES=['1'=>'economy','2'=>'standard','3'=>'van'];
    public const TRAFFIC=[1=>0.0,2=>0.10,3=>0.25,4=>0.25,5=>0.10,6=>0.25,7=>0.10,8=>0.0];
    private static ?array $reference=null;

    private static function data(): array {
        if(self::$reference===null)self::$reference=json_decode(file_get_contents(__DIR__.'/public-service-valuation-1405.json'),true,512,JSON_THROW_ON_ERROR);
        return self::$reference;
    }
    public static function district(mixed $region): int {
        $raw=trim(strtr((string)$region,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
        if($raw==='تبادکان'||str_contains($raw,'تبادکان'))return 8;
        if(preg_match('/(?:ناحیه|منطقه)?\s*([1-7])$/u',$raw,$m))return (int)$m[1];
        throw new AuthException(422,'ناحیه مدرسه در بازه ۱ تا ۷ یا تبادکان مشخص نیست.','TAXI_DISTRICT_REQUIRED');
    }
    private static function vehicle(mixed $class): string {
        $v=(string)$class;
        if(isset(self::CLASSES[$v]))$v=self::CLASSES[$v];
        if(!in_array($v,array_values(self::CLASSES),true))throw new AuthException(422,'کلاس خودرو برای محاسبه تعرفه معتبر نیست.','INVALID_TAXI_VEHICLE_CLASS');
        return $v;
    }
    public static function defaults(int $district,string $class): array {
        $data=self::data();$vehicle=null;
        foreach($data['classes'] as $item)if($item['key']===$class){$vehicle=$item;break;}
        if(!$vehicle||!isset(self::TRAFFIC[$district]))throw new AuthException(422,'کلاس خودرو یا ناحیه نامعتبر است.','INVALID_TAXI_TARIFF');
        $traffic=self::TRAFFIC[$district];$items=[];
        foreach($vehicle['brackets'] as $bracket){
            $driver=(int)$bracket['driver_1405_riyal'];
            $parent=(int)round((float)$bracket['parent_monthly_riyal']+$driver*$traffic*1.19658);
            $items[(int)$bracket['distance_km']]=[
                'distanceKm'=>(int)$bracket['distance_km'],'driverMonthly'=>$driver,
                'parentMonthly'=>$parent,'overridden'=>false
            ];
        }
        return $items;
    }
    private static function missingTable(PDOException $e): bool {
        return (int)($e->errorInfo[1]??0)===1146 && str_contains($e->getMessage(),'taxi_organization_tariffs');
    }
    private static function overrides(PDO $pdo,int $district,string $class): array {
        try {
            $q=$pdo->prepare('SELECT distance_km,driver_monthly_irr,parent_monthly_irr FROM taxi_organization_tariffs WHERE academic_year=? AND district=? AND vehicle_class=?');
            $q->execute([self::YEAR,$district,$class]);
            $rows=[];foreach($q as $r)$rows[(int)$r['distance_km']]=[
                'distanceKm'=>(int)$r['distance_km'],
                'driverMonthly'=>(int)$r['driver_monthly_irr'],
                'parentMonthly'=>(int)$r['parent_monthly_irr'],
                'overridden'=>true
            ];
            return $rows;
        } catch(PDOException $e) {
            if(self::missingTable($e))return [];
            throw $e;
        }
    }
    public static function rates(PDO $pdo,int $district,string $class): array {
        return self::overrides($pdo,$district,$class)+self::defaults($district,$class);
    }
    public static function quote(PDO $pdo,int $district,mixed $class,mixed $distance): array {
        $class=self::vehicle($class);
        if(!is_numeric($distance)||!is_finite((float)$distance)||(float)$distance<=0||(float)$distance>200)
            throw new AuthException(422,'مسافت باید عدد مثبت و حداکثر ۲۰۰ کیلومتر باشد.','INVALID_TAXI_DISTANCE');
        $km=max(3,(int)ceil((float)$distance));
        $rates=self::rates($pdo,$district,$class);
        if(isset($rates[$km]))$rate=$rates[$km];
        else {
            $last=$rates[20]??null;
            if(!$last)throw new AuthException(422,'نرخ‌نامه برای این مسافت کامل نیست.','TAXI_TARIFF_UNAVAILABLE');
            $vehicle=null;foreach(self::data()['classes'] as $c)if($c['key']===$class){$vehicle=$c;break;}
            $extra=($km-20)*(int)$vehicle['extra_driver_riyal_per_km'];
            $rate=['distanceKm'=>$km,'driverMonthly'=>$last['driverMonthly']+$extra,
                'parentMonthly'=>(int)round($last['parentMonthly']+$extra*1.19658),'overridden'=>false];
        }
        return $rate+['district'=>$district,'vehicleClass'=>$class,'academicYear'=>self::YEAR,'currency'=>'IRR'];
    }
    public static function quoteStudent(PDO $pdo,int $agencyId,int $schoolId,mixed $class,mixed $distance): array {
        $q=$pdo->prepare('SELECT region FROM schools WHERE id=? AND agency_id=?');
        $q->execute([$schoolId,$agencyId]);$region=$q->fetchColumn();
        if($region===false)throw new AuthException(422,'مدرسه متعلق به شرکت نیست.','SCHOOL_MISMATCH');
        return self::quote($pdo,self::district($region),$class,$distance);
    }
    public static function editor(int $uid,int $district,string $class): array {
        if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط ادمین کل به نرخ‌نامه تاکسیرانی دسترسی دارد.','SUPER_ADMIN_REQUIRED');
        if(!isset(self::TRAFFIC[$district]))throw new AuthException(422,'ناحیه معتبر نیست.','INVALID_DISTRICT');
        $class=self::vehicle($class);$rates=self::rates(Connection::get(),$district,$class);ksort($rates);
        return ['academicYear'=>self::YEAR,'district'=>$district,'vehicleClass'=>$class,'trafficPercent'=>self::TRAFFIC[$district]*100,'rows'=>array_values($rates)];
    }
    public static function save(int $uid,array $input): array {
        if(!Authorization::isSuperAdmin($uid))throw new AuthException(403,'فقط ادمین کل مجاز به تغییر نرخ‌نامه است.','SUPER_ADMIN_REQUIRED');
        $district=(int)($input['district']??0);$class=self::vehicle($input['vehicleClass']??'');
        if(!isset(self::TRAFFIC[$district]))throw new AuthException(422,'ناحیه نامعتبر است.','INVALID_DISTRICT');
        $rows=$input['rows']??null;
        if(!is_array($rows)||count($rows)<1||count($rows)>200)throw new AuthException(422,'حداقل یک و حداکثر ۲۰۰ ردیف نرخ معتبر لازم است.','INVALID_TAXI_ROWS');
        $validated=[];foreach($rows as $r){
            if(!is_array($r))throw new AuthException(422,'ردیف تعرفه معتبر نیست.','INVALID_TAXI_ROW');
            $km=filter_var($r['distanceKm']??null,FILTER_VALIDATE_INT);
            $driver=filter_var($r['driverMonthly']??null,FILTER_VALIDATE_INT);
            $parent=filter_var($r['parentMonthly']??null,FILTER_VALIDATE_INT);
            if($km===false||$km<3||$km>200||$driver===false||$driver<0||$parent===false||$parent<0||
                $driver>999999999999||$parent>999999999999||isset($validated[$km]))
                throw new AuthException(422,'مسافت و مبالغ هر ردیف باید ریال صحیح مثبت و بدون تکرار باشند.','INVALID_TAXI_ROW');
            $validated[$km]=[$km,$driver,$parent];
        }
        $pdo=Connection::get();
        try {
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('INSERT INTO taxi_organization_tariffs(academic_year,district,vehicle_class,distance_km,driver_monthly_irr,parent_monthly_irr,updated_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE driver_monthly_irr=VALUES(driver_monthly_irr),parent_monthly_irr=VALUES(parent_monthly_irr),updated_by=VALUES(updated_by)');
            foreach($validated as [$km,$driver,$parent])$stmt->execute([self::YEAR,$district,$class,$km,$driver,$parent,$uid]);
            $pdo->commit();
        } catch(PDOException $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            if(self::missingTable($e))throw new AuthException(503,'برای ثبت تعرفه تاکسیرانی مهاجرت دیتابیس 0037 را اجرا کنید.','TAXI_TARIFF_MIGRATION_REQUIRED');
            throw $e;
        }
        return self::editor($uid,$district,$class);
    }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

/** Preview-only calculation and explicit finalization of monthly school-service statements. */
final class MonthlyStatements
{
    private const MONTHS=['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

    private static function authorize(int $uid,int $aid,bool $write=false): void
    {
        Authorization::requirePermission($uid,$write?'finance.manage':'finance.view');
        Authorization::requireAgencyAccess($uid,$aid);
        self::ensureStorage();
    }

    /** Hosting is FTP-only; initialize the new archive table on first authorized use. */
    private static function ensureStorage(): void
    {
        static $ready=false;
        if($ready)return;
        $pdo=Connection::get();
        try{$pdo->query('SELECT id FROM school_monthly_statements LIMIT 0');$ready=true;return;}
        catch(\PDOException $e){
            if(($e->errorInfo[1]??null)!==1146)throw $e;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS school_monthly_statements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agency_id BIGINT UNSIGNED NOT NULL,
            jalali_year SMALLINT UNSIGNED NOT NULL,
            jalali_month TINYINT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            academic_year VARCHAR(15) NOT NULL,
            status ENUM('final','deleted') NOT NULL DEFAULT 'final',
            snapshot_json LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NULL,
            restored_by BIGINT UNSIGNED NULL,
            restored_at DATETIME NULL,
            INDEX idx_school_statement_agency_period(agency_id,jalali_year,jalali_month,status),
            CONSTRAINT fk_school_statement_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ready=true;
    }

    private static function range(array $input): array
    {
        $year=filter_var($input['year']??null,FILTER_VALIDATE_INT);
        $month=filter_var($input['month']??null,FILTER_VALIDATE_INT);
        $from=(string)($input['from']??'');
        $to=(string)($input['to']??'');
        if($year===false||$year<1400||$year>1500||$month===false||$month<1||$month>12||
           !OperationsService::validDate($from)||!OperationsService::validDate($to)||
           $from>$to||((strtotime($to)-strtotime($from))/86400)<27||
           ((strtotime($to)-strtotime($from))/86400)>30){
            throw new AuthException(422,'سال، ماه یا بازه صورت‌وضعیت معتبر نیست.','INVALID_STATEMENT_PERIOD');
        }
        // Verify Persian month boundaries; dates must not span a full academic year.
        $start=self::jalaliToGregorian($year,$month,1);
        $end=self::jalaliToGregorian($month===12?$year+1:$year,$month===12?1:$month+1,1);
        $end=date('Y-m-d',strtotime($end.' -1 day'));
        if($from!==$start||$to!==$end)throw new AuthException(422,'بازه انتخابی باید دقیقاً یک ماه شمسی باشد.','INVALID_STATEMENT_PERIOD');
        return [$year,$month,$from,$to];
    }

    private static function jalaliToGregorian(int $jy,int $jm,int $jd): string
    {
        // Integer arithmetic conversion (Jalali 33-year cycle).
        $jy+=1595;
        $days=-355668+365*$jy+intdiv($jy,33)*8+intdiv(($jy%33)+3,4)
             +$jd+($jm<7?($jm-1)*31:($jm-7)*30+186);
        $gy=400*intdiv($days,146097);$days%=146097;
        if($days>36524){$gy+=100*intdiv(--$days,36524);$days%=36524;if($days>=365)$days++;}
        $gy+=4*intdiv($days,1461);$days%=1461;
        if($days>365){$gy+=intdiv($days-1,365);$days=($days-1)%365;}
        $gd=$days+1;$leap=($gy%4===0&&($gy%100!==0||$gy%400===0));
        foreach([31,$leap?29:28,31,30,31,30,31,31,30,31,30,31] as $m=>$length){
            if($gd<=$length)return sprintf('%04d-%02d-%02d',$gy,$m+1,$gd);
            $gd-=$length;
        }
        throw new \LogicException('Invalid Jalali conversion');
    }

    public static function preview(int $uid,int $aid,array $input): array
    {
        self::authorize($uid,$aid);
        [$year,$month,$from,$to]=self::range($input);
        $academic=$month>=7?$year.'-'.($year+1):($year-1).'-'.$year;
        $pdo=Connection::get();
        $q=$pdo->prepare("SELECT st.id AS student_id,CONCAT_WS(' ',st.first_name,st.last_name) student_name,
          st.national_code student_national_code,st.grade,st.monthly_fee,st.tariff_distance_km,
          st.guardian_first_name,st.guardian_last_name,st.mobile,st.address,
          sc.name school_name,sc.region,sc.school_type,
          sv.id service_id,sv.title service_name,sv.code service_code,
          dr.id driver_id,CONCAT_WS(' ',dr.first_name,dr.last_name) driver_name,dr.national_code driver_national_code,
          dr.mobile driver_mobile,dr.iban driver_iban,dr.bank_name,dr.bank_account,
          vh.plate vehicle_plate,vh.model vehicle_model
          FROM service_students ss
          JOIN students st ON st.id=ss.student_id AND st.agency_id=:a
          JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=:agency AND sv.is_active=1
          LEFT JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
          LEFT JOIN drivers dr ON dr.id=sv.driver_id AND dr.agency_id=sv.agency_id
          LEFT JOIN vehicles vh ON vh.id=dr.vehicle_id AND vh.agency_id=dr.agency_id
          WHERE st.is_active=1 AND (REPLACE(st.academic_year,'/','-')=:academic OR st.academic_year IS NULL OR st.academic_year='')
          ORDER BY dr.last_name,dr.first_name,sc.name,st.last_name,st.first_name");
        $q->execute(['a'=>$aid,'agency'=>$aid,'academic'=>$academic]);
        $rates=DriverDeductionPolicy::rates($aid);
        $items=[];$drivers=[];$seenStudents=[];$totals=['students'=>0,'drivers'=>0,'schools'=>0,'gross'=>0.0,'deductions'=>0.0,'net'=>0.0,
            'supervision'=>0.0,'coordination'=>0.0,'overhead'=>0.0,'insurance'=>0.0];
        $schools=[];
        foreach($q->fetchAll() as $row){
            if(isset($seenStudents[$row['student_id']]))throw new AuthException(409,'یک دانش‌آموز به چند سرویس فعال تخصیص داده شده است؛ پیش از ثبت صورت‌وضعیت اصلاح کنید.','DUPLICATE_STUDENT_ASSIGNMENT');
            $seenStudents[$row['student_id']]=true;
            $gross=max(0,(float)$row['monthly_fee']);
            $calc=DriverDeductionPolicy::calculate($gross,$rates);
            $row['gross']=$gross;
            $row['deductions']=$calc['total_deductions'];
            $row['net']=$calc['net_earned'];
            foreach(['supervision','coordination','overhead','insurance'] as $key)$row[$key]=$calc[$key.'_deduction'];
            $items[]=$row;
            $id=(int)$row['driver_id'];
            if(!isset($drivers[$id]))$drivers[$id]=['driver_id'=>$id,'driver_name'=>$row['driver_name']?:'راننده نامشخص',
                'driver_iban'=>$row['driver_iban'],'student_count'=>0,'gross'=>0.0,'deductions'=>0.0,'net'=>0.0,
                'supervision'=>0.0,'coordination'=>0.0,'overhead'=>0.0,'insurance'=>0.0];
            $drivers[$id]['student_count']++;
            foreach(['gross','deductions','net','supervision','coordination','overhead','insurance'] as $key){
                $drivers[$id][$key]+=$row[$key];$totals[$key]+=$row[$key];
            }
            $schools[(string)$row['school_name']]=true;
        }
        $totals['students']=count($items);$totals['drivers']=count($drivers);$totals['schools']=count($schools);
        return ['year'=>$year,'month'=>$month,'month_name'=>self::MONTHS[$month],'from'=>$from,'to'=>$to,
          'academic_year'=>$academic,'rates'=>$rates,'totals'=>$totals,'drivers'=>array_values($drivers),
          'items'=>$items,'status'=>'preview','source'=>'current_active_service_assignments'];
    }

    public static function list(int $uid,int $aid,bool $includeDeleted=false): array
    {
        self::authorize($uid,$aid);
        $q=Connection::get()->prepare('SELECT id,jalali_year,jalali_month,period_start,period_end,academic_year,status,created_at,deleted_at,restored_at,JSON_EXTRACT(snapshot_json,\'$.totals\') AS totals_json FROM school_monthly_statements WHERE agency_id=:a '.($includeDeleted?'':'AND status=\'final\'').' ORDER BY jalali_year DESC,jalali_month DESC,id DESC LIMIT 200');
        $q->execute(['a'=>$aid]);
        return array_map(static function($r){$r['totals']=json_decode((string)$r['totals_json'],true)?:[];unset($r['totals_json']);return $r;},$q->fetchAll());
    }

    public static function get(int $uid,int $aid,int $id): array
    {
        self::authorize($uid,$aid);
        $q=Connection::get()->prepare('SELECT * FROM school_monthly_statements WHERE id=:id AND agency_id=:a');
        $q->execute(['id'=>$id,'a'=>$aid]);$r=$q->fetch();
        if(!$r)throw new AuthException(404,'صورت‌وضعیت یافت نشد.','NOT_FOUND');
        $snapshot=json_decode((string)$r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        $snapshot['id']=(int)$r['id'];$snapshot['status']=$r['status'];
        $snapshot['created_at']=$r['created_at'];$snapshot['deleted_at']=$r['deleted_at'];
        return $snapshot;
    }

    public static function finalize(int $uid,int $aid,array $input): array
    {
        self::authorize($uid,$aid,true);
        $data=self::preview($uid,$aid,$input);
        if(!$data['items'])throw new AuthException(422,'برای این ماه هیچ تخصیص فعال و قابل محاسبه‌ای وجود ندارد.','STATEMENT_EMPTY');
        $pdo=Connection::get();$pdo->beginTransaction();
        try{
            $agencyLock=$pdo->prepare('SELECT id FROM agencies WHERE id=:a FOR UPDATE');$agencyLock->execute(['a'=>$aid]);
            $lock=$pdo->prepare("SELECT id FROM school_monthly_statements WHERE agency_id=:a AND jalali_year=:y AND jalali_month=:m AND status='final' LIMIT 1 FOR UPDATE");
            $lock->execute(['a'=>$aid,'y'=>$data['year'],'m'=>$data['month']]);
            if($lock->fetchColumn())throw new AuthException(409,'برای این ماه صورت‌وضعیت نهایی ثبت شده است.','STATEMENT_EXISTS');
            $q=$pdo->prepare("INSERT INTO school_monthly_statements(agency_id,jalali_year,jalali_month,period_start,period_end,academic_year,snapshot_json,created_by) VALUES(:a,:y,:m,:f,:t,:academic,:json,:u)");
            $q->execute(['a'=>$aid,'y'=>$data['year'],'m'=>$data['month'],'f'=>$data['from'],'t'=>$data['to'],
                'academic'=>$data['academic_year'],'json'=>json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'u'=>$uid]);
            $id=(int)$pdo->lastInsertId();$pdo->commit();
            return self::get($uid,$aid,$id);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function changeStatus(int $uid,int $aid,int $id,bool $restore): array
    {
        self::authorize($uid,$aid,true);
        $pdo=Connection::get();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM school_monthly_statements WHERE id=:id AND agency_id=:a FOR UPDATE');
            $q->execute(['id'=>$id,'a'=>$aid]);$row=$q->fetch();
            if(!$row)throw new AuthException(404,'صورت‌وضعیت یافت نشد.','NOT_FOUND');
            $expected=$restore?'deleted':'final';
            if($row['status']!==$expected)throw new AuthException(409,'وضعیت صورت‌وضعیت تغییر کرده است.','INVALID_STATEMENT_STATUS');
            if($restore){
                $q=$pdo->prepare("SELECT id FROM school_monthly_statements WHERE agency_id=:a AND jalali_year=:y AND jalali_month=:m AND status='final' LIMIT 1 FOR UPDATE");
                $q->execute(['a'=>$aid,'y'=>$row['jalali_year'],'m'=>$row['jalali_month']]);
                if($q->fetchColumn())throw new AuthException(409,'برای این ماه صورت‌وضعیت فعال دیگری وجود دارد.','STATEMENT_EXISTS');
            }
            $sql=$restore?"UPDATE school_monthly_statements SET status='final',restored_by=:u,restored_at=NOW(),deleted_at=NULL,deleted_by=NULL WHERE id=:id AND agency_id=:a"
                :"UPDATE school_monthly_statements SET status='deleted',deleted_by=:u,deleted_at=NOW() WHERE id=:id AND agency_id=:a";
            $pdo->prepare($sql)->execute(['u'=>$uid,'id'=>$id,'a'=>$aid]);$pdo->commit();
            return self::get($uid,$aid,$id);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}

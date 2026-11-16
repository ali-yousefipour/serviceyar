<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

final class SchoolRepresentatives
{
    private static function db(): PDO
    {
        $pdo=Connection::get();
        // Shared hosting may deploy source before a separate SQL migration run.
        $pdo->exec("CREATE TABLE IF NOT EXISTS representative_settings (
          agency_id BIGINT UNSIGNED NOT NULL, driver_id BIGINT UNSIGNED NOT NULL,
          custom_school_scope TINYINT(1) NOT NULL DEFAULT 0,
          PRIMARY KEY(agency_id,driver_id),
          FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
          FOREIGN KEY(driver_id) REFERENCES drivers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS representative_school_access (
          agency_id BIGINT UNSIGNED NOT NULL,driver_id BIGINT UNSIGNED NOT NULL,
          school_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(agency_id,driver_id,school_id),
          INDEX idx_rep_school(agency_id,school_id),
          FOREIGN KEY(driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
          FOREIGN KEY(school_id) REFERENCES schools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $pdo;
    }
    private static function staff(int $uid,int $aid,bool $write=false): PDO
    {
        Authorization::requireAgencyAccess($uid,$aid);
        if(!Authorization::isSuperAdmin($uid)){
            $permissions=Authorization::permissions($uid);
            if(!in_array($write?'users.manage':'users.view',$permissions,true))
                throw new AuthException(403,'برای مدیریت نمایندگان مجوز مدیریت کاربران شرکت لازم است.','FORBIDDEN');
        }
        return self::db();
    }
    private static function driver(PDO $pdo,int $aid,int $driverId): array
    {
        $q=$pdo->prepare("SELECT id,is_representative,first_name,last_name,mobile FROM drivers
          WHERE agency_id=? AND id=? AND is_active=1");
        $q->execute([$aid,$driverId]);
        $driver=$q->fetch(PDO::FETCH_ASSOC);
        if(!$driver)throw new AuthException(404,'راننده انتخاب‌شده در این شرکت یافت نشد.','DRIVER_NOT_FOUND');
        return $driver;
    }
    private static function scopedSchools(PDO $pdo,int $aid,int $driverId): array
    {
        $q=$pdo->prepare("SELECT custom_school_scope FROM representative_settings WHERE agency_id=? AND driver_id=?");
        $q->execute([$aid,$driverId]);
        if((int)$q->fetchColumn()===1){
            $sql="SELECT sc.id,sc.name FROM representative_school_access a
                  JOIN schools sc ON sc.id=a.school_id AND sc.agency_id=a.agency_id AND sc.is_active=1
                  WHERE a.agency_id=? AND a.driver_id=? ORDER BY sc.name,sc.id";
        }else{
            $sql="SELECT DISTINCT sc.id,sc.name FROM services sv
                  JOIN service_students ss ON ss.service_id=sv.id
                  JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id AND st.is_active=1
                  JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=sv.agency_id AND sc.is_active=1
                  WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1
                    AND sv.status<>'cancelled' ORDER BY sc.name,sc.id";
        }
        $query=$pdo->prepare($sql);$query->execute([$aid,$driverId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function portalSchools(int $uid,int $aid,int $driverId): array
    {
        // The verified, live portal membership is mandatory, even for admins viewing portals.
        $pdo=self::db();
        $driver=self::driver($pdo,$aid,$driverId);
        if((int)$driver['is_representative']!==1)return [];
        PortalService::requireDriverMembership($uid,$aid,$driverId);
        return self::scopedSchools($pdo,$aid,$driverId);
    }
    public static function report(int $uid,int $aid,int $driverId,array $filters): array
    {
        $schools=self::portalSchools($uid,$aid,$driverId);
        $schoolId=filter_var($filters['schoolId']??null,FILTER_VALIDATE_INT);
        if(!$schoolId || !in_array((int)$schoolId,array_map(static fn($s)=>(int)$s['id'],$schools),true))
            throw new AuthException(403,'مشاهده بدهی این مدرسه برای نماینده مجاز نیست.','SCHOOL_SCOPE_FORBIDDEN');
        return StudentDebtors::reportForRepresentative($uid,$aid,$filters);
    }
    public static function manageList(int $uid,int $aid): array
    {
        $pdo=self::staff($uid,$aid);
        $q=$pdo->prepare("SELECT d.id,d.first_name,d.last_name,d.mobile,d.is_representative
            FROM drivers d WHERE d.agency_id=? AND d.is_active=1 ORDER BY d.last_name,d.first_name,d.id");
        $q->execute([$aid]);$drivers=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT id,name FROM schools WHERE agency_id=? AND is_active=1 ORDER BY name,id");
        $q->execute([$aid]);$schools=$q->fetchAll(PDO::FETCH_ASSOC);
        $reps=[];
        foreach($drivers as $driver){
            if((int)$driver['is_representative']!==1)continue;
            $q=$pdo->prepare("SELECT custom_school_scope FROM representative_settings WHERE agency_id=? AND driver_id=?");
            $q->execute([$aid,(int)$driver['id']]);
            $driver['customScope']=(int)$q->fetchColumn()===1;
            $driver['schools']=self::scopedSchools($pdo,$aid,(int)$driver['id']);
            $reps[]=$driver;
        }
        return ['representatives'=>$reps,'drivers'=>$drivers,'schools'=>$schools];
    }
    public static function save(int $uid,int $aid,array $data): array
    {
        $pdo=self::staff($uid,$aid,true);
        $driverId=filter_var($data['driverId']??null,FILTER_VALIDATE_INT);
        if(!$driverId)throw new AuthException(422,'راننده معتبر انتخاب کنید.','INVALID_DRIVER');
        self::driver($pdo,$aid,(int)$driverId);
        $enabled=($data['enabled']??true)===true;
        $custom=($data['customScope']??false)===true;
        $ids=$data['schoolIds']??[];
        if(!is_array($ids)||count($ids)>500)throw new AuthException(422,'فهرست مدارس معتبر نیست.','INVALID_SCHOOLS');
        $ids=array_values(array_unique(array_map(static fn($n)=>(int)$n,$ids)));
        if(in_array(0,$ids,true))throw new AuthException(422,'شناسه مدرسه نامعتبر است.','INVALID_SCHOOLS');
        if($custom&&$enabled&&!$ids)throw new AuthException(422,'حداقل یک مدرسه برای نماینده انتخاب کنید.','SCHOOLS_REQUIRED');
        if($ids){
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $q=$pdo->prepare("SELECT COUNT(*) FROM schools WHERE agency_id=? AND is_active=1 AND id IN ($marks)");
            $q->execute([$aid,...$ids]);
            if((int)$q->fetchColumn()!==count($ids))throw new AuthException(422,'برخی مدارس متعلق به این شرکت نیستند.','INVALID_SCHOOLS');
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE drivers SET is_representative=? WHERE id=? AND agency_id=?")->execute([$enabled?1:0,$driverId,$aid]);
            $pdo->prepare("INSERT INTO representative_settings(agency_id,driver_id,custom_school_scope)
                VALUES(?,?,?) ON DUPLICATE KEY UPDATE custom_school_scope=VALUES(custom_school_scope)")
                ->execute([$aid,$driverId,$custom?1:0]);
            $pdo->prepare("DELETE FROM representative_school_access WHERE agency_id=? AND driver_id=?")->execute([$aid,$driverId]);
            if($custom&&$enabled){
                $insert=$pdo->prepare("INSERT INTO representative_school_access(agency_id,driver_id,school_id) VALUES(?,?,?)");
                foreach($ids as $id)$insert->execute([$aid,$driverId,$id]);
            }
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return self::manageList($uid,$aid);
    }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Domain\DomainService;
use ServiceYar\Database\Connection;

final class MapService
{
    public static function points(int $uid,int $aid,int $service): array
    {
        Authorization::requirePermission($uid,'services.view');Authorization::requirePermission($uid,'students.view');Authorization::requireAgencyAccess($uid,$aid);
        $row=DomainService::get($uid,$aid,'services',$service);$school=$row['school_id']?DomainService::get($uid,$aid,'schools',(int)$row['school_id']):null;
        $q=Connection::get()->prepare('SELECT st.id,st.first_name,st.last_name,st.latitude,st.longitude,st.address,ss.pickup_order FROM service_students ss JOIN students st ON st.id=ss.student_id WHERE ss.service_id=:s AND st.agency_id=:a AND st.is_active=1 ORDER BY ss.pickup_order,st.id');$q->execute(['s'=>$service,'a'=>$aid]);return ['service'=>$row,'school'=>$school,'schools'=>\ServiceYar\Domain\OperationsService::serviceSchools($uid,$aid,$service),'students'=>$q->fetchAll()];
    }

    public static function route(int $uid,int $aid,array $body): array
    {
        Authorization::requirePermission($uid,'students.view');Authorization::requirePermission($uid,'schools.view');Authorization::requireAgencyAccess($uid,$aid);
        if(!empty($body['studentId'])){$student=DomainService::get($uid,$aid,'students',(int)$body['studentId']);$schoolId=(int)$student['school_id'];}else{Authorization::requirePermission($uid,'students.manage');$student=['latitude'=>$body['latitude']??null,'longitude'=>$body['longitude']??null];$schoolId=(int)($body['schoolId']??0);}$school=DomainService::get($uid,$aid,'schools',$schoolId);
        $start=self::coordinate($student);$end=self::coordinate($school);
        $startText=$start['latitude'].','.$start['longitude'];$endText=$end['latitude'].','.$end['longitude'];
        return Neshan::route($aid,$startText,$endText);
    }

    private static function coordinate(array $row): array
    {
        return Neshan::coordinate($row['latitude']??null,$row['longitude']??null);
    }
}

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
        $student=DomainService::get($uid,$aid,'students',(int)($body['studentId']??0));$school=DomainService::get($uid,$aid,'schools',(int)$student['school_id']);
        $start=self::coordinate($student);$end=self::coordinate($school);
        $startText=$start['latitude'].','.$start['longitude'];$endText=$end['latitude'].','.$end['longitude'];
        $links=['google'=>'https://www.google.com/maps/dir/?'.http_build_query(['api'=>1,'origin'=>$startText,'destination'=>$endText,'travelmode'=>'driving']),'osm'=>'https://www.openstreetmap.org/directions?'.http_build_query(['engine'=>'fossgis_osrm_car','route'=>$startText.';'.$endText])];
        $key=(string)config('GOOGLE_MAPS_SERVER_KEY','');
        if($key==='')return ['links'=>$links,'calculated'=>false,'reason'=>'google_key_not_configured'];
        $response=JsonHttp::post('https://routes.googleapis.com/directions/v2:computeRoutes',['origin'=>['location'=>['latLng'=>$start]],'destination'=>['location'=>['latLng'=>$end]],'travelMode'=>'DRIVE','routingPreference'=>'TRAFFIC_UNAWARE','units'=>'METRIC'],['X-Goog-Api-Key: '.$key,'X-Goog-FieldMask: routes.distanceMeters,routes.duration']);
        $route=$response['routes'][0]??null;if(!$route)throw new AuthException(422,'مسیر جاده‌ای پیدا نشد.','ROUTE_NOT_FOUND');return ['links'=>$links,'calculated'=>true,'distanceMeters'=>$route['distanceMeters']??null,'duration'=>$route['duration']??null];
    }

    private static function coordinate(array $row): array
    {
        if($row['latitude']===null||$row['longitude']===null)throw new AuthException(422,'مختصات دانش‌آموز و مدرسه باید ثبت شده باشد.','LOCATION_REQUIRED');return ['latitude'=>(float)$row['latitude'],'longitude'=>(float)$row['longitude']];
    }
}

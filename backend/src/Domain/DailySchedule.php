<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
final class DailySchedule {
 public static function report(int $uid,int $aid,array $filter): array {
  Authorization::requireAgencyAccess($uid,$aid);Authorization::requirePermission($uid,'services.view');Authorization::requirePermission($uid,'drivers.view');
  $date=$filter['date']??'';if(!is_string($date)||!OperationsService::validDate($date))throw new AuthException(422,'تاریخ معتبر انتخاب کنید.','VALIDATION_ERROR');
  $p=Connection::get();$q=$p->prepare('SELECT s.id,s.title,s.shift,s.school_id,s.driver_id,d.vehicle_id,d.first_name,d.last_name FROM services s LEFT JOIN drivers d ON d.id=s.driver_id AND d.agency_id=s.agency_id WHERE s.agency_id=? AND s.is_active=1 ORDER BY s.id');$q->execute([$aid]);$rows=[];$issues=[];$cache=[];
  foreach($q->fetchAll() as $service){
   $school=(int)$service['school_id'];if(!$school){$issues[]=['service'=>$service['title'],'reason'=>'مدرسه اصلی مشخص نیست'];continue;}
   if(!isset($cache[$school]))$cache[$school]=SchoolShifts::onDate($uid,$aid,$school,$filter);
   $plan=$cache[$school];$shifts=SchoolShifts::get($uid,$aid,$school);
   if(!$shifts){$issues[]=['service'=>$service['title'],'reason'=>'برنامه شیفت مدرسه تعریف نشده'];continue;}
   if(trim((string)$service['shift'])===''){$issues[]=['service'=>$service['title'],'reason'=>'نام شیفت سرویس مشخص نیست'];continue;}
   $known=array_filter($shifts,fn($s)=>$s['name']===$service['shift']);if(!$known){$issues[]=['service'=>$service['title'],'reason'=>'نام شیفت سرویس با برنامه مدرسه تطبیق ندارد'];continue;}
   if(in_array($service['shift'],$plan['needsWeekAnchor'],true)){$issues[]=['service'=>$service['title'],'reason'=>'مبدأ هفته لازم است'];continue;}
   foreach($plan['shifts'] as $shift){if($shift['name']!==$service['shift'])continue;$rows[]=['serviceId'=>(int)$service['id'],'service'=>$service['title'],'schoolId'=>$school,'shift'=>$shift['name'],'start'=>$shift['start'],'end'=>$shift['end'],'driverId'=>$service['driver_id'],'driver'=>trim(($service['first_name']??'').' '.($service['last_name']??'')),'vehicleId'=>$service['vehicle_id']];if(!$service['driver_id'])$issues[]=['service'=>$service['title'],'reason'=>'راننده تخصیص نیافته'];}
  }
  $conflicts=[];for($i=0;$i<count($rows);$i++)for($j=$i+1;$j<count($rows);$j++){
   $a=$rows[$i];$b=$rows[$j];$sameDriver=$a['driverId']&&$a['driverId']===$b['driverId'];$sameVehicle=$a['vehicleId']&&$a['vehicleId']===$b['vehicleId'];
   if(($sameDriver||$sameVehicle)&&$a['start']<$b['end']&&$b['start']<$a['end'])$conflicts[]=['first'=>$a['service'],'second'=>$b['service'],'reason'=>$sameDriver?'تداخل راننده':'تداخل خودرو'];
  }
  return ['date'=>$date,'items'=>$rows,'issues'=>$issues,'conflicts'=>$conflicts];
 }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
final class DriverAssignment {
 public static function preview(int $uid,int $aid): array {
  Authorization::requireAgencyAccess($uid,$aid);Authorization::requirePermission($uid,'services.manage');Authorization::requirePermission($uid,'drivers.view');
  $p=Connection::get();
  $q=$p->prepare("SELECT s.id,s.title,s.capacity,(SELECT COUNT(*) FROM service_students ss WHERE ss.service_id=s.id) members FROM services s WHERE s.agency_id=? AND s.is_active=1 AND s.driver_id IS NULL ORDER BY s.capacity DESC,s.id");$q->execute([$aid]);$services=$q->fetchAll();
  $q=$p->prepare("SELECT d.id,d.first_name,d.last_name,d.vehicle_id,v.capacity FROM drivers d JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=d.agency_id AND v.is_active=1 WHERE d.agency_id=? AND d.is_active=1 AND d.status='active' AND NOT EXISTS(SELECT 1 FROM services s WHERE s.agency_id=d.agency_id AND s.is_active=1 AND s.driver_id IN (SELECT busy.id FROM drivers busy WHERE busy.agency_id=d.agency_id AND busy.vehicle_id=d.vehicle_id)) ORDER BY v.capacity,d.id");$q->execute([$aid]);$drivers=$q->fetchAll();$used=[];$vehicles=[];$items=[];$unmatched=[];
  foreach($services as $service){$found=false;foreach($drivers as $driver){if(isset($used[$driver['id']])||isset($vehicles[$driver['vehicle_id']])||(int)$driver['capacity']<max((int)$service['capacity'],(int)$service['members']))continue;$used[$driver['id']]=true;$vehicles[$driver['vehicle_id']]=true;$items[]=['serviceId'=>(int)$service['id'],'serviceTitle'=>$service['title'],'driverId'=>(int)$driver['id'],'driverName'=>trim($driver['first_name'].' '.$driver['last_name']),'capacity'=>(int)$driver['capacity']];$found=true;break;}if(!$found)$unmatched[]=['id'=>$service['id'],'title'=>$service['title']];}
  return ['items'=>$items,'unmatched'=>$unmatched];
 }
 public static function apply(int $uid,int $aid,array $body): array {
  Authorization::requireAgencyAccess($uid,$aid);Authorization::requirePermission($uid,'services.manage');Authorization::requirePermission($uid,'drivers.view');
  $rows=$body['items']??null;if(!is_array($rows)||!array_is_list($rows)||!count($rows)||count($rows)>200)throw new AuthException(422,'بین ۱ تا ۲۰۰ پیشنهاد انتخاب کنید.','VALIDATION_ERROR');
  return OperationsService::transaction(function($p)use($uid,$aid,$rows){
   $q=$p->prepare('SELECT id FROM agencies WHERE id=? FOR UPDATE');$q->execute([$aid]);$seen=[];
   foreach($rows as $row){if(!is_array($row)||!is_int($row['serviceId']??null)||!is_int($row['driverId']??null)||isset($seen[$row['serviceId']]))throw new AuthException(422,'پیشنهاد تکراری یا نامعتبر است.','VALIDATION_ERROR');$seen[$row['serviceId']]=true;
    $service=DomainService::get($uid,$aid,'services',$row['serviceId']);$driver=DomainService::get($uid,$aid,'drivers',$row['driverId']);
    $q=$p->prepare('SELECT * FROM services WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$row['serviceId'],$aid]);$service=$q->fetch();
    $q=$p->prepare('SELECT * FROM drivers WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$row['driverId'],$aid]);$driver=$q->fetch();
    $vehicle=DomainService::get($uid,$aid,'vehicles',(int)$driver['vehicle_id']);
    $q=$p->prepare('SELECT * FROM vehicles WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$vehicle['id'],$aid]);$vehicle=$q->fetch();
    $q=$p->prepare('SELECT COUNT(*) FROM service_students WHERE service_id=?');$q->execute([$service['id']]);$members=(int)$q->fetchColumn();
    $q=$p->prepare('SELECT s.id FROM services s JOIN drivers d ON d.id=s.driver_id WHERE s.agency_id=? AND s.is_active=1 AND (d.id=? OR d.vehicle_id=?) FOR UPDATE');$q->execute([$aid,$driver['id'],$vehicle['id']]);$busy=$q->fetchColumn()!==false;
    if(!(int)$service['is_active']||$service['driver_id']!==null||!(int)$driver['is_active']||$driver['status']!=='active'||!(int)$vehicle['is_active']||$busy||(int)$vehicle['capacity']<max($members,(int)$service['capacity']))throw new AuthException(409,'پیشنهاد دیگر معتبر نیست؛ پیش‌نمایش را تازه کنید.','ASSIGNMENT_CONFLICT');
    $p->prepare('UPDATE services SET driver_id=? WHERE id=? AND agency_id=? AND driver_id IS NULL')->execute([$driver['id'],$service['id'],$aid]);OperationsService::audit($uid,$aid,'assign_driver','services',(int)$service['id']);
   }return ['assigned'=>count($rows)];
  });
 }
}

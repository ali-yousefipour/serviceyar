<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

final class SchoolCatalog {
 public static function search(int $uid,int $aid,array $query): array {
  Authorization::requirePermission($uid,'schools.view');
  Authorization::requireAgencyAccess($uid,$aid);
  $pdo=Connection::get();
  $search=trim((string)($query['search']??''));
  $sql="SELECT c.id,c.education_code AS code,c.name,c.region,c.gender,c.shift_name AS shift,c.education_level,c.address,c.phone,c.manager_name,c.latitude,c.longitude,CASE WHEN ac.agency_id IS NULL THEN 0 ELSE 1 END AS selected
    FROM school_catalog c LEFT JOIN agency_school_catalog ac ON ac.catalog_school_id=c.id AND ac.agency_id=:agency
    WHERE (:empty=1 OR c.name LIKE :name OR c.education_code LIKE :code)
    ORDER BY selected DESC,c.name ASC LIMIT 100";
  $q=$pdo->prepare($sql);$q->execute(['agency'=>$aid,'empty'=>$search===''?1:0,'name'=>'%'.$search.'%','code'=>'%'.$search.'%']);
  return $q->fetchAll(PDO::FETCH_ASSOC);
 }
 public static function select(int $uid,int $aid,array $body): array {
  Authorization::requirePermission($uid,'schools.manage');
  Authorization::requireAgencyAccess($uid,$aid);
  $id=filter_var($body['catalogSchoolId']??null,FILTER_VALIDATE_INT);
  if(!$id||$id<1)throw new AuthException(422,'مدرسه انتخاب نشده است.','VALIDATION_ERROR');
  $pdo=Connection::get();$pdo->beginTransaction();
  try {
   $q=$pdo->prepare('SELECT * FROM school_catalog WHERE id=?');$q->execute([$id]);$c=$q->fetch(PDO::FETCH_ASSOC);
   if(!$c)throw new AuthException(404,'مدرسه در فهرست مرکزی پیدا نشد.','NOT_FOUND');
   $q=$pdo->prepare('SELECT linked_school_id FROM agency_school_catalog WHERE agency_id=? AND catalog_school_id=? FOR UPDATE');$q->execute([$aid,$id]);$linked=$q->fetchColumn();
   if($linked){$pdo->commit();return ['schoolId'=>(int)$linked,'alreadySelected'=>true];}
   $q=$pdo->prepare('SELECT id FROM schools WHERE agency_id=? AND code=? ORDER BY id LIMIT 1 FOR UPDATE');$q->execute([$aid,$c['education_code']]);$schoolId=$q->fetchColumn();
   if(!$schoolId){
    $q=$pdo->prepare('INSERT INTO schools(uuid,agency_id,name,code,school_type,manager_name,phone,address,latitude,longitude,region,is_active) VALUES(UUID(),?,?,?,?,?,?,?,?,?,?,1)');
    $q->execute([$aid,$c['name'],$c['education_code'],$c['education_level'],$c['manager_name'],$c['phone'],$c['address'],$c['latitude'],$c['longitude'],$c['region']]);
    $schoolId=$pdo->lastInsertId();
   } else {
    $q=$pdo->prepare('UPDATE schools SET name=?,region=COALESCE(?,region),address=COALESCE(?,address),phone=COALESCE(?,phone),manager_name=COALESCE(?,manager_name),latitude=COALESCE(?,latitude),longitude=COALESCE(?,longitude) WHERE id=? AND agency_id=?');
    $q->execute([$c['name'],$c['region'],$c['address'],$c['phone'],$c['manager_name'],$c['latitude'],$c['longitude'],$schoolId,$aid]);
   }
   $q=$pdo->prepare('INSERT INTO agency_school_catalog(agency_id,catalog_school_id,linked_school_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE linked_school_id=VALUES(linked_school_id)');
   $q->execute([$aid,$id,$schoolId]);$pdo->commit();return ['schoolId'=>(int)$schoolId,'alreadySelected'=>false];
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}

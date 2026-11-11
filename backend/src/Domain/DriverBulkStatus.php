<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
use PDO;

final class DriverBulkStatus {
 public static function apply(int $uid,int $aid,array $body): array {
  Authorization::requirePermission($uid,'drivers.manage');
  Authorization::requireAgencyAccess($uid,$aid);
  $ids=$body['ids']??null;$active=$body['active']??null;
  if(!is_array($ids)||count($ids)<1||count($ids)>200||!in_array($active,[true,false,0,1],true))
   throw new AuthException(422,'بین ۱ تا ۲۰۰ راننده و وضعیت معتبر انتخاب کنید.','VALIDATION_ERROR');
  $unique=[];
  foreach($ids as $id){
   if(!is_int($id)&&!(is_string($id)&&ctype_digit($id)))throw new AuthException(422,'شناسه راننده نامعتبر است.','VALIDATION_ERROR');
   if((int)$id<1)throw new AuthException(422,'شناسه راننده نامعتبر است.','VALIDATION_ERROR');
   $unique[(int)$id]=(int)$id;
  }
  $ids=array_values($unique);$pdo=Connection::get();
  return OperationsService::transaction(function()use($pdo,$uid,$aid,$ids,$active){
   $placeholders=implode(',',array_fill(0,count($ids),'?'));
   $q=$pdo->prepare("SELECT id,is_active FROM drivers WHERE agency_id=? AND id IN ($placeholders) FOR UPDATE");
   $q->execute(array_merge([$aid],$ids));$rows=$q->fetchAll(PDO::FETCH_ASSOC);
   if(count($rows)!==count($ids))throw new AuthException(422,'برخی رانندگان متعلق به این شرکت نیستند یا وجود ندارند.','CROSS_SCOPE_REFERENCE');
   $desired=($active===true||$active===1)?1:0;
   $update=$pdo->prepare('UPDATE drivers SET is_active=? WHERE id=? AND agency_id=?');
   $audit=$pdo->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id) VALUES(?,?,?,?,?)');
   $changed=0;
   foreach($rows as $row){
    if((int)$row['is_active']===$desired)continue;
    $update->execute([$desired,$row['id'],$aid]);
    $audit->execute([$aid,$uid,$desired?'bulk_activate':'bulk_deactivate','drivers',$row['id']]);
    $changed++;
   }
   return ['selected'=>count($ids),'changed'=>$changed,'unchanged'=>count($ids)-$changed,'active'=>(bool)$desired];
  });
 }
}

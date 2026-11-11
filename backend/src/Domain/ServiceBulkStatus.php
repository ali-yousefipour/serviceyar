<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
use PDO;

final class ServiceBulkStatus {
 public static function apply(int $uid,int $aid,array $body): array {
  Authorization::requirePermission($uid,'services.manage');
  Authorization::requireAgencyAccess($uid,$aid);
  $ids=$body['ids']??null;
  $status=$body['status']??null;
  if(!is_array($ids)||count($ids)<1||count($ids)>200||!is_string($status)||!in_array($status,['active','inactive','draft'],true))
   throw new AuthException(422,'بین ۱ تا ۲۰۰ سرویس و وضعیت فعال، غیرفعال یا پیش‌نویس انتخاب کنید.','VALIDATION_ERROR');
  $unique=[];
  foreach($ids as $id){
   if(!is_int($id)&&!(is_string($id)&&ctype_digit($id)))throw new AuthException(422,'شناسه سرویس نامعتبر است.','VALIDATION_ERROR');
   if((int)$id<1)throw new AuthException(422,'شناسه سرویس نامعتبر است.','VALIDATION_ERROR');
   $unique[(int)$id]=(int)$id;
  }
  $ids=array_values($unique);$pdo=Connection::get();
  return OperationsService::transaction(function()use($pdo,$uid,$aid,$ids,$status){
   $placeholders=implode(',',array_fill(0,count($ids),'?'));
   $q=$pdo->prepare("SELECT id,status,is_active FROM services WHERE agency_id=? AND id IN ($placeholders) FOR UPDATE");
   $q->execute(array_merge([$aid],$ids));$rows=$q->fetchAll(PDO::FETCH_ASSOC);
   if(count($rows)!==count($ids))throw new AuthException(422,'برخی سرویس‌ها وجود ندارند یا متعلق به این شرکت نیستند.','CROSS_SCOPE_REFERENCE');
   $active=$status==='inactive'?0:1;
   $update=$pdo->prepare('UPDATE services SET status=?,is_active=? WHERE id=? AND agency_id=?');
   $audit=$pdo->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id) VALUES(?,?,?,?,?)');
   $changed=0;
   foreach($rows as $row){
    if((string)$row['status']===$status&&(int)$row['is_active']===$active)continue;
    $update->execute([$status,$active,$row['id'],$aid]);
    $audit->execute([$aid,$uid,'bulk_status_'.$status,'services',$row['id']]);
    $changed++;
   }
   return ['selected'=>count($ids),'changed'=>$changed,'unchanged'=>count($ids)-$changed,'status'=>$status,'active'=>(bool)$active];
  });
 }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
use PDO;

final class MehrWorkbookAssignments {
 public static function run(int $uid,int $aid,bool $apply=false):array {
  Authorization::requireAgencyAccess($uid,$aid);
  Authorization::requirePermission($uid,'services.manage');
  Authorization::requirePermission($uid,'students.view');
  if($apply)Authorization::requirePermission($uid,'students.manage');
  return OperationsService::transaction(function(PDO $p)use($uid,$aid,$apply){
   $q=$p->prepare('SELECT id FROM agencies WHERE id=? FOR UPDATE');$q->execute([$aid]);
   $rows=$p->query("SELECT * FROM school_assignment_import_mehr1405 WHERE driver_name<>'' ORDER BY student_national_code")->fetchAll(PDO::FETCH_ASSOC);
   $stats=['source'=>count($rows),'matched'=>0,'createdServices'=>0,'assigned'=>0,'skipped'=>[]];
   $school=$p->prepare('SELECT id FROM schools WHERE agency_id=? AND TRIM(name)=? AND is_active=1');
   $student=$p->prepare('SELECT id,school_id FROM students WHERE agency_id=? AND national_code=? AND is_active=1');
   $driver=$p->prepare("SELECT d.id,v.capacity FROM drivers d JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=d.agency_id AND v.is_active=1 WHERE d.agency_id=? AND d.is_active=1 AND d.status='active' AND TRIM(CONCAT(d.first_name,' ',d.last_name))=?");
   $assigned=$p->prepare('SELECT s.id FROM service_students ss JOIN services s ON s.id=ss.service_id WHERE ss.student_id=? AND s.agency_id=? AND s.is_active=1');
   $groups=[];
   foreach($rows as $r){
    $school->execute([$aid,trim($r['school_name'])]);$schools=$school->fetchAll(PDO::FETCH_COLUMN);
    $student->execute([$aid,$r['student_national_code']]);$students=$student->fetchAll(PDO::FETCH_ASSOC);
    $driver->execute([$aid,trim($r['driver_name'])]);$drivers=$driver->fetchAll(PDO::FETCH_ASSOC);
    if(count($schools)!==1||count($students)!==1||count($drivers)!==1||(int)$students[0]['school_id']!==(int)$schools[0]){
     $stats['skipped'][]=['student'=>$r['student_national_code'],'reason'=>'school/student/driver mismatch or ambiguity'];continue;
    }
    $assigned->execute([$students[0]['id'],$aid]);if($assigned->fetchColumn()!==false){
     $stats['skipped'][]=['student'=>$r['student_national_code'],'reason'=>'already assigned to an active service'];continue;
    }
    $d=$drivers[0];$capacity=(int)$d['capacity'];
    if($capacity<1){$stats['skipped'][]=['student'=>$r['student_national_code'],'reason'=>'vehicle capacity unavailable'];continue;}
    $key=$schools[0].':'.$d['id'];$groups[$key]['school']=(int)$schools[0];$groups[$key]['driver']=(int)$d['id'];$groups[$key]['capacity']=$capacity;
    $groups[$key]['students'][]=(int)$students[0]['id'];$stats['matched']++;
   }
   if(!$apply){$stats['groups']=count($groups);$stats['skippedCount']=count($stats['skipped']);return $stats;}
   $insert=$p->prepare("INSERT INTO services(uuid,agency_id,title,code,school_id,driver_id,capacity,status,is_active) VALUES(UUID(),?,?,?,?,?,?,'draft',0)");
   $find=$p->prepare('SELECT id FROM services WHERE agency_id=? AND code=?');
   $member=$p->prepare('INSERT INTO service_students(service_id,student_id,pickup_order) VALUES(?,?,?) ON DUPLICATE KEY UPDATE pickup_order=VALUES(pickup_order)');
   foreach($groups as $g){
    foreach(array_chunk($g['students'],$g['capacity']) as $i=>$chunk){
     $code='mehr1405-import-s'.$g['school'].'-d'.$g['driver'].'-'.($i+1);
     $find->execute([$aid,$code]);$sid=$find->fetchColumn();
     if($sid!==false){$stats['skipped'][]=['service'=>$code,'reason'=>'draft already exists; no overwrite'];continue;}
     $insert->execute([$aid,'پیش‌نویس تخصیص مهر ۱۴۰۵',$code,$g['school'],$g['driver'],$g['capacity']]);
     $sid=(int)$p->lastInsertId();$stats['createdServices']++;
     foreach($chunk as $index=>$studentId){$member->execute([$sid,$studentId,$index+1]);$stats['assigned']++;}
     OperationsService::audit($uid,$aid,'import_mehr_draft','services',$sid);
    }
   }
   $stats['skippedCount']=count($stats['skipped']);return $stats;
  });
 }
}

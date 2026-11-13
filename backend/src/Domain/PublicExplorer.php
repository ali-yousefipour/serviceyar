<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Database\Connection;

/** Public directory exposes only curated company contact fields and limited school previews. */
final class PublicExplorer {
 private static function point(mixed $lat,mixed $lng): array {
  if(!is_numeric($lat)||!is_numeric($lng))return ['latitude'=>null,'longitude'=>null];
  $lat=(float)$lat;$lng=(float)$lng;
  if(!is_finite($lat)||!is_finite($lng)||abs($lat)>90||abs($lng)>180||($lat==0&&$lng==0))
   return ['latitude'=>null,'longitude'=>null];
  return ['latitude'=>round($lat,4),'longitude'=>round($lng,4)];
 }
 private static function settings(PDO $pdo,int $agency): array {
  $q=$pdo->prepare("SELECT setting_key,setting_value FROM agency_settings WHERE agency_id=? AND setting_key IN
    ('company_location','company_manager_name','company_phone','company_office_phone','company_address','company_office_address',
     'public_contact_enabled','public_contact_type','public_office_address','public_manager_name','public_manager_mobile','company_ceo_mobile')");
  $q->execute([$agency]);return $q->fetchAll(PDO::FETCH_KEY_PAIR);
 }
 private static function safeCompany(array $agency,array $settings): array {
  $location=json_decode((string)($settings['company_location']??''),true);
  $point=is_array($location)?self::point($location['latitude']??null,$location['longitude']??null):['latitude'=>null,'longitude'=>null];
  // Public contact preference defaults to manager mobile unless the agency selects its landline.
  $contactType=($settings['public_contact_type']??'mobile')==='landline'?'landline':'mobile';
  $address=(string)(($settings['public_office_address']??'') ?: (($settings['company_address']??'') ?: ($settings['company_office_address']??'')));
  $manager=(string)(($settings['public_manager_name']??'') ?: ($settings['company_manager_name']??''));
  $officePhone=(string)(($settings['company_office_phone']??'') ?: ($settings['company_phone']??''));
  $mobile=(string)(($settings['public_manager_mobile']??'') ?: ($settings['company_ceo_mobile']??''));
  $contactNumber=$contactType==='mobile'?$mobile:$officePhone;
  return [
   'id'=>(int)$agency['id'],'name'=>(string)$agency['name'],
   'latitude'=>$point['latitude'],'longitude'=>$point['longitude'],
   'logoUrl'=>\ServiceYar\Agency\AgencyLogo::hasCustomLogo((int)$agency['id'])?
     '/api/v1/public/company-logo?id='.(int)$agency['id']:null,
   'address'=>$address?:null,
   'managerName'=>$manager?:null,
   'contactType'=>$contactType,'contactNumber'=>$contactNumber?:null,
   'officePhone'=>$contactType==='landline'&&$officePhone!==''?$officePhone:null,
   'managerMobile'=>$contactType==='mobile'&&$mobile!==''?$mobile:null,
   'contactPublished'=>true
  ];
 }
 public static function companies(): array {
  $pdo=Connection::get();
  $agencies=$pdo->query("SELECT id,name FROM agencies WHERE is_active=1 ORDER BY name LIMIT 150")->fetchAll(PDO::FETCH_ASSOC);
  $rows=[];$mapped=0;
  foreach($agencies as $agency){
   $row=self::safeCompany($agency,self::settings($pdo,(int)$agency['id']));
   $rows[]=$row;
   if($row['latitude']!==null)$mapped++;
  }
  return ['companies'=>$rows,'mappedCount'=>$mapped,'totalCompanies'=>count($rows),
   'unmappedCount'=>count($rows)-$mapped];
 }
 public static function company(int $id): ?array {
  if($id<1)return null;
  $pdo=Connection::get();$q=$pdo->prepare('SELECT id,name FROM agencies WHERE id=? AND is_active=1');
  $q->execute([$id]);$agency=$q->fetch(PDO::FETCH_ASSOC);
  return $agency?self::safeCompany($agency,self::settings($pdo,$id)):null;
 }
 public static function schools(string $term): array {
  $pdo=Connection::get();$like='%'.$term.'%';
  $query=$pdo->prepare("SELECT s.id,s.name,s.region,s.school_type,s.address,s.phone,s.manager_name,
     s.latitude,s.longitude,s.photo_path,s.agency_id,a.name AS company_name
     FROM schools s JOIN agencies a ON a.id=s.agency_id AND a.is_active=1
     WHERE s.is_active=1 AND (s.name LIKE :term OR a.name LIKE :agency)
     ORDER BY s.name,s.id LIMIT 20");
  $query->execute(['term'=>$like,'agency'=>$like]);
  $rows=[];$seen=[];
  foreach($query->fetchAll(PDO::FETCH_ASSOC) as $r){
   $id='s'.(int)$r['id'];$seen[trim((string)$r['name']).'|'.trim((string)($r['region']??''))]=true;
   $rows[]=self::schoolRow($r,$id);
  }
  if(count($rows)<20){
   try{
    $query=$pdo->prepare("SELECT c.id,c.name,c.region,c.education_level AS school_type,c.address,c.phone,
      c.manager_name,c.latitude,c.longitude,c.photo_path,
      (SELECT asc2.agency_id FROM agency_school_catalog asc2 JOIN agencies a2 ON a2.id=asc2.agency_id AND a2.is_active=1
        WHERE asc2.catalog_school_id=c.id ORDER BY asc2.agency_id LIMIT 1) AS agency_id,
      (SELECT a3.name FROM agency_school_catalog asc3 JOIN agencies a3 ON a3.id=asc3.agency_id AND a3.is_active=1
        WHERE asc3.catalog_school_id=c.id ORDER BY asc3.agency_id LIMIT 1) AS company_name
      FROM school_catalog c WHERE c.name LIKE :term ORDER BY c.name LIMIT 25");
    $query->execute(['term'=>$like]);
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $r){
     if(count($rows)>=20)break;
     $key=trim((string)$r['name']).'|'.trim((string)($r['region']??''));
     if(isset($seen[$key]))continue;$seen[$key]=true;
     $rows[]=self::schoolRow($r,'c'.(int)$r['id']);
    }
   }catch(\PDOException $e){
    if($e->getCode()!=='42S02'&&(int)($e->errorInfo[1]??0)!==1146)throw $e;
   }
  }
  return ['schools'=>$rows,'minimumSearchLength'=>3];
 }
 private static function schoolRow(array $r,string $id): array {
  $pt=self::point($r['latitude']??null,$r['longitude']??null);
  $expiry=time()+600;
  $secret=(string)config('APP_KEY','').(string)config('DB_PASSWORD','');
  $photo=((string)($r['photo_path']??'')!==''&&$secret!=='')?
   '/api/v1/public/school-photo?school='.$id.'&expires='.$expiry.'&sig='.
    hash_hmac('sha256',$id.'|'.$expiry,$secret):null;
  return ['id'=>$id,'name'=>(string)$r['name'],'region'=>(string)($r['region']??''),
   'type'=>(string)($r['school_type']??''),'address'=>(string)($r['address']??''),
   'phone'=>(string)($r['phone']??''),'managerName'=>(string)($r['manager_name']??''),
   'latitude'=>$pt['latitude'],'longitude'=>$pt['longitude'],'photoUrl'=>$photo,
   'company'=>(string)($r['company_name']??''),
   'companyId'=>isset($r['agency_id'])?(int)$r['agency_id']:null];
 }
 public static function countSchools(): int {
  $pdo=Connection::get();
  $registered=(int)$pdo->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(code,''),CONCAT(name,'|',COALESCE(region,''))))
    FROM schools WHERE is_active=1")->fetchColumn();
  try{
   // Count unique education codes in catalog that do not already exist in registered schools.
   $catalog=(int)$pdo->query("SELECT COUNT(*) FROM school_catalog c WHERE NOT EXISTS
     (SELECT 1 FROM schools s WHERE s.is_active=1 AND
      ((s.code IS NOT NULL AND s.code<>'' AND s.code=c.education_code)
       OR (s.name=c.name AND COALESCE(s.region,'')=COALESCE(c.region,''))))")->fetchColumn();
   return $registered+$catalog;
  }catch(\PDOException $e){
   if($e->getCode()==='42S02'||(int)($e->errorInfo[1]??0)===1146)return $registered;
   throw $e;
  }
 }
}

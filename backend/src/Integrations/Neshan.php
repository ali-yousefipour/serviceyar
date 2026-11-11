<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use ServiceYar\Database\Connection;
use ServiceYar\Auth\AuthException;
final class Neshan {
 public static function settings(int $aid): array {$q=Connection::get()->prepare("SELECT setting_key,setting_value FROM agency_settings WHERE agency_id=? AND setting_key IN ('map_provider','neshan_service_key','neshan_web_key','company_location')");$q->execute([$aid]);return $q->fetchAll(\PDO::FETCH_KEY_PAIR);}
 public static function key(int $aid): string {$s=self::settings($aid);if(!empty($s['neshan_service_key']))return $s['neshan_service_key'];$env=(string)config('NESHAN_SERVICE_KEY','');if($env!=='')return $env;$file=dirname(__DIR__,3).'/storage/cache/neshan-service-key.txt';return is_file($file)?trim(file_get_contents($file)):'';}
 public static function webKey(array $settings): string {
  if(array_key_exists('neshan_web_key',$settings))return (string)$settings['neshan_web_key'];
  $key=(string)config('NESHAN_WEB_KEY','');
  if($key===''){$file=dirname(__DIR__,3).'/storage/cache/neshan-web-key.txt';$key=is_file($file)?trim(file_get_contents($file)):'';}
  return preg_match('/^web\.[a-zA-Z0-9-]{16,200}$/D',$key)?$key:'';
 }
 public static function config(int $aid): array {$s=self::settings($aid);return ['provider'=>'neshan','webKey'=>self::webKey($s),'serviceConfigured'=>self::key($aid)!=='','companyLocation'=>isset($s['company_location'])?json_decode($s['company_location'],true):null];}
 public static function coordinate(mixed $lat,mixed $lng): array {
  if(!is_numeric($lat)||!is_numeric($lng)||!is_finite((float)$lat)||!is_finite((float)$lng)||abs((float)$lat)>90||abs((float)$lng)>180)throw new AuthException(422,'مختصات معتبر منزل و مدرسه را ثبت کنید.','LOCATION_REQUIRED');
  return ['latitude'=>(float)$lat,'longitude'=>(float)$lng];
 }
 public static function request(int $aid,string $path,array $params): array {
  $key=self::key($aid);if($key==='')throw new AuthException(503,'کلید سرویس نشان تنظیم نشده است.','NESHAN_NOT_CONFIGURED');
  if(!function_exists('curl_init'))throw new AuthException(503,'افزونه cURL فعال نیست.','PROVIDER_UNAVAILABLE');
  if(!in_array($path,['/v4/direction','/v3/search','/v5/reverse'],true))throw new \InvalidArgumentException('Unknown Neshan endpoint');
  $curl=curl_init('https://api.neshan.org'.$path.'?'.http_build_query($params));$response='';
  curl_setopt_array($curl,[CURLOPT_HTTPHEADER=>['Api-Key: '.$key,'Accept: application/json'],CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$response){if(strlen($response)+strlen($chunk)>2097152)return 0;$response.=$chunk;return strlen($chunk);}]);
  if(PHP_OS_FAMILY==='Windows'&&defined('CURLSSLOPT_NATIVE_CA'))curl_setopt($curl,CURLOPT_SSL_OPTIONS,CURLSSLOPT_NATIVE_CA);
  $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
  if($ok===false||$status!==200)throw new AuthException(502,'پاسخ سرویس نشان دریافت نشد؛ اعتبار، محدودیت دامنه یا IP و دسترسی سرویس کلید را بررسی کنید.','NESHAN_PROVIDER_ERROR');
  $data=json_decode($response,true);
  if(!is_array($data))throw new AuthException(502,'پاسخ نشان نامعتبر است.','NESHAN_PROVIDER_ERROR');
  return $data;
 }
 public static function route(int $aid,string $origin,string $destination): array {
  return self::routeResult(self::request($aid,'/v4/direction',['type'=>'car','origin'=>$origin,'destination'=>$destination,'alternative'=>'false']));
 }
 public static function routeResult(array $data): array {
  $r=$data['routes'][0]??[];$legs=$r['legs']??[];if(!is_array($legs)||!$legs)throw new AuthException(422,'مسیر پیدا نشد.','ROUTE_NOT_FOUND');
  $distance=0;$duration=0;foreach($legs as $leg){foreach(['distance','duration'] as $field){$v=$leg[$field]['value']??null;if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0)throw new AuthException(502,'پاسخ مسیر نامعتبر است.','NESHAN_PROVIDER_ERROR');}$distance+=$leg['distance']['value'];$duration+=$leg['duration']['value'];}
  $line=$r['overview_polyline']['points']??'';return ['provider'=>'neshan','calculated'=>true,'distanceMeters'=>$distance,'duration'=>$duration.'s','geometry'=>self::decodePolyline(is_string($line)?$line:'')];
 }
 public static function decodePolyline(string $encoded): array {
  if(strlen($encoded)>200000)throw new AuthException(502,'هندسه مسیر نامعتبر است.','NESHAN_PROVIDER_ERROR');
  $points=[];$index=0;$lat=0;$lng=0;$length=strlen($encoded);
  while($index<$length){$delta=[];for($axis=0;$axis<2;$axis++){$value=0;$shift=0;do{if($index>=$length||$shift>30)throw new AuthException(502,'هندسه مسیر نامعتبر است.','NESHAN_PROVIDER_ERROR');$byte=ord($encoded[$index++])-63;if($byte<0||$byte>63)throw new AuthException(502,'هندسه مسیر نامعتبر است.','NESHAN_PROVIDER_ERROR');$value|=($byte&31)<<$shift;$shift+=5;}while($byte>=32);$delta[]=($value&1)?~($value>>1):($value>>1);}$lat+=$delta[0];$lng+=$delta[1];self::coordinate($lat/1e5,$lng/1e5);$points[]=[$lat/1e5,$lng/1e5];if(count($points)>30000)throw new AuthException(502,'هندسه مسیر بیش از حد طولانی است.','NESHAN_PROVIDER_ERROR');}return $points;
 }
 public static function search(int $aid,array $body): array {
  $center=self::coordinate($body['latitude']??null,$body['longitude']??null);$term=$body['term']??'';
  if(!is_string($term)||strlen(trim($term))<3||strlen($term)>400)throw new AuthException(422,'عبارت جستجوی مکان را وارد کنید.','VALIDATION_ERROR');
  $data=self::request($aid,'/v3/search',['q'=>json_encode(['term'=>trim($term),'center'=>$center],JSON_UNESCAPED_UNICODE)]);$items=[];if(!is_array($data['items']??null))throw new AuthException(502,'پاسخ جستجوی نشان نامعتبر است.','NESHAN_PROVIDER_ERROR');
  foreach(array_slice($data['items']??[],0,30) as $row){try{$point=self::coordinate($row['location']['y']??null,$row['location']['x']??null);}catch(AuthException $e){continue;}$items[]=array_merge($point,['title'=>(string)($row['title']??''),'address'=>(string)($row['address']??'')]);}return ['provider'=>'neshan','items'=>$items];
 }
 public static function reverse(int $aid,array $body): array {
  $point=self::coordinate($body['latitude']??null,$body['longitude']??null);$data=self::request($aid,'/v5/reverse',['lat'=>$point['latitude'],'lng'=>$point['longitude']]);
  if(($data['status']??'')!=='OK'||!is_string($data['formatted_address']??null))throw new AuthException(422,'نشانی این نقطه در نشان پیدا نشد.','LOCATION_NOT_FOUND');
  return array_merge($point,['provider'=>'neshan','address'=>$data['formatted_address']]);
 }
}

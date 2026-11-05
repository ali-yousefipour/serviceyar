<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use ServiceYar\Database\Connection;
use ServiceYar\Auth\AuthException;
final class Neshan {
 public static function settings(int $aid): array {$q=Connection::get()->prepare("SELECT setting_key,setting_value FROM agency_settings WHERE agency_id=? AND setting_key IN ('map_provider','neshan_service_key','neshan_web_key')");$q->execute([$aid]);return $q->fetchAll(\PDO::FETCH_KEY_PAIR);}
 public static function key(int $aid): string {$s=self::settings($aid);if(!empty($s['neshan_service_key']))return $s['neshan_service_key'];$env=(string)config('NESHAN_SERVICE_KEY','');if($env!=='')return $env;$file=dirname(__DIR__,3).'/storage/cache/neshan-service-key.txt';return is_file($file)?trim(file_get_contents($file)):'';}
 public static function webKey(array $settings): string {
  if(array_key_exists('neshan_web_key',$settings))return (string)$settings['neshan_web_key'];
  $key=(string)config('NESHAN_WEB_KEY','');
  if($key===''){$file=dirname(__DIR__,3).'/storage/cache/neshan-web-key.txt';$key=is_file($file)?trim(file_get_contents($file)):'';}
  return preg_match('/^web\.[a-zA-Z0-9-]{16,200}$/D',$key)?$key:'';
 }
 public static function config(int $aid): array {$s=self::settings($aid);return ['provider'=>$s['map_provider']??'neshan','webKey'=>self::webKey($s),'serviceConfigured'=>self::key($aid)!==''];}
 public static function route(int $aid,string $origin,string $destination): array {
  $key=self::key($aid);if($key==='')throw new AuthException(503,'کلید سرویس نشان تنظیم نشده است.','NESHAN_NOT_CONFIGURED');
  if(!function_exists('curl_init'))throw new AuthException(503,'افزونه cURL فعال نیست.','PROVIDER_UNAVAILABLE');
  $curl=curl_init('https://api.neshan.org/v4/direction?'.http_build_query(['type'=>'car','origin'=>$origin,'destination'=>$destination,'alternative'=>'false']));$response='';
  curl_setopt_array($curl,[CURLOPT_HTTPHEADER=>['Api-Key: '.$key,'Accept: application/json'],CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$response){if(strlen($response)+strlen($chunk)>2097152)return 0;$response.=$chunk;return strlen($chunk);}]);
  try{$ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);if($ok===false||$status!==200)throw new AuthException(502,'پاسخ مسیریابی نشان تأیید نشد؛ دسترسی کلید را بررسی کنید.','NESHAN_PROVIDER_ERROR');$data=json_decode($response,true);$legs=$data['routes'][0]['legs']??[];if(!$legs)throw new AuthException(422,'مسیر پیدا نشد.','ROUTE_NOT_FOUND');$distance=0;$duration=0;foreach($legs as $leg){if(!is_numeric($leg['distance']['value']??null)||!is_numeric($leg['duration']['value']??null))throw new AuthException(502,'پاسخ مسیر نامعتبر است.','NESHAN_PROVIDER_ERROR');$distance+=$leg['distance']['value'];$duration+=$leg['duration']['value'];}return ['provider'=>'neshan','calculated'=>true,'distanceMeters'=>$distance,'duration'=>$duration.'s'];}finally{curl_close($curl);}
 }
}

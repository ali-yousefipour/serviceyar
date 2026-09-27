<?php
declare(strict_types=1);
namespace ServiceYar\Settings;
use ServiceYar\Auth\Authorization; use ServiceYar\Database\Connection;
final class SettingsService {
 private static function scope(int $uid,int $aid): void { Authorization::requireAgencyAccess($uid,$aid); }
 public static function get(int $uid,int $aid): array { Authorization::requirePermission($uid,'settings.view'); self::scope($uid,$aid); $s=Connection::get()->prepare('SELECT setting_key,setting_value,is_secret FROM agency_settings WHERE agency_id=:a ORDER BY setting_key'); $s->execute(['a'=>$aid]); $out=[]; foreach($s as $r){ if(!(bool)$r['is_secret'])$out[$r['setting_key']]=$r['setting_value']; } return $out; }
 public static function put(int $uid,int $aid,array $settings): array { Authorization::requirePermission($uid,'settings.manage'); self::scope($uid,$aid); $pdo=Connection::get(); $pdo->beginTransaction(); try{ $q=$pdo->prepare('INSERT INTO agency_settings(agency_id,setting_key,setting_value,is_secret) VALUES(:a,:k,:v,0) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); foreach($settings as $k=>$v){ if(!preg_match('/^[a-z][a-z0-9_.-]{1,149}$/i',(string)$k))throw new \ServiceYar\Auth\AuthException(422,'کلید تنظیمات نامعتبر است.','VALIDATION_ERROR'); if(is_array($v)||is_object($v))$v=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $q->execute(['a'=>$aid,'k'=>$k,'v'=>$v===null?null:(string)$v]); } $pdo->commit(); return self::get($uid,$aid); }catch(\Throwable $e){$pdo->rollBack();throw $e;} }
}

<?php
declare(strict_types=1);
namespace ServiceYar\Monitoring;
use ServiceYar\Auth\Authorization; use ServiceYar\Database\Connection;
final class MonitoringService {
 public static function users(int $uid,int $aid): array {
  Authorization::requirePermission($uid,'monitoring.view');Authorization::requireAgencyAccess($uid,$aid);
  $q=Connection::get()->prepare('SELECT u.id,u.username,u.first_name,u.last_name,u.last_login_at,MAX(CASE WHEN s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP() THEN s.last_seen_at ELSE NULL END) last_seen_at,MAX(CASE WHEN s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP() AND s.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) online FROM users u JOIN agency_users au ON au.user_id=u.id LEFT JOIN user_sessions s ON s.user_id=u.id WHERE au.agency_id=:a GROUP BY u.id ORDER BY online DESC,u.last_name');$q->execute(['a'=>$aid]);return $q->fetchAll();
 }
 public static function activity(int $uid,int $aid): array { Authorization::requirePermission($uid,'monitoring.view');Authorization::requireAgencyAccess($uid,$aid);$s=Connection::get()->prepare('SELECT id,user_id,action,entity_type,entity_id,metadata,created_at FROM activity_logs WHERE agency_id=:a ORDER BY id DESC LIMIT 100');$s->execute(['a'=>$aid]);return $s->fetchAll(); }
 public static function locations(int $uid,int $aid,int $serviceId): array { Authorization::requirePermission($uid,'monitoring.view');Authorization::requireAgencyAccess($uid,$aid);$s=Connection::get()->prepare('SELECT id,service_id,latitude,longitude,recorded_at FROM service_locations WHERE agency_id=:a AND service_id=:s ORDER BY recorded_at DESC LIMIT 200');$s->execute(['a'=>$aid,'s'=>$serviceId]);return $s->fetchAll(); }
}

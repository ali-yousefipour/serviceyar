<?php
declare(strict_types=1);
namespace ServiceYar\Monitoring;
use ServiceYar\Auth\Authorization; use ServiceYar\Database\Connection;
final class MonitoringService {
 public static function activity(int $uid,int $aid): array { Authorization::requirePermission($uid,'monitoring.view');Authorization::requireAgencyAccess($uid,$aid);$s=Connection::get()->prepare('SELECT id,user_id,action,entity_type,entity_id,metadata,created_at FROM activity_logs WHERE agency_id=:a ORDER BY id DESC LIMIT 100');$s->execute(['a'=>$aid]);return $s->fetchAll(); }
 public static function locations(int $uid,int $aid,int $serviceId): array { Authorization::requirePermission($uid,'monitoring.view');Authorization::requireAgencyAccess($uid,$aid);$s=Connection::get()->prepare('SELECT id,service_id,latitude,longitude,recorded_at FROM service_locations WHERE agency_id=:a AND service_id=:s ORDER BY recorded_at DESC LIMIT 200');$s->execute(['a'=>$aid,'s'=>$serviceId]);return $s->fetchAll(); }
}

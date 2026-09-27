<?php
declare(strict_types=1);
namespace ServiceYar\AI;
use ServiceYar\Auth\Authorization; use ServiceYar\Database\Connection;
final class AiService {
 public static function history(int $uid,int $aid): array { Authorization::requirePermission($uid,'ai.use');Authorization::requireAgencyAccess($uid,$aid);$s=Connection::get()->prepare('SELECT id,title,created_at,updated_at FROM ai_conversations WHERE agency_id=:a AND user_id=:u ORDER BY id DESC LIMIT 100');$s->execute(['a'=>$aid,'u'=>$uid]);return $s->fetchAll(); }
 public static function create(int $uid,int $aid,string $title): array { Authorization::requirePermission($uid,'ai.use');Authorization::requireAgencyAccess($uid,$aid);$s=Connection::get()->prepare('INSERT INTO ai_conversations(agency_id,user_id,title) VALUES(:a,:u,:t)');$s->execute(['a'=>$aid,'u'=>$uid,'t'=>trim($title)]);return ['id'=>(int)Connection::get()->lastInsertId(),'title'=>trim($title)]; }
}

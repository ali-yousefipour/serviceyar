<?php
declare(strict_types=1);

namespace ServiceYar\Auth;

use ServiceYar\Database\Connection;

final class Authorization
{
    public static function permissions(int $userId): array
    {
        $s=Connection::get()->prepare(
            'SELECT DISTINCT p.name FROM permissions p
             JOIN role_permissions rp ON rp.permission_id=p.id
             JOIN user_roles ur ON ur.role_id=rp.role_id
             JOIN users u ON u.id=ur.user_id
             WHERE u.id=:uid AND u.is_active=1 ORDER BY p.name'
        );
        $s->execute(['uid'=>$userId]);
        return $s->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function requirePermission(int $userId,string $permission): void
    {
        if (!in_array($permission,self::permissions($userId),true)) {
            throw new AuthException(403,'برای انجام این عملیات مجوز ندارید.','FORBIDDEN');
        }
    }
}
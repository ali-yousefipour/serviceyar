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

    public static function isSuperAdmin(int $userId): bool
    {
        $s = Connection::get()->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id JOIN users u ON u.id=ur.user_id WHERE ur.user_id=:uid AND r.name='super_admin' AND u.is_active=1 LIMIT 1");
        $s->execute(['uid'=>$userId]);
        return (bool)$s->fetchColumn();
    }

    public static function requirePermission(int $userId,string $permission): void
    {
        if (!in_array($permission,self::permissions($userId),true)) {
            throw new AuthException(403,'برای انجام این عملیات مجوز ندارید.','FORBIDDEN');
        }
    }

    public static function requireAgencyAccess(int $userId,int $agencyId): void
    {
        if (self::isSuperAdmin($userId)) return;
        $s=Connection::get()->prepare('SELECT 1 FROM agency_users WHERE user_id=:uid AND agency_id=:aid LIMIT 1');
        $s->execute(['uid'=>$userId,'aid'=>$agencyId]);
        if (!$s->fetchColumn()) {
            throw new AuthException(403,'دسترسی به این سازمان مجاز نیست.','SCOPE_FORBIDDEN');
        }
    }
}
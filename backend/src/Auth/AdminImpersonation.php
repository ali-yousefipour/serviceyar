<?php
declare(strict_types=1);

namespace ServiceYar\Auth;

use ServiceYar\Database\Connection;
use ServiceYar\Support\Uuid;
use PDO;

final class AdminImpersonation
{
    private const ADMIN_COOKIE='serviceyar_admin_session';
    private const SESSION_COOKIE='serviceyar_session';
    private const COOKIE_PATH='/api/v1/admin/impersonation';
    private const MAX_DURATION_SECONDS=14400;

    private static function secureCookie(): bool
    {
        return config('APP_SECURE_COOKIE','0')==='1';
    }

    private static function cookieOptions(int $expires,string $path='/'): array
    {
        return [
            'expires'=>$expires,
            'path'=>$path,
            'secure'=>self::secureCookie(),
            'httponly'=>true,
            'samesite'=>'Strict'
        ];
    }

    private static function audit(int $actor,string $action,int $target,int $agency): void
    {
        try{
            Connection::get()->prepare(
                'INSERT INTO audit_logs(uuid,user_id,action,entity_type,entity_id,ip_address,user_agent,metadata)
                 VALUES(?,?,?,?,?,?,?,?)'
            )->execute([
                Uuid::v4(),$actor,$action,'user',(string)$target,
                substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),
                substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),
                json_encode(['targetUserId'=>$target,'agencyId'=>$agency],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)
            ]);
        }catch(\Throwable){}
    }

    private static function adminToken(): string
    {
        $token=$_COOKIE[self::SESSION_COOKIE]??'';
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))
            throw new AuthException(401,'نشست مدیر کل برای ورود نیابتی معتبر نیست.','ADMIN_SESSION_REQUIRED');
        return $token;
    }

    private static function activeUserFilter(PDO $pdo): string
    {
        $columns=$pdo->query("SHOW COLUMNS FROM users LIKE 'deleted_at'")->fetch();
        return $columns?' AND u.deleted_at IS NULL':'';
    }

    private static function requireAdmin(int $userId): void
    {
        if(!Authorization::isSuperAdmin($userId))
            throw new AuthException(403,'فقط ادمین کل مجاز به ورود نیابتی است.','SUPER_ADMIN_REQUIRED');
    }

    public static function users(int $adminId,int $agencyId): array
    {
        self::requireAdmin($adminId);
        if($agencyId<1)throw new AuthException(422,'شرکت انتخاب‌شده معتبر نیست.','INVALID_AGENCY');
        $pdo=Connection::get();
        $filter=self::activeUserFilter($pdo);
        $agency=$pdo->prepare('SELECT id,name FROM agencies WHERE id=? AND is_active=1');
        $agency->execute([$agencyId]);
        $company=$agency->fetch(PDO::FETCH_ASSOC);
        if(!$company)throw new AuthException(404,'شرکت فعال موردنظر پیدا نشد.','AGENCY_NOT_FOUND');

        $sql="SELECT u.id,u.username,u.first_name,u.last_name,u.last_login_at,
            GROUP_CONCAT(DISTINCT r.title ORDER BY r.id SEPARATOR ', ') AS roles
            FROM users u
            JOIN agency_users au ON au.user_id=u.id AND au.agency_id=?
            LEFT JOIN user_roles ur ON ur.user_id=u.id
            LEFT JOIN roles r ON r.id=ur.role_id
            WHERE u.is_active=1 $filter
              AND NOT EXISTS (
                SELECT 1 FROM user_roles sur JOIN roles sr ON sr.id=sur.role_id
                WHERE sur.user_id=u.id AND sr.name='super_admin'
              )
            GROUP BY u.id
            ORDER BY u.first_name,u.last_name,u.username";
        $q=$pdo->prepare($sql);
        $q->execute([$agencyId]);
        return [
            'agency'=>['id'=>(int)$company['id'],'name'=>$company['name']],
            'users'=>$q->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    public static function start(array $adminSession,array $input): array
    {
        $adminId=(int)($adminSession['id']??0);
        self::requireAdmin($adminId);
        $agencyId=filter_var($input['agencyId']??null,FILTER_VALIDATE_INT);
        $targetId=filter_var($input['userId']??null,FILTER_VALIDATE_INT);
        if(!$agencyId||$agencyId<1||!$targetId||$targetId<1)
            throw new AuthException(422,'شرکت و کاربر برای ورود نیابتی باید انتخاب شوند.','VALIDATION_ERROR');

        $adminToken=self::adminToken();
        $adminHash=hash('sha256',$adminToken);
        $pdo=Connection::get();
        $adminQuery=$pdo->prepare(
            'SELECT expires_at FROM user_sessions
             WHERE token_hash=? AND user_id=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP() LIMIT 1'
        );
        $adminQuery->execute([$adminHash,$adminId]);
        $adminExpiry=$adminQuery->fetchColumn();
        if(!$adminExpiry)
            throw new AuthException(401,'نشست مدیر کل منقضی شده است؛ دوباره وارد شوید.','ADMIN_SESSION_EXPIRED');

        $active=$pdo->prepare(
            'SELECT id FROM admin_impersonation_sessions
             WHERE admin_session_hash=? AND ended_at IS NULL LIMIT 1'
        );
        $active->execute([$adminHash]);
        if($active->fetchColumn())
            throw new AuthException(409,'این نشست مدیر کل هم‌اکنون در حالت ورود نیابتی است.','IMPERSONATION_ALREADY_ACTIVE');

        $filter=self::activeUserFilter($pdo);
        $targetQuery=$pdo->prepare(
            "SELECT u.id,u.username,u.first_name,u.last_name,a.name AS agency_name
             FROM users u
             JOIN agency_users au ON au.user_id=u.id AND au.agency_id=?
             JOIN agencies a ON a.id=au.agency_id AND a.is_active=1
             WHERE u.id=? AND u.is_active=1 $filter
               AND NOT EXISTS (
                 SELECT 1 FROM user_roles sur JOIN roles sr ON sr.id=sur.role_id
                 WHERE sur.user_id=u.id AND sr.name='super_admin'
               )
             LIMIT 1"
        );
        $targetQuery->execute([$agencyId,$targetId]);
        $target=$targetQuery->fetch(PDO::FETCH_ASSOC);
        if(!$target)
            throw new AuthException(422,'کاربر انتخاب‌شده فعال نیست یا به شرکت انتخابی تخصیص داده نشده است.','IMPERSONATION_TARGET_NOT_ALLOWED');

        $now=time();
        $adminExpires=strtotime((string)$adminExpiry);
        $targetExpires=min($adminExpires,$now+self::MAX_DURATION_SECONDS);
        if($targetExpires<=$now)
            throw new AuthException(401,'زمان اعتبار نشست مدیر کل پایان یافته است.','ADMIN_SESSION_EXPIRED');
        $expiresAt=gmdate('Y-m-d H:i:s',$targetExpires);
        $targetToken=bin2hex(random_bytes(32));
        $targetHash=hash('sha256',$targetToken);
        $csrf=bin2hex(random_bytes(32));
        $ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
        $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);

        $pdo->beginTransaction();
        try{
            $pdo->prepare(
                'INSERT INTO user_sessions(user_id,token_hash,csrf_token,ip_address,user_agent,expires_at)
                 VALUES(?,?,?,?,?,?)'
            )->execute([$targetId,$targetHash,$csrf,$ip,$ua,$expiresAt]);
            $pdo->prepare(
                'INSERT INTO admin_impersonation_sessions
                 (admin_user_id,target_user_id,agency_id,admin_session_hash,target_session_hash,ip_address,user_agent)
                 VALUES(?,?,?,?,?,?,?)'
            )->execute([$adminId,$targetId,$agencyId,$adminHash,$targetHash,$ip,$ua]);
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }

        setcookie(self::ADMIN_COOKIE,$adminToken,self::cookieOptions($adminExpires,self::COOKIE_PATH));
        setcookie(self::SESSION_COOKIE,$targetToken,self::cookieOptions($targetExpires,'/'));
        $_COOKIE[self::ADMIN_COOKIE]=$adminToken;
        $_COOKIE[self::SESSION_COOKIE]=$targetToken;
        self::audit($adminId,'admin.impersonation.started',$targetId,$agencyId);

        return [
            'started'=>true,
            'agencyId'=>$agencyId,
            'userId'=>$targetId,
            'expiresAt'=>$expiresAt
        ];
    }

    public static function context(string $targetSessionHash): ?array
    {
        try{
            $q=Connection::get()->prepare(
                "SELECT i.admin_user_id AS adminUserId,i.agency_id AS agencyId,a.name AS agencyName,
                    CONCAT_WS(' ',admin.first_name,admin.last_name) AS adminName
                 FROM admin_impersonation_sessions i
                 JOIN agencies a ON a.id=i.agency_id AND a.is_active=1
                 JOIN users admin ON admin.id=i.admin_user_id AND admin.is_active=1
                 JOIN user_sessions adminSession ON adminSession.token_hash=i.admin_session_hash
                    AND adminSession.user_id=i.admin_user_id
                    AND adminSession.revoked_at IS NULL AND adminSession.expires_at>UTC_TIMESTAMP()
                 WHERE i.target_session_hash=? AND i.ended_at IS NULL
                   AND EXISTS (
                    SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id
                    WHERE ur.user_id=i.admin_user_id AND r.name='super_admin'
                   )
                 LIMIT 1"
            );
            $q->execute([$targetSessionHash]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            return $row?:null;
        }catch(\PDOException $e){
            if($e->getCode()==='42S02'||(int)($e->errorInfo[1]??0)===1146)return null;
            throw $e;
        }
    }

    public static function stop(array $targetSession): array
    {
        $targetToken=$_COOKIE[self::SESSION_COOKIE]??'';
        $adminToken=$_COOKIE[self::ADMIN_COOKIE]??'';
        if(!is_string($targetToken)||!preg_match('/^[a-f0-9]{64}$/D',$targetToken)
            ||!is_string($adminToken)||!preg_match('/^[a-f0-9]{64}$/D',$adminToken))
            throw new AuthException(403,'نشست اصلی مدیر کل برای بازگشت در دسترس نیست؛ دوباره وارد سامانه شوید.','IMPERSONATION_RETURN_UNAVAILABLE');

        $targetHash=hash('sha256',$targetToken);
        $adminHash=hash('sha256',$adminToken);
        $pdo=Connection::get();
        $q=$pdo->prepare(
            "SELECT i.admin_user_id,i.target_user_id,i.agency_id,adminSession.expires_at
             FROM admin_impersonation_sessions i
             JOIN user_sessions adminSession ON adminSession.token_hash=i.admin_session_hash
                AND adminSession.user_id=i.admin_user_id
                AND adminSession.revoked_at IS NULL AND adminSession.expires_at>UTC_TIMESTAMP()
             JOIN users admin ON admin.id=i.admin_user_id AND admin.is_active=1
             WHERE i.target_session_hash=? AND i.admin_session_hash=? AND i.ended_at IS NULL
               AND i.target_user_id=?
               AND EXISTS (
                SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id
                WHERE ur.user_id=i.admin_user_id AND r.name='super_admin'
               )
             LIMIT 1"
        );
        $q->execute([$targetHash,$adminHash,(int)$targetSession['id']]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)
            throw new AuthException(403,'نشست اصلی مدیر کل معتبر نیست یا ورود نیابتی به این نشست تعلق ندارد.','IMPERSONATION_RETURN_FORBIDDEN');

        $pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE admin_impersonation_sessions SET ended_at=UTC_TIMESTAMP() WHERE target_session_hash=? AND ended_at IS NULL')
                ->execute([$targetHash]);
            $pdo->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=? AND revoked_at IS NULL')
                ->execute([$targetHash]);
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }

        $adminExpiry=strtotime((string)$row['expires_at']);
        setcookie(self::SESSION_COOKIE,$adminToken,self::cookieOptions($adminExpiry,'/'));
        setcookie(self::ADMIN_COOKIE,'',['expires'=>1,'path'=>self::COOKIE_PATH,'secure'=>self::secureCookie(),'httponly'=>true,'samesite'=>'Strict']);
        $_COOKIE[self::SESSION_COOKIE]=$adminToken;
        unset($_COOKIE[self::ADMIN_COOKIE]);
        self::audit((int)$row['admin_user_id'],'admin.impersonation.ended',(int)$row['target_user_id'],(int)$row['agency_id']);

        return ['returned'=>true,'adminUserId'=>(int)$row['admin_user_id']];
    }

    /** Restore the original administrator session if the target session was revoked or expired. */
    public static function restoreAfterInvalidTarget(string $targetSessionHash): bool
    {
        $adminToken=$_COOKIE[self::ADMIN_COOKIE]??'';
        if(!is_string($adminToken)||!preg_match('/^[a-f0-9]{64}$/D',$adminToken))return false;
        $adminHash=hash('sha256',$adminToken);
        $pdo=Connection::get();
        try{
            $q=$pdo->prepare(
                "SELECT i.admin_user_id,i.target_user_id,i.agency_id,adminSession.expires_at
                 FROM admin_impersonation_sessions i
                 JOIN user_sessions adminSession ON adminSession.token_hash=i.admin_session_hash
                    AND adminSession.user_id=i.admin_user_id
                    AND adminSession.revoked_at IS NULL AND adminSession.expires_at>UTC_TIMESTAMP()
                 JOIN users admin ON admin.id=i.admin_user_id AND admin.is_active=1
                 WHERE i.target_session_hash=? AND i.admin_session_hash=? AND i.ended_at IS NULL
                   AND EXISTS (
                    SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id
                    WHERE ur.user_id=i.admin_user_id AND r.name='super_admin'
                   )
                 LIMIT 1"
            );
            $q->execute([$targetSessionHash,$adminHash]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row)return false;
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE admin_impersonation_sessions SET ended_at=UTC_TIMESTAMP() WHERE target_session_hash=? AND ended_at IS NULL')->execute([$targetSessionHash]);
            $pdo->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=? AND revoked_at IS NULL')->execute([$targetSessionHash]);
            $pdo->commit();
            $expires=strtotime((string)$row['expires_at']);
            setcookie(self::SESSION_COOKIE,$adminToken,self::cookieOptions($expires,'/'));
            setcookie(self::ADMIN_COOKIE,'',['expires'=>1,'path'=>self::COOKIE_PATH,'secure'=>self::secureCookie(),'httponly'=>true,'samesite'=>'Strict']);
            $_COOKIE[self::SESSION_COOKIE]=$adminToken;
            unset($_COOKIE[self::ADMIN_COOKIE]);
            self::audit((int)$row['admin_user_id'],'admin.impersonation.auto_restored',(int)$row['target_user_id'],(int)$row['agency_id']);
            return true;
        }catch(\Throwable){
            if($pdo->inTransaction())$pdo->rollBack();
            return false;
        }
    }
}

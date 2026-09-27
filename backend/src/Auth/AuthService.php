<?php
declare(strict_types=1);

namespace ServiceYar\Auth;

use ServiceYar\Database\Connection;
use PDO;

final class AuthService
{
    private const SESSION_DAYS = 7;

    public function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new AuthException(422, 'نام کاربری و رمز عبور الزامی است.', 'VALIDATION_ERROR');
        }

        $pdo = Connection::get();
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $rate = $pdo->prepare('SELECT COUNT(*) FROM auth_login_attempts WHERE username=:u AND ip_address=:ip AND succeeded=0 AND attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)');
        $rate->execute(['u'=>$username,'ip'=>$ip]);
        if ((int)$rate->fetchColumn() >= 10) throw new AuthException(429, 'تعداد تلاش‌های ورود بیش از حد مجاز است. بعداً دوباره تلاش کنید.', 'LOGIN_RATE_LIMITED');
        $stmt = $pdo->prepare('SELECT id, uuid, username, password_hash, first_name, last_name, is_active FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !(bool)$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            $pdo->prepare('INSERT INTO auth_login_attempts(username,ip_address,succeeded) VALUES(:u,:ip,0)')->execute(['u'=>$username,'ip'=>$ip]);
            $this->audit(null, 'auth.login_failed', null, null);
            throw new AuthException(401, 'نام کاربری یا رمز عبور صحیح نیست.', 'INVALID_CREDENTIALS');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(['hash' => $hash, 'id' => $user['id']]);
        }

        $pdo->prepare('INSERT INTO auth_login_attempts(username,ip_address,succeeded) VALUES(:u,:ip,1)')->execute(['u'=>$username,'ip'=>$ip]);
        $pdo->prepare('DELETE FROM auth_login_attempts WHERE attempted_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)')->execute();

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $csrf = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+' . self::SESSION_DAYS . ' days')->format('Y-m-d H:i:s');

        $pdo->prepare('INSERT INTO user_sessions (user_id, token_hash, csrf_token, ip_address, user_agent, expires_at) VALUES (:uid,:token,:csrf,:ip,:ua,:expires)')
            ->execute([
                'uid'=>$user['id'], 'token'=>$tokenHash, 'csrf'=>$csrf,
                'ip'=>substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'ua'=>substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                'expires'=>$expires
            ]);

        $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id')->execute(['id'=>$user['id']]);
        $this->audit((int)$user['id'], 'auth.login', 'user', (string)$user['id']);

        setcookie('serviceyar_session', $rawToken, ['expires'=>strtotime($expires), 'path'=>'/', 'secure'=>config('APP_SECURE_COOKIE','0') === '1', 'httponly'=>true, 'samesite'=>'Lax']);
        return ['csrfToken'=>$csrf, 'expiresAt'=>$expires, 'user'=>$this->publicUser((int)$user['id'])];
    }

    public function current(): array
    {
        $raw = $this->sessionToken();
        if ($raw === null) {
            throw new AuthException(401, 'نشست معتبر نیست.', 'UNAUTHENTICATED');
        }

        $hash = hash('sha256', $raw);
        $stmt = Connection::get()->prepare(
            'SELECT s.user_id, s.csrf_token, s.expires_at, u.uuid, u.username, u.first_name, u.last_name, u.is_active
             FROM user_sessions s JOIN users u ON u.id=s.user_id
             WHERE s.token_hash=:token AND s.revoked_at IS NULL AND s.expires_at > UTC_TIMESTAMP() LIMIT 1'
        );
        $stmt->execute(['token'=>$hash]);
        $row=$stmt->fetch();

        if (!$row || !(bool)$row['is_active']) {
            throw new AuthException(401, 'نشست منقضی یا لغو شده است.', 'UNAUTHENTICATED');
        }

        Connection::get()->prepare('UPDATE user_sessions SET last_seen_at=CURRENT_TIMESTAMP WHERE token_hash=:token')->execute(['token'=>$hash]);

        return [
            'id'=>(int)$row['user_id'], 'uuid'=>$row['uuid'], 'username'=>$row['username'],
            'firstName'=>$row['first_name'], 'lastName'=>$row['last_name'], 'csrfToken'=>$row['csrf_token']
        ];
    }

    public function logout(): void
    {
        $raw=$this->sessionToken();
        if ($raw===null) return;
        $hash=hash('sha256',$raw);
        $pdo=Connection::get();
        $stmt=$pdo->prepare('SELECT user_id FROM user_sessions WHERE token_hash=:token AND revoked_at IS NULL LIMIT 1');
        $stmt->execute(['token'=>$hash]);
        $uid=$stmt->fetchColumn();
        $pdo->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=:token')->execute(['token'=>$hash]);
        if ($uid) $this->audit((int)$uid,'auth.logout',null,null);
        setcookie('serviceyar_session','',['expires'=>1,'path'=>'/','secure'=>config('APP_SECURE_COOKIE','0') === '1','httponly'=>true,'samesite'=>'Lax']);
    }

    public function requireCsrf(array $session): void
    {
        $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method,['GET','HEAD','OPTIONS'],true)) return;
        $provided=(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($provided==='' || !hash_equals((string)$session['csrfToken'],$provided)) {
            throw new AuthException(419, 'توکن امنیتی درخواست معتبر نیست.', 'CSRF_MISMATCH');
        }
    }

    private function sessionToken(): ?string
    {
        $cookie=$_COOKIE['serviceyar_session'] ?? null;
        if (is_string($cookie) && preg_match('/^[A-Fa-f0-9]{64}$/',$cookie)) return $cookie;
        $header=(string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\\s+([A-Za-z0-9]+)$/',$header,$m)) return $m[1];
        return null;
    }

    private function publicUser(int $id): array
    {
        $pdo=Connection::get();
        $s=$pdo->prepare('SELECT id, uuid, username, first_name, last_name, is_active FROM users WHERE id=:id');
        $s->execute(['id'=>$id]);
        $u=$s->fetch();
        if (!$u) throw new AuthException(500,'کاربر نشست پیدا نشد.','AUTH_STATE_ERROR');
        return ['id'=>(int)$u['id'],'uuid'=>$u['uuid'],'username'=>$u['username'],'firstName'=>$u['first_name'],'lastName'=>$u['last_name'],'isActive'=>(bool)$u['is_active']];
    }

    private function audit(?int $userId,string $action,?string $entityType,?string $entityId): void
    {
        try {
            Connection::get()->prepare('INSERT INTO audit_logs (uuid,user_id,action,entity_type,entity_id,ip_address,user_agent) VALUES (:uuid,:uid,:action,:type,:eid,:ip,:ua)')
                ->execute(['uuid'=>$this->uuid(),'uid'=>$userId,'action'=>$action,'type'=>$entityType,'eid'=>$entityId,'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),'ua'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
        } catch (\Throwable) {}
    }

    private function uuid(): string
    {
        $d=random_bytes(16); $d[6]=chr((ord($d[6])&0x0f)|0x40); $d[8]=chr((ord($d[8])&0x3f)|0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));
    }
}
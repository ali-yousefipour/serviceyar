<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\AuthService;
use ServiceYar\Auth\Authorization;
use ServiceYar\Http\Request;
use ServiceYar\Http\Response;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    if ($method === 'GET' && ($path === '/api/health' || $path === '/api/v1/health')) {
        Response::json(['ok'=>true,'service'=>config('APP_NAME','ServiceYar'),'environment'=>config('APP_ENV','local'),'version'=>'0.2.0','time'=>gmdate('c')]);
    }

    $auth = new AuthService();

    if ($method === 'POST' && $path === '/api/v1/auth/login') {
        $body=Request::json();
        Response::json(['ok'=>true,'data'=>$auth->login((string)($body['username']??''),(string)($body['password']??''))]);
    }

    if ($method === 'POST' && $path === '/api/v1/auth/logout') {
        $session=$auth->current();
        $auth->requireCsrf($session);
        $auth->logout();
        Response::json(['ok'=>true,'data'=>['loggedOut'=>true]]);
    }

    if ($method === 'GET' && $path === '/api/v1/auth/me') {
        $session=$auth->current();
        Response::json(['ok'=>true,'data'=>[
            'user'=>['id'=>$session['id'],'uuid'=>$session['uuid'],'username'=>$session['username'],'firstName'=>$session['firstName'],'lastName'=>$session['lastName']],
            'permissions'=>Authorization::permissions($session['id'])
        ]]);
    }

    if ($method === 'GET' && $path === '/api/v1/dashboard/summary') {
        $session=$auth->current();
        Authorization::requirePermission($session['id'],'dashboard.view');
        $pdo=ServiceYar\\Database\\Connection::get();
        $counts=[];
        foreach (['schools','students','drivers','services'] as $table) {
            try { $counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE is_active=1")->fetchColumn(); }
            catch (\\Throwable) { $counts[$table]=0; }
        }
        Response::json(['ok'=>true,'data'=>['counts'=>$counts]]);
    }

    if ($method === 'GET' && $path === '/api/v1/auth/csrf') {
        $session=$auth->current();
        Response::json(['ok'=>true,'data'=>['csrfToken'=>$session['csrfToken']]]);
    }

    Response::json(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'مسیر مورد نظر پیدا نشد.']],404);
} catch (AuthException $e) {
    Response::json(['ok'=>false,'error'=>['code'=>$e->codeName,'message'=>$e->getMessage()]],$e->status);
} catch (\PDOException $e) {
    error_log($e->getMessage());
    Response::json(['ok'=>false,'error'=>['code'=>'DATABASE_ERROR','message'=>'خطای پایگاه داده.']],500);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    Response::json(['ok'=>false,'error'=>['code'=>'INTERNAL_ERROR','message'=>'خطای داخلی سامانه.']],500);
}

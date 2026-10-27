<?php
declare(strict_types=1);
// Run from the repository root: php -S 127.0.0.1:8080 backend/bin/dev-router.php
$root=dirname(__DIR__,2);
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(str_starts_with($path,'/api/')){require $root.'/backend/public/index.php';return;}
$frontend=realpath($root.'/frontend');
$file=realpath($frontend.($path==='/'?'/index.html':$path));
if(!$file||!str_starts_with($file,$frontend.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);echo 'Not found';return;}
$type=['html'=>'text/html; charset=utf-8','js'=>'application/javascript; charset=utf-8','css'=>'text/css; charset=utf-8','svg'=>'image/svg+xml','woff2'=>'font/woff2'][pathinfo($file,PATHINFO_EXTENSION)]??null;
if(!$type){http_response_code(404);return;}
header('Content-Type: '.$type);header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://tile.openstreetmap.org; style-src 'self'; style-src-attr 'unsafe-inline'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'");
readfile($file);

<?php
declare(strict_types=1);

namespace ServiceYar\Http;

final class Request
{
    public static function json(): array
    {
        $raw=file_get_contents('php://input');
        if ($raw===false || trim($raw)==='') return [];
        try {$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);} catch (\JsonException) {
            Response::json(['ok'=>false,'error'=>['code'=>'INVALID_JSON','message'=>'بدنه JSON معتبر نیست.']],400);
        }
        return is_array($data) ? $data : [];
    }
}
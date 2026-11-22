<?php
declare(strict_types=1);

namespace ServiceYar\Http;

final class Request
{
    /**
     * API mutation payloads have an object-shaped JSON contract.
     * Keep empty bodies backward compatible with endpoints with optional input.
     */
    public static function json(): array
    {
        $raw=file_get_contents('php://input');
        if ($raw===false || trim($raw)==='') return [];
        try {
            $data=json_decode($raw,false,512,JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            Response::json(['ok'=>false,'error'=>['code'=>'INVALID_JSON','message'=>'بدنه JSON معتبر نیست.']],400);
        }
        if (!is_object($data)) {
            Response::json(['ok'=>false,'error'=>['code'=>'INVALID_JSON_OBJECT','message'=>'بدنه درخواست باید یک شیء JSON باشد.']],400);
        }
        return (array)$data;
    }
}

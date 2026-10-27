<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use ServiceYar\Auth\AuthException;

final class JsonHttp
{
    public static function post(string $url,array $body,array $headers=[],int $timeout=25): array
    {
        if(!function_exists('curl_init'))throw new AuthException(503,'افزونه cURL روی سرور فعال نیست.','PROVIDER_UNAVAILABLE');
        $curl=curl_init($url);$response='';
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'],$headers),CURLOPT_CONNECTTIMEOUT=>min(8,$timeout),CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$response){if(strlen($response)+strlen($chunk)>2*1024*1024)return 0;$response.=$chunk;return strlen($chunk);}]);
        try{$ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);if($ok===false||$status<200||$status>=300)throw new AuthException(502,'ارتباط با سرویس‌دهنده تأیید نشد؛ وضعیت را قبل از تلاش مجدد بررسی کنید.','PROVIDER_ERROR');$result=json_decode($response,true,64,JSON_THROW_ON_ERROR);if(!is_array($result))throw new \RuntimeException('Invalid provider response');return $result;}
        catch(\JsonException){throw new AuthException(502,'پاسخ سرویس‌دهنده قابل بررسی نیست.','PROVIDER_ERROR');}
        finally{curl_close($curl);}
    }
}

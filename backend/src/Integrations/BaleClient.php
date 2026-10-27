<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use ServiceYar\Auth\AuthException;

class BaleClient
{
    public static function configured(): bool{return config('BALE_PAYMENTS_ENABLED','0')==='1'&&config('BALE_BOT_TOKEN','')!==''&&config('BALE_WALLET_PROVIDER_TOKEN','')!==''&&!(config('APP_ENV','local')==='production'&&str_starts_with((string)config('BALE_WALLET_PROVIDER_TOKEN'),'WALLET-TEST'));}
    public function request(string $method,array $body): mixed
    {
        if(!self::configured())throw new AuthException(503,'پرداخت کیف پول بله تنظیم یا فعال نشده است.','BALE_NOT_CONFIGURED');
        if(!in_array($method,['sendInvoice','answerPreCheckoutQuery','inquireTransaction'],true))throw new \InvalidArgumentException('Unsupported Bale method');
        $token=(string)config('BALE_BOT_TOKEN');if(!preg_match('/^[A-Za-z0-9:_-]+$/',$token))throw new AuthException(503,'توکن بله نامعتبر است.','BALE_NOT_CONFIGURED');
        $response=JsonHttp::post('https://tapi.bale.ai/bot'.$token.'/'.$method,$body,[],$method==='answerPreCheckoutQuery'?7:25);
        if(($response['ok']??false)!==true)throw new AuthException(502,'درخواست توسط بله تأیید نشد.','BALE_ERROR');return $response['result']??null;
    }
}

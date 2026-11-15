<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use ServiceYar\Auth\AuthException;

class BaleClient
{
    public static function configured(): bool{try{$provider=BaleBotService::walletProviderToken();return BaleBotService::walletEnabled()&&BaleBotService::botToken()!==''&&$provider!==''&&!str_starts_with($provider,'WALLET-TEST');}catch(\Throwable){return false;}}
    public function request(string $method,array $body): mixed
    {
        if(!self::configured())throw new AuthException(503,'پرداخت کیف پول بله تنظیم یا فعال نشده است.','BALE_NOT_CONFIGURED');
        if(!in_array($method,['sendInvoice','answerPreCheckoutQuery','inquireTransaction'],true))throw new \InvalidArgumentException('Unsupported Bale method');
        $token=BaleBotService::botToken();if(!preg_match('/^[A-Za-z0-9:_-]+$/',$token))throw new AuthException(503,'توکن بله نامعتبر است.','BALE_NOT_CONFIGURED');
        $response=JsonHttp::post('https://tapi.bale.ai/bot'.$token.'/'.$method,$body,[],$method==='answerPreCheckoutQuery'?7:25);
        if(($response['ok']??false)!==true)throw new AuthException(502,'درخواست توسط بله تأیید نشد.','BALE_ERROR');return $response['result']??null;
    }
}

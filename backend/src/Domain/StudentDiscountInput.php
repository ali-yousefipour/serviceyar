<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;

/** Strict Persian/Arabic digit and decimal parsing at the request boundary. */
final class StudentDiscountInput
{
    public static function rate(array $input): int
    {
        if (array_key_exists('rateMilliPercent', $input)) {
            $raw=$input['rateMilliPercent'];
            if (!is_int($raw) || $raw<1 || $raw>100000) self::invalid();
            return $raw;
        }
        $raw=$input['percent']??'20';
        if (!is_string($raw) && !is_int($raw)) self::invalid();
        $s=trim(strtr((string)$raw, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '٫'=>'.','٪'=>'','%'=>'',
        ]));
        if (!preg_match('/^([0-9]{1,3})(?:\.([0-9]{1,3}))?$/D',$s,$m)) self::invalid();
        $value=(int)$m[1]*1000+(int)str_pad($m[2]??'',3,'0');
        if ($value<1 || $value>100000) self::invalid();
        return $value;
    }

    private static function invalid(): never
    {
        throw new AuthException(422,'درصد تخفیف باید بیش از صفر و حداکثر ۱۰۰ باشد.','INVALID_DISCOUNT_RATE');
    }
}

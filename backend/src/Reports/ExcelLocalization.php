<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

/**
 * Presentation-only normalization for XLSX exports. Never change persisted
 * values, financial formulas, or interoperable bank identifiers (IBAN).
 */
final class ExcelLocalization
{
    private const DIGITS = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹'];
    private const STATUSES = [
        'active'=>'فعال','inactive'=>'غیرفعال','enabled'=>'فعال','disabled'=>'غیرفعال',
        'approved'=>'تأییدشده','pending'=>'در انتظار تأیید','rejected'=>'ردشده',
        'draft'=>'پیش‌نویس','final'=>'نهایی','deleted'=>'حذف‌شده',
        'paid'=>'پرداخت‌شده','unpaid'=>'پرداخت‌نشده','partial'=>'پرداخت ناقص',
        'partially_paid'=>'پرداخت ناقص','overdue'=>'سررسید گذشته',
        'cancelled'=>'لغوشده','canceled'=>'لغوشده','void'=>'باطل‌شده',
        'open'=>'باز','closed'=>'بسته‌شده','completed'=>'تکمیل‌شده',
        'assigned'=>'تخصیص‌یافته','unassigned'=>'تخصیص‌نیافته',
        'waiting'=>'در انتظار','queued'=>'در صف','sent'=>'ارسال‌شده',
        'failed'=>'ناموفق','success'=>'موفق','successful'=>'موفق',
        'cash'=>'نقدی','card'=>'کارت به کارت','card_to_card'=>'کارت به کارت',
        'pos'=>'کارت‌خوان','bank'=>'پایا','paya'=>'پایا','satna'=>'ساتنا',
        'transfer'=>'انتقال بانکی','cheque'=>'چک','check'=>'چک','wallet'=>'کیف پول',
        'receipt'=>'دریافت','payment'=>'پرداخت','credit'=>'بستانکار','debit'=>'بدهکار',
        'driver'=>'راننده','student'=>'دانش‌آموز','school'=>'مدرسه','agency'=>'شرکت',
        'male'=>'مرد','female'=>'زن','primary'=>'ابتدایی','secondary'=>'متوسطه',
        'morning'=>'صبح','afternoon'=>'بعدازظهر','evening'=>'عصر','both'=>'هردو',
        'yes'=>'بله','no'=>'خیر','true'=>'بله','false'=>'خیر',
        'suspended'=>'تعلیق‌شده','blocked'=>'مسدود','expired'=>'منقضی‌شده',
        'processing'=>'در حال پردازش','in_progress'=>'در حال انجام',
        'new'=>'جدید','no_request'=>'بدون درخواست','prepayment'=>'در انتظار پیش‌پرداخت',
        'waiting_payment'=>'در انتظار پرداخت','waiting_approval'=>'در انتظار تأیید',
        'settled'=>'تسویه‌شده','unsettled'=>'تسویه‌نشده','refunded'=>'بازپرداخت‌شده',
        'bounced'=>'برگشت‌خورده','returned'=>'برگشت‌خورده',
    ];

    public static function digits(string $text): string
    {
        return strtr($text,self::DIGITS);
    }

    public static function header(string $label): string
    {
        return str_replace(['میلادی','Gregorian','(ISO)'],['شمسی','شمسی',''],self::digits($label));
    }

    /**
     * Calendar conversion without php-intl dependency. Input is Gregorian,
     * strictly ISO YYYY-MM-DD (optional time). Jalali dates pass through.
     */
    public static function jalali(string $text): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/D',$text,$m))
            return self::digits($text);
        $gy=(int)$m[1];$gm=(int)$m[2];$gd=(int)$m[3];
        if ($gy<1700 || $gy>2500 || !checkdate($gm,$gd,$gy))return self::digits($text);
        // Gregorian-to-Jalali algorithm using day counts, including leap years.
        $gdm=[0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2=$gm>2?$gy+1:$gy;
        $days=355666+365*$gy+intdiv($gy2+3,4)-intdiv($gy2+99,100)
            +intdiv($gy2+399,400)+$gd+$gdm[$gm-1];
        $jy=-1595+33*intdiv($days,12053);$days%=12053;
        $jy+=4*intdiv($days,1461);$days%=1461;
        if($days>365){$jy+=intdiv($days-1,365);$days=($days-1)%365;}
        if($days<186){$jm=1+intdiv($days,31);$jd=1+$days%31;}
        else {$jm=7+intdiv($days-186,30);$jd=1+($days-186)%30;}
        $date=sprintf('%04d/%02d/%02d',$jy,$jm,$jd);
        if(isset($m[4]))$date.=' '.$m[4].':'.$m[5].(isset($m[6])?':'.$m[6]:'');
        return self::digits($date);
    }

    public static function value(mixed $value, string $field = '', bool $preserveNumeric=true): mixed
    {
        if($value===null)return '';
        if($field==='is_active' || $field==='active')return ((string)$value==='1'||$value===true)?'فعال':'غیرفعال';
        if(is_bool($value))return $value?'بله':'خیر';
        if((is_int($value)||is_float($value))&&$preserveNumeric)return $value;
        $text=trim((string)$value);
        if($text==='')return '';
        // Keep worksheet formula syntax and references in ASCII.
        if($text[0]==='=')return $text;
        // An internationally interoperable account number must retain ASCII.
        if(preg_match('/^IR[0-9]{24}$/i',$text))return strtoupper($text);
        $key=strtolower($text);
        if(isset(self::STATUSES[$key]))return self::STATUSES[$key];
        if(preg_match('/^(?:19|20|21)\d{2}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/D',$text))return self::jalali($text);
        // Convert decimal digits for visible IDs and Persian text, not formulas.
        return self::digits($text);
    }

    public static function row(array $row): array
    {
        foreach($row as $field=>$value){
            $name=(string)$field;
            if(is_int($value)&&preg_match('/(^id$|_id$|^n$|^row_no$|_count$|^capacity$|^rating$)/',$name)){
                $row[$field]=self::digits((string)$value);
                continue;
            }
            // Bank identifiers must remain interoperable ASCII strings.
            if(in_array($name,['iban','bank_account','reference_no','tracking_no','national_code','mobile','phone'],true)){
                $row[$field]=(string)($value??'');
                continue;
            }
            $row[$field]=self::value($value,$name);
        }
        return $row;
    }
}

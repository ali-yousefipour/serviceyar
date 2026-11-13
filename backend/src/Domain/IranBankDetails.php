<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

/** Derives only verifiable bank metadata; an IBAN is not a bank account number. */
final class IranBankDetails
{
    private const BANKS = [
        '010'=>'بانک مرکزی','011'=>'بانک صنعت و معدن','012'=>'بانک ملت',
        '013'=>'بانک رفاه کارگران','014'=>'بانک مسکن','015'=>'بانک سپه',
        '016'=>'بانک کشاورزی','017'=>'بانک ملی ایران','018'=>'بانک تجارت',
        '019'=>'بانک صادرات ایران','020'=>'بانک توسعه صادرات ایران','021'=>'پست بانک ایران','022'=>'بانک توسعه تعاون',
        '025'=>'بانک نامشخص (کد ۰۲۵)','053'=>'بانک کارآفرین','054'=>'بانک پارسیان',
        '055'=>'بانک اقتصاد نوین','056'=>'بانک سامان','057'=>'بانک پاسارگاد',
        '058'=>'بانک سرمایه','059'=>'بانک سینا','060'=>'بانک قرض‌الحسنه مهر ایران',
        '061'=>'بانک شهر','062'=>'بانک آینده','064'=>'بانک گردشگری',
        '066'=>'بانک دی','069'=>'بانک ایران زمین','070'=>'بانک قرض‌الحسنه رسالت',
        '075'=>'مؤسسه اعتباری ملل','078'=>'بانک خاورمیانه',
    ];

    /** Current and historic Iranian banks supported by the accounting bank selector. */
    public static function banks(): array
    {
        return array_values(array_unique(array_filter(self::BANKS,static fn(string $name): bool => !str_contains($name,'نامشخص'))));
    }

    public static function normalize(string $value): string
    {
        $value = strtr($value, array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789')
        ));
        return strtoupper((string)preg_replace('/[\s\x{200C}\-]+/u', '', $value));
    }

    public static function bankName(string $iban): ?string
    {
        $iban = self::normalize($iban);
        if (!PayrollDocuments::validIban($iban)) return null;
        $name = self::BANKS[substr($iban, 4, 3)] ?? null;
        return $name === 'بانک نامشخص (کد ۰۲۵)' ? null : $name;
    }
}

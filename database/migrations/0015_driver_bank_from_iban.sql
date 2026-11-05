-- Repair bank names based on verified Iranian IBANs. Never overwrite bank_account:
-- the 19-digit BBAN is not necessarily the customer-facing account number.
UPDATE drivers SET bank_name = CASE SUBSTRING(iban,5,3)
 WHEN '011' THEN 'بانک صنعت و معدن' WHEN '012' THEN 'بانک ملت'
 WHEN '013' THEN 'بانک رفاه کارگران' WHEN '014' THEN 'بانک مسکن'
 WHEN '015' THEN 'بانک سپه' WHEN '016' THEN 'بانک کشاورزی'
 WHEN '017' THEN 'بانک ملی ایران' WHEN '018' THEN 'بانک تجارت'
 WHEN '019' THEN 'بانک صادرات ایران' WHEN '021' THEN 'پست بانک ایران'
 WHEN '022' THEN 'بانک توسعه تعاون' WHEN '053' THEN 'بانک کارآفرین'
 WHEN '054' THEN 'بانک پارسیان' WHEN '055' THEN 'بانک اقتصاد نوین'
 WHEN '056' THEN 'بانک سامان' WHEN '057' THEN 'بانک پاسارگاد'
 WHEN '058' THEN 'بانک سرمایه' WHEN '059' THEN 'بانک سینا'
 WHEN '060' THEN 'بانک قرض‌الحسنه مهر ایران' WHEN '061' THEN 'بانک شهر'
 WHEN '062' THEN 'بانک آینده' WHEN '064' THEN 'بانک گردشگری'
 WHEN '066' THEN 'بانک دی' WHEN '069' THEN 'بانک ایران زمین'
 WHEN '070' THEN 'بانک قرض‌الحسنه رسالت' WHEN '075' THEN 'مؤسسه اعتباری ملل'
 WHEN '078' THEN 'بانک خاورمیانه' ELSE bank_name END
WHERE iban REGEXP '^IR[0-9]{24}$'
  AND MOD(CAST(CONCAT(SUBSTRING(iban,5),'1827',SUBSTRING(iban,3,2)) AS DECIMAL(30,0)),97)=1;

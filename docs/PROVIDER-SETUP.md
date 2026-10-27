# راه‌اندازی اتصال‌ها و محیط آزمایش

کلیدها و رمزها فقط در `.env` خصوصی سرور قرار می‌گیرند. API تنظیمات فقط آماده‌بودن اتصال را برمی‌گرداند. داده و رمز پنل سمر در این فایل‌ها لازم نیست.

## پیش از انتشار

از پایگاه داده پشتیبان بگیرید، migrationهای `0009_transport_operations.sql`، `0010_provider_integrations.sql` ، `0011_payroll_documents.sql` و `0012_service_schools.sql` را ابتدا روی نسخه آزمایشی اجرا کنید. برای runner:

```powershell
php backend/bin/migrate.php
```

schema باید پیش از استفاده از کد جدید روی سرور حاضر باشد. MySQL اجرای DDL را ضمنی commit می‌کند؛ runner دیگر به‌اشتباه ادعای rollback تراکنش DDL ندارد. در شکست یک migration، وضعیت واقعی schema بررسی شود؛ فایل ناقص را کورکورانه دوباره اجرا نکنید.

نیازمندی‌ها: PHP 8.2+، PDO MySQL، Zip برای Excel، SOAP برای نگین و cURL برای بله و Google. Leaflet داخل پروژه است و نصب npm برای اجرای سایت لازم نیست.

## پیامک نگین

```dotenv
NEGIN_SMS_ENABLED=1
NEGIN_SMS_USERNAME=...
NEGIN_SMS_PASSWORD=...
```

وب‌سرویس همان AlmasSms به نشانی `https://sms.3300.ir/almassms.asmx` است. WSDL محلی بدون credential نگهداری می‌شود. خط ارسال به حساب نگین متصل است. اعتبارسنجی گواهی TLS غیرفعال نشده است؛ در مشکل گواهی، CA سرور اصلاح شود.

فرمان صف بدون `--send` فقط توضیح اجرا می‌دهد:

```powershell
php backend/bin/send-sms.php
php backend/bin/send-sms.php --send
```

اجرای دوم حداکثر ۲۰ پیام در صف را پردازش می‌کند و می‌تواند در زمان‌بند سرور اجرا شود. وضعیت `sent` یعنی پذیرش وب‌سرویس، نه اثبات تحویل به گوشی. در خطای نامطمئن، پیام `uncertain` می‌شود و خودکار تکرار نمی‌شود. قبل از هر بازیابی دستی وضعیت، لاگ پنل نگین بررسی شود. پیام پردازش‌شده از فرم عمومی قابل ویرایش و صف‌گذاری مجدد نیست.

## کیف پول بله

```dotenv
BALE_PAYMENTS_ENABLED=1
BALE_BOT_TOKEN=...
BALE_WALLET_PROVIDER_TOKEN=...
BALE_WEBHOOK_SECRET=...
```

بازو و توکن کیف پول از سرویس بله تهیه می‌شوند. secret تصادفی وب‌هوک باید دست‌کم ۳۲ نویسه از حروف، ارقام، `_` و `-` باشد؛ مثلاً خروجی `bin2hex(random_bytes(32))`. آدرس وب‌هوک:

```text
https://YOUR-DOMAIN/api/v1/integrations/bale/webhook/YOUR_RANDOM_SECRET
```

این آدرس در تنظیمات بازوی بله ثبت می‌شود؛ نصب پروژه خودکار وب‌هوک یا مجوز بیرونی ایجاد نمی‌کند. توکن آزمایشی کیف پول در `APP_ENV=production` پذیرفته نمی‌شود. توکن بازو/secret/درخواست پرداخت در لاگ عمومی چاپ نشود.

در پنل، فاکتور و شناسه عددی گفتگوی پرداخت‌کننده انتخاب می‌شوند. درخواست‌های تکراری فاکتور در بازه فعال دوباره ارسال نمی‌شوند. موفقیت pre-checkout وصول نیست. callback موفق نیز با `inquireTransaction` کنترل می‌شود؛ مبلغ، ریال بودن، کاربر پرداخت‌کننده و شناسه تراکنش باید مطابق درخواست باشند. پردازش چندباره callback فقط یک رکورد پرداخت می‌سازد. پرداخت تأییدشده بله از فرم عمومی قابل ویرایش نیست.

## نقشه

OpenStreetMap در نمایش و انتخاب نقطه به کلید نیاز ندارد و attribution روی نقشه باقی می‌ماند. برای محاسبه درون سامانه با Google Routes:

```dotenv
GOOGLE_MAPS_SERVER_KEY=...
```

کلید باید به API و IP سرور محدود شود. بدون این کلید، لینک مسیریابی Google Maps و OpenStreetMap همچنان تولید می‌شود؛ فاصله جاده‌ای ساختگی نمایش داده نمی‌شود. محاسبه Google روی کلیک مسیریابی انجام می‌شود، نه خودکار برای همه دانش‌آموزان. OSM منبع مسیریابی اختصاصی با SLA برای این نصب فراهم نمی‌کند؛ در مصرف زیاد، سرویس کاشی مناسب یا میزبانی اختصاصی انتخاب شود.

## اجرای محلی و آزمون

یک دیتابیس مجزا با نام شروع‌شونده با `serviceyar_test` بسازید. دیتابیس عملیاتی نباید برای fixture یا آزمون انتخاب شود.

```powershell
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_DATABASE='serviceyar_test'
$env:DB_USERNAME='TEST_DATABASE_USER'
$env:DB_PASSWORD='TEST_DATABASE_PASSWORD'
$env:SERVICEYAR_TEST_DATABASE='1'
php backend/bin/migrate.php
php tests/transport-integration.php
php tests/seed-preview.php
php -S 127.0.0.1:8080 backend/bin/dev-router.php
```

در shell دوم با همان opt-in آزمون:

```powershell
$env:SERVICEYAR_TEST_DATABASE='1'
node tests/api-integration.mjs
```

fixture فقط در دیتابیس آزمایشی، حساب محلی `preview` با رمز `Preview-Only-2026!` می‌سازد. این حساب و اطلاعات بانکی/اشخاص نمونه نباید در دیتابیس عملیاتی ساخته شوند. فایل `.env` موجود در root بر متغیر محیطی اولویت دارد؛ هنگام آزمون مطمئن شوید به دیتابیس آزمایشی اشاره می‌کند. خروجی‌ها و runtimeهای آزمایشی در `storage/cache/` از Git مستثنا هستند.

## انتشار و وضعیت تأیید

workflow انتشار، فایل‌های رابط جدید، نقشه و مجوز Leaflet را از staging منتقل و byte-compare می‌کند. این تغییرات روی هاست زنده اجرا نشده‌اند. پیش از فعال‌کردن worker پیامک یا پرداخت واقعی، آزمون محیط staging با حساب‌های متعلق به کاربر و پیکربندی صحیح کامل شود. خروجی بانک CSV به‌تنهایی انتقال وجه نیست و سازگاری قالب اختصاصی بانک مقصد باید جداگانه تأیید شود.

# Phase 17 — Production Hardening

## هدف
آماده‌سازی ServiceYar برای استقرار واقعی بدون قرار دادن Secret در Frontend و بدون تضعیف Scope و Authorization.

## انجام‌شده
- تنظیم طول عمر Session از متغیر محیطی `SESSION_LIFETIME`.
- مقدار پیش‌فرض `SESSION_LIFETIME=604800` ثانیه.
- `APP_DEBUG=false` در نمونه Environment.
- فعال‌سازی سیاست عدم نمایش خطاهای PHP در محیط production.
- CORS فقط برای Originهای صریح موجود در `CORS_ALLOWED_ORIGINS`.
- پشتیبانی از preflight درخواست‌های CORS با پاسخ 204.
- HSTS فقط وقتی محیط production و Cookie امن فعال باشد.
- هدرهای امنیتی CSP، X-Content-Type-Options، X-Frame-Options و Referrer-Policy حفظ و بررسی می‌شوند.
- CI شامل PHP lint و static security smoke test است.

## متغیرهای Production
```text
APP_ENV=production
APP_DEBUG=false
APP_SECURE_COOKIE=1
APP_URL=https://service-yar.ir
SESSION_LIFETIME=604800
CORS_ALLOWED_ORIGINS=https://service-yar.ir
```

## دامنه عملیاتی و تنظیمات هاست

- دامنه اصلی و مرجع سامانه: `https://service-yar.ir`.
- نشانی `www.service-yar.ir` به دامنه اصلی هدایت می‌شود.
- درخواست‌های HTTP دامنه اصلی به HTTPS هدایت می‌شوند.
- در فایل `.env` موجود روی هاست، مقادیر `APP_URL` و `CORS_ALLOWED_ORIGINS` باید دقیقاً مطابق دامنه بالا تنظیم شوند.
- فایل `.env` و رمزهای دیتابیس خارج از مخزن هستند و فرایند Deploy آن‌ها را بازنویسی نمی‌کند؛ این مقادیر باید در تنظیمات هاست نیز بررسی شوند.
- گواهی SSL باید هر دو نام `service-yar.ir` و در صورت استفاده `www.service-yar.ir` را پوشش دهد؛ فعال‌بودن گواهی فقط برای `www` کافی نیست.
- مطابق تنظیم جدید حساب FTP، ورود این حساب مستقیماً به پوشه فیزیکی `/public_html` محدود شده است؛ به همین دلیل مسیر مقصد در محیط FTP برابر `/` است، نه `/public_html`. ابزار انتشار پیش از هر تغییر وجود `index.html`، `assets` و `backend` را در ریشه FTP کنترل می‌کند. بررسی گواهی SSL با درخواست مالک از شرط انتشار حذف شده، ولی مشکل گواهی دامنه باید جداگانه رفع شود.

## استقرار
1. در نصب فعلی هاست اشتراکی، Document Root روی `/public_html` است و فایل `index.html` در ریشه آن قرار دارد؛ درخواست‌های API از طریق قواعد `.htaccess` به `backend/public/index.php` هدایت می‌شوند. اگر در آینده معماری هاست تغییر کند، مسیر جدید باید پیش از انتشار بررسی شود.
2. در این نصب، `.env` در `/public_html` نگهداری می‌شود و با `.htaccess` دسترسی مستقیم HTTP به آن مسدود است؛ آن را Commit نکنید. در صورت پشتیبانی هاست، نگهداری خارج از Web Root امن‌تر است.
3. HTTPS و گواهی معتبر فعال باشد.
4. Migrationها دقیقاً به ترتیب اجرا شوند.
5. قبل از فعال‌سازی عمومی، `GET /api/v1/health/ready` بررسی شود.
6. دسترسی نوشتن فقط برای مسیرهای runtime موردنیاز مانند `storage` داده شود.
7. Backup منظم دیتابیس و تست Restore انجام شود.
8. لاگ‌های PHP و وب‌سرور به‌صورت دوره‌ای Rotate و پایش شوند.

## Rollback
- قبل از هر Migration یک Backup معتبر تهیه شود.
- Release/Commit قبلی قابل بازگشت نگه داشته شود.
- در صورت خطای استقرار، ترافیک ابتدا از نسخه جدید خارج و سپس نسخه پایدار قبلی فعال شود.
- Migrationهای مخرب یا DROP گسترده بدون برنامه بازگشت مجاز نیستند.

## کنترل نهایی
Phase 17 زمانی کامل است که Deployment واقعی، HTTPS، Backup/Restore، Log Rotation، Monitoring و Rollback روی محیط مقصد اجرا و ثبت شده باشند.

## تنظیم اتصال دیتابیس فعلی روی هاست (Production)

برای نصب فعلی روی هاست اشتراکی، فایل `.env` باید در ریشه `public_html` باشد؛ کد آن را از همان مسیر می‌خواند. این فایل در مخزن نیست و نباید به Git اضافه شود.

نام دیتابیس و کاربر فعلی (`challi_ali`) مستقل از دامنه وب است و با تغییر دامنه نباید تغییر کند.

مقادیر اتصال روی هاست:
```dotenv
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=challi_ali
DB_USERNAME=challi_ali
DB_PASSWORD=رمز_دیتابیس
```

مقدار `DB_PASSWORD` باید مستقیماً در فایل `.env` روی هاست وارد شود. رمز دیتابیس عمداً در سورس‌کد، `.env.example`، لاگ‌ها یا commit ذخیره نمی‌شود. پس از ذخیره، مجوز فایل را تا حد امکان به مالک محدود کنید؛ قاعده منع دسترسی به فایل‌های `.env` در `.htaccess` حفظ شود.

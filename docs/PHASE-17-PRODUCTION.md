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
APP_URL=https://YOUR-DOMAIN
SESSION_LIFETIME=604800
CORS_ALLOWED_ORIGINS=https://YOUR-DOMAIN
```

## استقرار
1. Document Root وب‌سرور فقط روی `backend/public` قرار گیرد.
2. فایل `.env` خارج از Web Root نگهداری شود و Commit نشود.
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

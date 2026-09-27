# ServiceYar — Phase 1: Authentication & Authorization

## وضعیت
Phase 1 زیرساخت احراز هویت و مجوزها را به صورت Server-side اضافه می‌کند.

### API
- POST /api/v1/auth/login
- POST /api/v1/auth/logout
- GET /api/v1/auth/me
- GET /api/v1/auth/csrf

### نشست
- نشست تصادفی 256 بیتی
- ذخیره SHA-256 نشست در DB، نه توکن خام
- Cookie با HttpOnly و SameSite=Lax
- انقضای 7 روزه
- امکان Revocation
- ثبت ورود، خروج و ورود ناموفق در audit_logs
- پشتیبانی از Bearer برای سازگاری API

### مجوز
Permissionها از role و user_role محاسبه می‌شوند و در Backend کنترل می‌شوند.
Roleهای پایه:
- super_admin
- agency_admin
- operator

### CSRF
درخواست‌های تغییر وضعیت پس از احراز هویت به X-CSRF-Token معتبر نیاز دارند.

## نکات مهم
- هیچ شناسه agency/school/driver از کلاینت به تنهایی Scope دسترسی را تعیین نمی‌کند.
- رمز عبور با password_hash ذخیره و با password_verify بررسی می‌شود.
- اطلاعات حساس در پاسخ API حداقلی است.
- خطاهای داخلی در Production به کاربر نمایش داده نمی‌شوند.

## Migration
فایل database/migrations/002_auth_sessions.sql جدول نشست و Role/Permissionهای پایه را ایجاد می‌کند.

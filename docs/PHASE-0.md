# Phase 0 — پایه پروژه

## وضعیت

Phase 0 شامل اسکلت اولیه پروژه، Backend، Frontend، API health، اتصال PDO، Migrationهای پایه و قواعد امنیتی اولیه است.

## تست‌های لازم پیش از Phase 1

- اجرای syntax check تمام فایل‌های PHP
- اجرای Migrationها روی DB آزمایشی
- بررسی GET /api/health
- بررسی GET /api/v1/health
- بررسی پاسخ 404 برای مسیر ناشناخته
- بررسی Headerهای امنیتی
- بررسی CORS در محیط Production
- تست اتصال Frontend به API

## موارد عمداً به Phase 1 موکول شده

- Login واقعی
- Session/Token
- Password hashing flow
- RBAC کامل
- Object-level authorization
- CSRF در صورت انتخاب Cookie Session
- Rate limiting عملیاتی

این موارد نباید با یک پیاده‌سازی موقت و ناامن وارد Phase 0 شوند.

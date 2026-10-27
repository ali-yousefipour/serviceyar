# ServiceYar

سامانه مدیریت سرویس مدارس.

رابط عملیاتی، پرونده دانش‌آموز و ولی، مدیریت سرویس و ظرفیت، ورود Excel، گردش مالی، اسناد حقوق و اتصال نگین/بله/نقشه توسعه داده شده‌اند. جزئیات قابلیت‌ها، آزمون‌ها و مواردی که هنوز معادل کامل مرجع ندارند در [گزارش تطبیق سمر](docs/SAMAR-PARITY.md) آمده است. برای اتصال‌ها و آزمون محلی، [راهنمای راه‌اندازی](docs/PROVIDER-SETUP.md) را ببینید.

## Phase 0

زیرساخت اولیه Phase 0 شامل موارد زیر بوده است:

- Backend مستقل PHP 8.2+
- API نسخه‌بندی‌شده با `/api/v1`
- Health Check
- تنظیمات Environment
- اتصال PDO به MySQL/MariaDB
- Migration پایه
- Frontend مستقل RTL
- ساختار ذخیره‌سازی runtime
- قواعد اولیه Git و امنیت

## ساختار

```
backend/
  public/
    index.php
  src/
    Config/
    Database/
    Http/
    Support/
frontend/
  index.html
  assets/
    app.js
    app.css
database/
  migrations/
docs/
storage/
  cache/
  logs/
  uploads/
```

## نیازمندی

- PHP 8.2+
- MySQL 8+ یا MariaDB 10.6+
- PDO MySQL
- Web server با rewrite به `backend/public/index.php`

## اجرا

1. فایل `.env.example` را به `.env` تبدیل کنید.
2. اطلاعات DB را تنظیم کنید.
3. Migrationهای پوشه `database/migrations` را به ترتیب اجرا کنید.
4. برای توسعه:

```powershell
php -S 127.0.0.1:8080 backend/bin/dev-router.php
```

سپس:

```
http://localhost:8080/api/health
```

## API

```
GET /api/health
GET /api/v1/health
```

احراز هویت، نشست، CSRF و کنترل دسترسی سازمانی در API فعال هستند.

## اصل امنیتی

هیچ `agencyId`، `schoolId` یا شناسه مشابهی که از کلاینت دریافت می‌شود نباید به‌تنهایی Scope دسترسی را تعیین کند. Scope باید در Backend و بر اساس نشست/مجوز کاربر اعمال شود.

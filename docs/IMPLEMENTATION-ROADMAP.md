# ServiceYar — Implementation Roadmap

## 1. هدف پروژه

ServiceYar یک سامانه مستقل برای مدیریت سرویس مدارس است که با تحلیل فرایندها و APIهای سامانه موجود، با معماری تمیز، امنیت سمت سرور، رابط کاربری RTL و تقویم جلالی ساخته می‌شود.

اصل مهم: هیچ اطلاعات، مجوز یا شناسه‌ای که از سمت کلاینت ارسال می‌شود نباید به‌تنهایی مبنای دسترسی باشد. تمام مجوزها و محدوده داده در Backend کنترل می‌شوند.

## 2. محدوده سامانه

### هویت و دسترسی
- ورود، خروج و مدیریت نشست
- کاربران، نقش‌ها و مجوزها
- محدوده دسترسی بر اساس سازمان/آژانس
- کنترل دسترسی عملیاتی و شیءمحور
- ثبت رویدادهای امنیتی و فعالیت کاربران

### داشبورد
- شاخص‌های مدارس، دانش‌آموزان، رانندگان و سرویس‌ها
- وضعیت قراردادها و عملیات جاری
- گزارش فعالیت
- هشدارها و اعلان‌ها

### مدارس
- فهرست و جست‌وجوی مدارس
- ایجاد، ویرایش و غیرفعال‌سازی
- اطلاعات تماس و مدیر
- موقعیت جغرافیایی
- قرارداد مدرسه
- محدوده/منطقه و اطلاعات مرجع

### دانش‌آموزان
- ثبت و ویرایش
- اطلاعات والدین/ولی
- مدرسه و پایه
- آدرس و موقعیت
- تخصیص به سرویس
- وضعیت قرارداد و حذف/بازیابی
- کنترل دقیق دسترسی به اطلاعات حساس

### رانندگان
- ثبت درخواست/پرونده
- اطلاعات هویتی
- مدارک
- امتیاز و وضعیت تأیید
- خودرو و اطلاعات مرتبط
- فهرست و جست‌وجوی رانندگان

### سرویس‌ها
- ایجاد و ویرایش سرویس
- تخصیص راننده
- تخصیص دانش‌آموز
- ظرفیت و وضعیت سرویس
- تغییر راننده
- تاریخچه تغییرات
- بسته‌ها و قراردادهای مرتبط

### کارکرد و حقوق رانندگان
- ثبت کارکرد
- محاسبه مبالغ
- مانده راننده
- گزارش ماهانه
- وضعیت پرداخت

### مالی و حسابداری
- بدهی مدرسه و سرویس
- فاکتور
- پرداخت
- حساب‌ها
- اسناد حسابداری
- کیف پول
- گزارش‌های مالی
- بانک و شعبه

### پیام‌رسانی
- پیام‌های سامانه
- SMS
- اعلان‌های عملیاتی
- ثبت سابقه ارسال و نتیجه

### گزارش‌ها و خروجی
- گزارش‌های عملیاتی
- گزارش رانندگان
- گزارش دانش‌آموزان
- گزارش مالی
- خروجی Excel
- فیلترهای تاریخ جلالی

### نقشه و پایش
- نقشه مدارس
- موقعیت‌های جغرافیایی
- پایش سرویس در صورت فعال شدن این قابلیت
- کنترل سطح دسترسی اطلاعات مکانی

### تنظیمات
- اطلاعات سازمان
- تنظیمات سامانه
- تنظیمات پیامک
- تنظیمات مالی
- تعطیلات و روزهای خاص
- تنظیمات قرارداد و محاسبات

### قابلیت‌های هوشمند
- تاریخچه گفتگو
- پرسش‌های پرتکرار
- دستیار قراردادی
- قابلیت‌های AI فقط با مجوز و داده محدودشده

## 3. معماری پیشنهادی

### Frontend
- SPA
- RTL
- فارسی
- اعداد و تاریخ جلالی
- کامپوننت‌های قابل استفاده مجدد
- لایه API مستقل از UI
- مدیریت خطا و وضعیت بارگذاری
- عدم نگهداری Secret در کد Frontend

### Backend
ساختار لایه‌ای:

HTTP/API
→ Authentication
→ Authorization
→ Validation
→ Service
→ Repository
→ Database

قواعد:
- Controller سبک
- Business Logic در Service
- دسترسی DB در Repository
- DTO برای ورودی/خروجی
- Validation در مرز API
- Transaction برای عملیات چندمرحله‌ای
- Audit برای عملیات حساس

### API
نسخه اولیه با مسیر:

/api/v1/...

گروه‌های اصلی:
- /auth
- /users
- /roles
- /agencies
- /schools
- /students
- /drivers
- /services
- /packs
- /driver-changes
- /contracts
- /driver-work
- /school-charges
- /service-charges
- /invoices
- /payments
- /accounting
- /messages
- /reports
- /monitoring
- /settings

## 4. مدل داده اولیه

موجودیت‌های اصلی:

- users
- roles
- permissions
- user_roles
- role_permissions
- agencies
- agency_users
- schools
- students
- guardians
- drivers
- driver_documents
- vehicles
- services
- service_students
- service_driver_changes
- packs
- contracts
- student_contracts
- driver_contracts
- driver_work
- driver_payments
- school_charges
- service_charges
- invoices
- payments
- accounting_accounts
- accounting_documents
- wallets
- messages
- audit_logs
- files
- settings
- holidays

تمام جداول باید کلید اصلی، زمان ایجاد/ویرایش، وضعیت فعال/حذف منطقی در صورت نیاز و ایندکس‌های متناسب داشته باشند.

## 5. مجوزها

کنترل دسترسی در سه سطح:

1. Authentication
   - کاربر معتبر و نشست معتبر

2. Function Authorization
   - آیا کاربر اجازه انجام عملیات را دارد؟

3. Object Authorization
   - آیا کاربر اجازه دسترسی به همین مدرسه/دانش‌آموز/راننده/سرویس را دارد؟

پارامترهایی مانند agencyId، schoolId یا driverId نباید باعث دور زدن Scope شوند. Backend باید Scope را از نشست/مجوز کاربر تعیین و اعتبارسنجی کند.

## 6. الزامات امنیتی

- HTTPS
- HSTS
- CSP
- X-Content-Type-Options
- X-Frame-Options
- Referrer-Policy
- CORS با Allowlist مشخص
- Rate Limit
- محافظت در برابر Brute Force
- Hash امن رمز عبور
- انقضای نشست و امکان Revocation
- اعتبارسنجی کامل JWT/Session
- جلوگیری از BOLA/IDOR
- جلوگیری از BFLA
- Validation ورودی
- جلوگیری از SQL Injection
- جلوگیری از XSS
- محدودیت و اعتبارسنجی Upload
- محافظت از فایل‌های خصوصی
- عدم نمایش Stack Trace در Production
- عدم قرار دادن Secret در Frontend
- Audit Log برای عملیات حساس
- حداقل‌سازی داده‌های برگشتی API
- محدودسازی اطلاعات هویتی، مالی و مکانی

## 7. بررسی‌های امنیتی در زمان توسعه

برای هر Endpoint این موارد بررسی شود:

- Authentication
- Permission
- Object Scope
- ورودی‌های اجباری
- ورودی‌های نامعتبر
- شناسه متعلق به سازمان دیگر
- شناسه جعلی
- حذف/ویرایش بدون مجوز
- Pagination و محدودیت حجم
- خروجی اطلاعات حساس
- Rate Limit
- Audit Log

برای Socket/Realtime نیز:
- Authentication
- Authorization کانال
- جلوگیری از مشاهده داده سازمان دیگر
- مدیریت قطع و وصل
- Expiration نشست

## 8. مراحل اجرا

### Phase 0 — پایه پروژه
- ساختار Frontend
- ساختار Backend
- تنظیم Environment
- Database connection
- Migration system
- Logging
- Error handling
- Health check
- CI پایه
- README توسعه

**خروجی:** پروژه قابل اجرا و قابل تست.

### Phase 1 — Authentication & Authorization
- Login
- Logout
- Session
- User
- Role
- Permission
- Scope
- Audit امنیتی

**معیار تکمیل:** کاربر بدون مجوز نتواند هیچ Endpoint محافظت‌شده‌ای را اجرا کند.

### Phase 2 — Layout & Dashboard
- Sidebar
- Header
- RTL
- داشبورد
- کارت‌های آماری
- اعلان‌ها

### Phase 3 — Agency & Settings
- اطلاعات سازمان
- کاربران سازمان
- تنظیمات
- تعطیلات
- تنظیمات پیام

### Phase 4 — Schools
- CRUD
- Search
- Filter
- Pagination
- موقعیت
- قرارداد
- گزارش
- Excel

### Phase 5 — Students
- CRUD
- ولی/والدین
- مدرسه
- موقعیت
- تخصیص سرویس
- قرارداد
- حذف منطقی
- Excel

### Phase 6 — Drivers
- پرونده
- مدارک
- امتیاز
- وضعیت
- خودرو
- Search/Filter
- گزارش

### Phase 7 — Services
- CRUD
- تخصیص دانش‌آموز
- تخصیص راننده
- ظرفیت
- وضعیت
- Pack
- قرارداد

### Phase 8 — Driver Change
- درخواست تغییر
- بررسی
- تأیید/رد
- تاریخچه
- ثبت Audit

### Phase 9 — Driver Work & Salary
- کارکرد
- محاسبه
- مبلغ
- مانده
- پرداخت
- گزارش ماهانه

### Phase 10 — Finance & Accounting
- حساب‌ها
- اسناد
- بدهی
- فاکتور
- پرداخت
- کیف پول
- بانک
- گزارش مالی

### Phase 11 — Reports & Excel
- گزارش‌های عملیاتی
- گزارش مالی
- گزارش راننده
- گزارش دانش‌آموز
- خروجی XLSX
- فیلتر جلالی

### Phase 12 — Messaging
- پیام داخلی
- SMS
- اعلان
- تاریخچه ارسال
- وضعیت تحویل

### Phase 13 — Maps
- Map provider abstraction
- مدرسه روی نقشه
- موقعیت‌ها
- سرویس/پایش در صورت نیاز
- کنترل دسترسی داده مکانی

### Phase 14 — AI
- History
- FAQ
- قراردادها
- محدودسازی Scope
- ثبت Audit
- عدم ارسال داده حساس بدون مجوز

### Phase 15 — Monitoring & Audit
- Activity
- Security events
- Login history
- API errors
- عملیات حساس
- گزارش مدیریتی

### Phase 16 — تست جامع
- Unit
- Integration
- API
- Authorization
- Regression
- UI
- Security
- Load محدود
- Migration
- Backup/Restore

### Phase 17 — Production
- Environment production
- HTTPS
- Database backup
- Monitoring
- Log rotation
- Rate limiting
- Health checks
- Deployment
- Rollback plan

## 9. روش اجرای هر Phase

هر مرحله دقیقاً با این چرخه انجام شود:

1. تحلیل نیازمندی
2. بررسی کد و تغییرات قبلی
3. طراحی DB
4. Migration
5. طراحی API
6. Backend
7. Unit/Integration Test
8. Frontend
9. اتصال Frontend و Backend
10. تست UI
11. تست Authorization
12. Regression
13. بازبینی کد
14. ثبت تغییرات
15. Commit

هیچ مرحله‌ای نباید با حذف یا بازنویسی بی‌دلیل قابلیت‌های قبلی انجام شود.

## 10. ترتیب شروع توسعه

ترتیب رسمی:

1. Phase 0
2. Phase 1
3. Phase 2
4. Phase 3
5. Phase 4
6. Phase 5
7. Phase 6
8. Phase 7
9. Phase 8
10. Phase 9
11. Phase 10
12. Phase 11
13. Phase 12
14. Phase 13
15. Phase 14
16. Phase 15
17. Phase 16
18. Phase 17

## 11. معیار تکمیل هر ماژول

یک ماژول فقط زمانی Complete محسوب می‌شود که:

- Database migration داشته باشد.
- API مستند و تست‌شده داشته باشد.
- Authentication فعال باشد.
- Authorization عملیاتی و Object Scope تست شده باشد.
- Validation ورودی انجام شود.
- خطاها استاندارد باشند.
- Audit برای عملیات حساس وجود داشته باشد.
- UI کامل باشد.
- Pagination/Filter در داده‌های حجیم رعایت شود.
- Excel در صورت نیاز وجود داشته باشد.
- تاریخ و اعداد فارسی/جلالی صحیح باشند.
- Regression روی قابلیت‌های قبلی انجام شده باشد.

## 12. قواعد Git

- شاخه اصلی: main
- هر قابلیت بزرگ در Branch مستقل توسعه داده شود.
- Commitها کوچک، قابل توضیح و مرتبط با یک تغییر باشند.
- قبل از Merge تست اجرا شود.
- تغییرات Database همراه Migration باشند.
- فایل‌های Environment و Secretها Commit نشوند.
- تغییرات مخرب یا بازنویسی گسترده بدون ضرورت انجام نشود.
- قبل از هر تغییر بزرگ، وضعیت فعلی Git بررسی شود.

## 13. وضعیت فعلی

- Repository: serviceyar
- Branch هدف: main
- این سند: نقشه رسمی اجرای پروژه
- Phase 0: تکمیل شده
- Phase 1: Authentication & Authorization پیاده‌سازی شده
- Phase 2: Layout & Dashboard پیاده‌سازی شده
- Phase 3: Agency & Settings پیاده‌سازی شده
- Phase 4 تا 15: زیرساخت داده، API، Scope، گزارش، پایش و AI ایجاد شده و توسعه تخصصی هر ماژول در ادامه همین خط انجام می‌شود
- Phase 16: تست جامع و Regression باید پس از تکمیل جزئیات عملیاتی اجرا شود
- Phase 17: آماده‌سازی Production پس از تأیید تست‌های Phase 16

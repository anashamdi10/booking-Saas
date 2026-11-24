# منصة حجوزات المصورين — Photographer Booking SaaS

منصة SaaS لحجوزات التصوير: المصور يبني صفحته ويعرض باقاته ومواعيده، والعميل يحجز الموعد المتاح
أو يتواصل معه مباشرة عبر واتساب.

البنية منفصلة إلى تطبيقين:

| المجلد | التقنية | الدور |
|---|---|---|
| `backend/` | Laravel 12 · PHP 8.2 · Sanctum 4 | REST API + منطق العمل + التخزين |
| `frontend/` | Vue 3.5 · TypeScript · Vite 8 · Tailwind 4 · Pinia | واجهة SPA (RTL عربية) |

---

## 1. التشغيل السريع

### المتطلبات

- PHP **8.2+** مع إضافات `pdo_sqlite` / `pdo_mysql`، `mbstring`, `openssl`, `fileinfo`
- Composer 2، Node.js 20.19+ / 22.12+، npm 10

### الـ Backend

```bash
cd backend
composer install
cp .env.example .env          # راجع section 4 لتضييط الإنتاج
php artisan key:generate
php artisan migrate --seed    # ينشئ الباقات والمستخدمين التجريبيين
php artisan serve --port=8010
```

#### قاعدة البيانات

المشروع يعمل على **SQLite** (افتراضياً للتطوير) و**MySQL 8+** (للإنتاج). للتحويل إلى MySQL
في `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=photographers_saas
DB_USERNAME=root
DB_PASSWORD=
```

ثم `php artisan migrate --seed`.

> **جميع أعمدة التاريخ `DATETIME` وليست `TIMESTAMP`** — لأن `TIMESTAMP` في MySQL تتوقف عند
> 2038-01-19، بينما `DATETIME` تصل إلى 9999. هذا يمنع فشل الإدخال عند تجديد الاشتراكات
> أو انتهاء صلاحية الألبومات بعد 2038. راجع `php tools/inspect-schema.php` للتحقق.

> **المنفذ `8000` مستخدم على هذا الجهاز من مشروع آخر**، لذلك المشروع يعمل على `8010`.
> لتغييره حدّث `frontend/.env` (المتغير `VITE_API_PROXY_TARGET`).

### الـ Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev                   # http://127.0.0.1:5173
```

يعمل خادم Vite كـ **proxy** للـ API على `/api`، فلا حاجة لإعداد CORS أثناء التطوير.

### حساب عميلي جاهز

| الدور | البريد | كلمة المرور |
|---|---|---|
| مدير النظام | `admin@example.com` | `Admin@12345` |
| مصور | `photographer1@example.com` … `photographer6@example.com` | `Photographer@123` |

> غيّر هذه كلمات المرور فوراً في أي بيئة عامة.

---

## 2. ما تم تنفيذه في هذه المرحلة

### الموقع العام
- **الصفحة الرئيسية**: إحصائيات المنصة (عدد المصورين، المدن، الحجوزات، الجلسات المكتملة)،
  شبكة المصورين المميزين، وشرح خطوات الحجز.
- **دليل المصورين**: بحث بالاسم/المدينة/التخصص، ترقيم صفحات،Design cards تعرض الشعار والتقييم والمدينة.
- **صفحة المصور**: تُطبّق **ألوان المصور** على الصفحة عبر CSS variables، وتعرض معرض الأعمال،
  الباقات والأسعار، ساعات العمل، زر «تواصل عبر واتساب» (رابط `wa.me` خارجي)، وزر «احجز موعداً».
- **حساب الزيارات**: كل زيارة عامة تُسجَّل (مُجزّأة ومُخزَّنة بصيغة hash للخصوصية) وتظهر في
  لوحة تحكم المصور ولوحة الإدارة.

### نظام الحجز
- `AvailabilityService` يحسب المواعيد المتاحة من **ساعات العمل** − **فترات الإغلاق** − **الحجوزات القائمة**.
- الحجز يتحقق على الخادم دائماً: لا مواعيد خارج العمل، ولا حجز مزدوج لنفس الفترة
  (قفل الصف داخل `DB::transaction`).
- الباقة المُختارة يجب أن **تعتمد للمصور نفسه وأن تكون نشطة** — إرسال `package_id`
  لباقة موقوفة أو لباقة مصور آخر يُردّ بـ`404` (الواجهة تخفيها، والـAPI يرفضها).
- المصور يحوّل الحالة: `pending → confirmed → completed`، أو `cancelled` / `rejected`.
- **الإشعارات عند إنشاء الحجز**: يُبلَّغ المصور (إشعار مُخزَّن في جرس لوحته + إيميل
  «طلب حجز جديد») ويُبلَّغ العميل بإيميل تأكيد إلى عنوانه — وكلا الإرسالين بعد التزام
  المعاملة، وفشله يُسجَّل فقط ولا يُبطل حجزاً تمّ بالفعل.

### لوحة تحكم المصور
- إحصائيات (حجوزات لكل حالة، زيارات اليوم، إجمالي الزيارات) + رسم بياني لآخر 30 يوماً.
- إدارة الحجوزات: تأكيد / إلغاء / إكمال مع فلاتر للحالة.
- **جرس الإشعارات** في القائمة الجانبية: عدّاد غير المقروءة، قائمة منسدلة تُحدَّث كل
  30 ثانية، نقر الإشعار يعلّمه كمقروء ويقود إلى الحجز المعني.
- تعديل الصفحة الشخصية: الاسم، العنوان المهني، النبذة، المدينة، الجوال، واتساب، الموقع، سنوات الخبرة،
  **ألوان الصفحة** (5 ألوان)، و**مواعيد العمل** (أيام + من/إلى).
- إدارة الباقات والأسعار: إضافة / تعديل / حذف مع السعر والعملة والمدة والصور والمميزات
  و**إيقاف باقة** مؤقتاً — وكل مصور يرى أسعاره هو فقط (فحص العزل في `tools/smoke-test.php`).

### لوحة الإدارة
- إحصائيات عامة للمنصة.
- إدارة المصورين: تفعيل / إيقاف الحساب، وإسناد باقة الاشتراك التي تحدّد مساحة التخزين.
- إدارة **باقات الاشتراك** نفسها (إضافة / تعديل / إيقاف / حذف): الاسم والسعر
  والعملة ومساحة التخزين **بالجيجابايت** وحدود المعارض والعملاء والحجوزات شهرياً
  والمميزات — والباقة المرتبطة باشتراكات تُوقف ولا تُحذف حتى لا يضيع سجل المشتركين،
  فتختفي فوراً من قوائم الإسناد.
- إدارة **باقات التصوير والأسعار** لأي مصور (إضافة / تعديل / حذف) باسمه — وتعمل
  فوراً في صفحته العامة وفي نافذة الحجز، بنفس قواعد التحقق المعزولة للمصور.

### المصادقة والصلاحيات
- تسجيل المصورين → الحساب `pending` حتى توافق الإدارة.
- تسجيل الدخول بـ **Bearer token** (Sanctum) مع صلاحية افتراضية 7 أيام.
- إعادة تعيين **البريد الإلكتروني** و**كلمة المرور** من قسم «الحساب والأمان» في
  الملف الشخصي — كلٌّ منهما مستقل عن الآخر ويتطلب كلمة المرور الحالية،
  بحدّ خمس محاولات في الدقيقة لكل حساب.
- `admin` / `photographer` / `user.active` middleware + **Policies** لكل مورد.
- **لا توجد جلسات (cookies)** في الـ API → لا سطح هجوم CSRF ولا session fixation.

---

## 3. خريطة المشروع

```
backend/
  app/
    Enums/            UserRole, UserStatus, BookingStatus, SubscriptionStatus, ...
    Http/
      Controllers/Api/V1/
        Auth/         AuthController
        Admin/        PhotographerController
        Photographer/ ProfileController, PackageController, DashboardController
        SiteController, PhotographerController, AvailabilityController, BookingController
      Middleware/     EnsureAdmin, EnsurePhotographer, EnsureUserIsActive,
                      ResetAuthenticationState, SecurityHeaders, CachePublicResponse, TrackSiteVisit
      Requests/       FormRequest لكل عملية (تحقق صارم + رسائل عربية)
      Resources/      API Resources (لا تسرّب أي حقل داخلي)
    Jobs/             RecordSiteVisit
    Models/           User, PhotographerProfile, Package, Booking, Gallery, Invoice, ...
    Policies/         BookingPolicy, PackagePolicy, WorkingHourPolicy, PhotographerProfilePolicy
    Services/         AvailabilityService, BookingService, StorageQuotaService,
                      StatsService, WhatsAppService, SlugService
    Support/          MediaUrl
  database/           migrations (21 جدول) + factories + seeders
  routes/api.php
  tools/
    smoke-test.php        اختبار شامل للـ API (83 فحصاً)
    verify-namespaces.php فحص تطابق الـ namespace مع مسار الملف
    inspect-schema.php    عرض أنواع أعمدة قاعدة البيانات (تحقق من عدم وجود TIMESTAMP)

frontend/
  src/
    api/               client.ts (axios + interceptors), types.ts
    components/        layout/, ui/, photographer/
    composables/       useFormat.ts (تنسيق عربي),
                       useBrowserNotification.ts (إشعارات متصفح الـ Web Notifications)
    router/            index.ts (حماية المسارات حسب الدور)
    stores/            auth.ts (Pinia)
    views/             HomeView, PhotographersView, PhotographerProfileView, ...
```

---

## 4. قاعدة البيانات

21 جدولاً جاهزة对本موذج الكامل — منها هذه المرحلة تستخدم:

`users` · `plans` · `subscriptions` · `photographer_profiles` · `portfolio_images` ·
`working_hours` · `availability_blocks` · `packages` · `bookings` · `site_visits` · `daily_stats`

وجداول **مهيّأة مسبقاً للمراحل القادمة** (لا تحتاج إعادة تصميم): `clients` · `galleries` ·
`gallery_files` · `invoices` · `invoice_items` · `reviews` · `transactions` · `settings` · `audit_logs`

---

## 5. التخزين (Cloudflare R2 / AWS S3)

```env
MEDIA_PUBLIC_DISK=r2          # شعارات المصورين وصور المعرض (عامة)
MEDIA_PRIVATE_DISK=media      # ألبومات العملاء (روابط موقّتة فقط)

R2_ENDPOINT=https://<account>.r2.cloudflarestorage.com
R2_BUCKET=your-bucket
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_URL=https://cdn.example.com
```

صور العملاء يجب أن تُقدَّم عبر **روابط موقّتة** (signed URLs) لا عبر رابط عام.

---

## 6. الأمان

التفاصيل الكاملة في [`docs/SECURITY.md`](docs/SECURITY.md). أهم النقاط:

- **Rate limiting** على كل المسارات الحساسة (دخول 10/دقيقة، حجز 60/ساعة لكل IP و5/ساعة لكل بريد).
- **تقييد نوع الاستجابة**: `Cache-Control: no-store, private` لكل ردّ يعتمد على المستخدم —
  منع تسريب بيانات عميل إلى مشترك أو إلى متصفح آخر. الردود العامة فقط قابلة للتخزين المؤقت.
- **تشفير كلمات المرور** بـ bcrypt (12 جولة) + `Password::uncompromised()`.
- **تنقية المدخلات**: كل الحقول مقيّدة بـ FormRequest، لا استعلامات SQL خام، كل المخرجات عبر API Resources.
- **رؤوس أمان** على كل ردّ: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, …
- **عزل حالة المصادقة** بين الطلبات (`ResetAuthenticationState`) حتى لا يتسرب مستخدم إلى آخر
  في بيئات التشغيل طويلة العمر (Octane).
- **CORS** بقائمة نطاقات محدّدة من `.env` (لا `*` في الإنتاج).

---

## 7. الاختبارات

```bash
cd backend
php tools/smoke-test.php       # 83 فحصاً على الـ API كاملاً
php tools/verify-namespaces.php
php tools/inspect-schema.php   # أنواع الأعمدة في قاعدة البيانات
vendor/bin/pint --test
```

الاختبار الشامل يغطي: الموقع العام، تدفق الحجز (مع منع الحجز المزدوج)، التسجيل والدخول،
لوحات التحكم، حدود الصلاحيات (مستخدم مصور ↔ مسارات الإدارة، زائر ↔ المناطق المحمية).

```bash
cd frontend
npm run build    # vue-tsc (تحقق أنواع صارم) + build إنتاج
```

---

## 8. مسارات الـ API (نسخة مختصرة)

| الطريقة | المسار | الوصف |
|---|---|---|
| GET | `/api/v1/site/stats` | إحصائيات المنصة |
| GET | `/api/v1/photographers` | دليل المصورين (بحث + ترقيم) |
| GET | `/api/v1/photographers/{slug}` | صفحة المصور العامة |
| GET | `/api/v1/photographers/{slug}/availability` | المواعيد المتاحة |
| POST | `/api/v1/bookings` | إنشاء طلب حجز |
| POST | `/api/v1/auth/register` | تسجيل مصور (يبقى قيد المراجعة) |
| POST | `/api/v1/auth/login` | تسجيل الدخول |
| POST | `/api/v1/auth/logout` | خروج |
| GET | `/api/v1/auth/me` | المستخدم الحالي |
| GET | `/api/v1/photographer/dashboard` | إحصائيات المصور |
| GET/PUT | `/api/v1/photographer/profile` | الصفحة الشخصية |
| PUT | `/api/v1/photographer/profile/working-hours` | مواعيد العمل |
| CRUD | `/api/v1/photographer/packages` | الباقات |
| GET | `/api/v1/photographer/bookings` | الحجوزات |
| PATCH | `/api/v1/photographer/bookings/{id}` | تغيير حالة الحجز |
| GET | `/api/v1/admin/photographers` | كل المصورين |
| PATCH | `/api/v1/admin/photographers/{id}` | التفعيل / إسناد الباقة |
| GET | `/api/v1/admin/plans` | باقات الاشتراك |

التفاصيل الكاملة: [`docs/API.md`](docs/API.md).

---

## 9. النشر في الإنتاج

[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — nginx، supervisor، cron، checklist قبل الإطلاق.

**تطبيق واحد (موصى به):** `cd frontend && npm run build:spa` ينسخ الواجهة إلى
`backend/public` فيخدمها Laravel نفسه — نطاق واحد بلا CORS.

**Wasmer Edge:** [`docs/DEPLOY_WASMER.md`](docs/DEPLOY_WASMER.md) — تحزيم
(`backend/deploy/package.ps1`)، أسرار، TiDB، قرص دائم، وترحيل.

---

## 10. خارطة الطريق (المراحل القادمة)

البنية جاهزة لها؛ ما ينقص هو الواجهات فقط:

1. **رفع الصور والألبومات** — `galleries` + `gallery_files` جاهزان، مع فرض حدّ المساحة عبر
   `StorageQuotaService`، وروابط موقّتة للألبومات الخاصة.
2. **العملاء والفواتير** — `clients` + `invoices` + `invoice_items` جاهزة.
3. **لوحة تحكم المصور الكاملة** — عرض الألبومات، إدارة العملاء، إدارة الفواتير.
4. **الإدارة** — إدارة الباقات، عرض الإيرادات، تقارير المصورين، سجل التدقيق (`audit_logs`).
5. **بوابة دفع** — خلف واجهة `BillingGateway` لربط Stripe/Moyasar لاحقاً بدون تعديل المنطق.

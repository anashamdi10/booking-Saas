# النشر على Wasmer Edge — تطبيق واحد

الواجهة (Vue) والـ API (Laravel) يُنشران **كتطبيق واحد** على نطاق واحد:

```
https://your-app.wasmer.app/            ← واجهة Vue (SPA)
https://your-app.wasmer.app/api/v1/...  ← الـ API
```

المزايا: لا CORS، نطاق واحد وشهادة واحدة، نشر واحد، وتطبيق واحد على Wasmer
(الخطة المجانية تسمح بتطبيق واحد فقط).

---

## كيف يعمل الدمج

| الملف | الدور |
|---|---|
| `frontend/scripts/build-spa.mjs` | يبني Vue ثم ينسخ `dist/` إلى `backend/public/` |
| `backend/routes/web.php` | مسار شامل يعيد `index.html` لأي رابط غير `/api/*` و`/up` و`/storage/*` |
| `frontend/.env` | `VITE_API_BASE_URL=/api/v1` — **نسبي**، فيعمل على أي نطاق |
| `backend/wasmer.toml` | حزمة Wasmer: PHP 8.3.4 + خادم التطوير + `php.ini` مخصّص |
| `backend/deploy/package.ps1` | يجهّز حزمة نظيفة بلا `.env` ولا SQLite ولا سجلات |

> **لا تضبط `VITE_API_BASE_URL` على عنوان مطلق** عند الدمج، وإلا صار الـ API
> على أصل مختلف وعدنا إلى مشكلة CORS بلا داعٍ.

---

## 0. القيود التي يجب أن تعرفها

1. **PHP يعمل بخادم مدمج** (`php -S`) داخل WASM — مناسب للعرض والتجربة، لا لضغط
   إنتاجي عالٍ.
2. **لا MySQL من Wasmer** — استخدم [TiDB Cloud](https://tidbcloud.com)
   (متوافقة مع MySQL). ملاحظة: TiDB **لا تفرض المفاتيح الأجنبية**، لكن حمايتنا
   من حذف باقة مستخدمة مطبّقة في الكود أيضاً (`PlanController`).
3. **نظام الملفات غير دائم** — ما يُكتب خارج Volumes يضيع مع كل إعادة نشر.
4. **لا queue worker دائم** — استخدم `QUEUE_CONNECTION=sync` (الإشعارات في
   المشروع تُرسل متزامنة أصلاً).
5. **البريد** — `MAIL_MAILER=log` بلا معنى هنا (السجلات مؤقتة) ⇒ SMTP حقيقي.
6. **الصور** — القرص المحلي غير مناسب ⇒ Cloudflare R2 (مدعوم في المشروع).

---

## 1. تثبيت الأدوات

```powershell
# Windows (PowerShell)
iwr https://win.wasmer.io -useb | iex
```

```bash
# macOS / Linux
curl https://get.wasmer.io -sSfL | sh
```

أنشئ حساباً على <https://wasmer.io/signup> ثم:

```bash
wasmer login
```

---

## 2. ابنِ الواجهة ونسخها إلى الـ API

```bash
cd frontend
npm ci
npm run build:spa
```

ينتهي بطباعة:

```
SPA copied to ...\backend\public
```

**تحقّق محلياً قبل أي نشر:**

```bash
cd ../backend
php artisan serve --port=8010
```

- <http://127.0.0.1:8010> → الواجهة
- <http://127.0.0.1:8010/admin/plans> → نفس الواجهة (رابط عميق)
- <http://127.0.0.1:8010/api/v1/site/stats> → JSON

إن ظهر 404 في الواجهة فمعناه أن `public/index.html` غير موجود — أعد
`npm run build:spa`.

---

## 3. قاعدة البيانات: TiDB Cloud

1. أنشئ Cluster من نوع **Serverless** في <https://tidbcloud.com>.
2. من صفحة الاتصال خُذ: `Host`، `Port` (عادةً `4000`)، `User`، `Database`.
3. نزّل شهادة **CA** باسم `tidb-ca.pem` وضعها في `backend/certs/tidb-ca.pem`.
   ستُحزم مع التطبيق، ويقرأ مشروعك مسارها من `MYSQL_ATTR_SSL_CA`
   (مضبوط في `config/database.php`).
4. أنشئ مستخدماً مخصّصاً للتطبيق، ولا تستخدم بيانات المدير.

---

## 4. الأسرار (بدل `.env`)

الأسرار تُضبط في Wasmer، وتتفوّق على أي `.env` موجود (Dotenv لا يستبدل
متغيّرات البيئة الموجودة).

```bash
wasmer app secrets create APP_ENV production
wasmer app secrets create APP_DEBUG false
wasmer app secrets create APP_KEY "base64:..."          # php artisan key:generate --show
wasmer app secrets create APP_URL "https://<app>.wasmer.app"
wasmer app secrets create FRONTEND_URL "https://<app>.wasmer.app"
wasmer app secrets create APP_LOCALE ar

wasmer app secrets create DB_CONNECTION mysql
wasmer app secrets create DB_HOST "<tidb-host>"
wasmer app secrets create DB_PORT 4000
wasmer app secrets create DB_DATABASE photographers
wasmer app secrets create DB_USERNAME "<user>"
wasmer app secrets create DB_PASSWORD "<password>"
wasmer app secrets create MYSQL_ATTR_SSL_CA "/app/certs/tidb-ca.pem"

wasmer app secrets create QUEUE_CONNECTION sync
wasmer app secrets create MAIL_MAILER smtp
wasmer app secrets create MAIL_HOST "<smtp-host>"
wasmer app secrets create MAIL_PORT 587
wasmer app secrets create MAIL_USERNAME "<smtp-user>"
wasmer app secrets create MAIL_PASSWORD "<smtp-password>"
wasmer app secrets create MAIL_FROM_ADDRESS "<no-reply@your-domain>"
wasmer app secrets create MAIL_FROM_NAME "منصة حجوزات المصورين"

# الصور والملفات: لا تعتمد على القرص المحلي
wasmer app secrets create MEDIA_PUBLIC_DISK r2
wasmer app secrets create MEDIA_PRIVATE_DISK media
wasmer app secrets create R2_ENDPOINT "https://<account>.r2.cloudflarestorage.com"
wasmer app secrets create R2_BUCKET photographer-media
wasmer app secrets create R2_ACCESS_KEY_ID "<key>"
wasmer app secrets create R2_SECRET_ACCESS_KEY "<secret>"
wasmer app secrets create R2_URL "https://media.your-domain.com"
```

> `CORS_ALLOWED_ORIGINS` غير لازم في هذه الطريقة: الواجهة والـ API على نفس
> الأصل. (اتركه فارغاً؛ الافتراضي يسمح للتطوير المحلي فقط.)

**لا تُعِد توليد `APP_KEY`** بعد أول تشغيل — البيانات المشفّرة في القاعدة ستصبح
غير قابلة للقراءة.

---

## 5. القرص الدائم والترحيل

### 5.1 Volume

`wasmer deploy` (أول مرة) يولّد `app.yaml`. أضف إليه:

```yaml
volumes:
  - name: storage
    mount: /app/storage
```

> الصور تُحفظ في R2، فالـ Volume هنا للسجلات والكاش وملفات Blade المترجمة فقط.

### 5.2 الترحيل

```bash
wasmer app ssh
```

داخل الجلسة:

```bash
cd /app
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public bootstrap/cache
php artisan migrate --force
```

**لا تشغّل `DemoDataSeeder`** في الإنتاج. أنشئ حساب الأدمن يدوياً:

```bash
php artisan tinker --execute="App\Models\User::create(['name'=>'المدير','email'=>'admin@example.com','password'=>bcrypt('<كلمة-قوية>'),'role'=>'admin','status'=>'active']);"
```

---

## 6. التحزيم والنشر

```powershell
cd backend
powershell -ExecutionPolicy Bypass -File deploy\package.ps1
```

يطبع مسار حزمة نظيفة في `%TEMP%\photographer-api-package` — **بلا `.env`**
(وفيه `APP_KEY` ومفتاح القاعدة) وبلا SQLite وبلا سجلات، مع `vendor/` ومخرجات
الواجهة وملف `wasmer.toml`.

```powershell
cd "$env:TEMP\photographer-api-package"
wasmer deploy
```

سيطبع عنوان التطبيق. ثم:

1. اضبط `APP_URL` و`FRONTEND_URL` على العنوان النهائي.
2. أضف الـ Volume (بند 5.1) ونفّذ الترحيل (بند 5.2).

### كل تحديث لاحق

```powershell
cd frontend; npm run build:spa
cd ..\backend; powershell -ExecutionPolicy Bypass -File deploy\package.ps1
cd "$env:TEMP\photographer-api-package"; wasmer deploy
```

---

## 7. قائمة تحقّق بعد النشر

- [ ] `/` يفتح الواجهة، و`/admin/plans` مباشرةً يفتح نفس الواجهة (لا 404)
- [ ] `/api/v1/site/stats` يعيد JSON
- [ ] تسجيل الدخول يعمل (admin / مصوّر)
- [ ] `APP_DEBUG=false` و`APP_ENV=production`
- [ ] الترحيل نُفّذ ولم تُزرع بيانات تجريبية
- [ ] إشعار حجز تجريبي يصل بالبريد (SMTP)
- [ ] رفع صورة يحفظها في R2 ويعيد رابطاً يعمل
- [ ] إعادة النشر تحافظ على البيانات (Volume + TiDB)

---

## 8. متى تتجنّب Wasmer؟

| الحالة | السبب |
|---|---|
| حركة مرور عالية | خادم PHP المدمج أحادي العملية |
| عمّال طابور دائمون | لا عمليات خلفية طويلة |
| قاعدة بيانات محلية | لا MySQL مستضاف (TiDB فقط) |
| ملفات كثيرة على القرص | التخزين المؤقت غير دائم |

في هذه الحالات خادم VPS واحد يكفي — الخطوات كاملة في
[`docs/DEPLOYMENT.md`](./DEPLOYMENT.md).

## 9. البديل: تطبيقان منفصلان

لو أردت لاحقاً فصل الواجهة (CDN) عن الـ API:

- الواجهة: قالب `static-website` + `root: dist` + `page-fallback = "/index.html"`
  في `settings/config.toml` (بدونه كل رابط عميق يعطي 404).
- الـ API: نفس `wasmer.toml` الحالي.
- عندها يلزم: `VITE_API_BASE_URL` مطلق + `CORS_ALLOWED_ORIGINS` =
  نطاق الواجهة، **وإعادة بناء الواجهة** عند تغيّر عنوان الـ API، وخطة Wasmer
  تسمح بتطبيقين.

# تشغيل المشروع من VS Code

المشروع جاهز للتشغيل بضغطة واحدة. كل الملفات موجودة في `.vscode/`.

---

## 1. مرة واحدة فقط

### أ) افتح المجلد الصحيح

```bash
cd "C:\Users\Anas\Documents\المشروع الافتراضي"
code .
```

> افتح **الجذر** (المجلد الذي يحوي `backend/` و `frontend/`)، لا مجلد `backend` منفرداً —
> وإلا لن تعمل مسارات الـ Tasks.

### ب) ثبّت الإضافات

اضغط `Ctrl+Shift+X` → اكتب `@recommended` → **Install All**.

| الإضافة | لماذا |
|---|---|
| **PHP Intelephense** | إكمال تلقائي +Go-to-definition + تحليل الأخطاء |
| **Vue - Official (Volar)** | أنواع + قالب داخل ملفات `.vue` |
| **EditorConfig** | تنسيق موحّد (4 مسافات لـ PHP، 2 للـ Vue/TS) |
| **ESLint** | فحص الجودة (اختياري) |

> إضافة **Laravel Pint** للواجهة داخل VS Code اختيارية؛ Pint يعمل من الطرفية:
> `vendor\bin\pint` (داخل `backend`).

### ج) تهيئة الاعتماديات

اضغط `Ctrl+Shift+P` → **Tasks: Run Task** → اختر:

1. `تهيئة المشروع (مرة واحدة)` — يشغّل `composer install` + `migrate --seed`
2. `تهيئة الواجهة (مرة واحدة)` — يشغّل `npm install`

> الملف `frontend/.env` موجود جاهز. إن حذفته: `cp .env.example .env`.

---

## 2. التشغيل اليومي

### الطريقة الموصى بها: Task واحد

`Ctrl+Shift+P` → **Tasks: Run Task** → `▶ تشغيل المشروع كاملاً`

أو بالاختصار: `Ctrl+Shift+B` (Build Task) — لأن هذه الـ Task هي الافتراضية (`isDefault`).

سيُفتح **3 terminals** جنباً إلى جنب:

| الطرفية | الأمر | المنفذ |
|---|---|---|
| API | `php artisan serve --host=127.0.0.1 --port=8010` | http://127.0.0.1:8010 |
| الطابور | `php artisan queue:work --tries=3` | — |
| الواجهة | `npm run dev` | http://127.0.0.1:5173 |

ثم افتح **http://127.0.0.1:5173**

> **لماذا المنفذ 8010؟** المنفذ `8000` مشغول على جهازك بمشروع آخر
> (`C:\xampp\htdocs\photography-booking-saas`). الـ proxy في `frontend/.env` يشير إلى 8010،
> فإن غيّرت المنفذ غيّره في الملفين: `frontend/.env` و `tasks.json`.

### إيقاف كل شيء

زر **■ (Terminate Task)** في شريط المهام، أو أغلق الـ terminals.
`Ctrl+C` في كل طرفية يعمل أيضاً.

### إعادة تشغيل الـ API فقط بعد تعديل `.env`

```
Ctrl+Shift+P → Tasks: Run Task → "الخادم: إعادة تحميل التغييرات"
```

ثم أعد تشغيل task الـ API (لأن `config:cache` يحتاج إعادة تحميل).

---

## 3. التصحيح (Debugging)

### تفعيل Xdebug — تمّ

Xdebug 3.5.3 **مثبَّت بالفعل** في:

```
C:\xampp\php\ext\php_xdebug.dll
```

**مهم:** لم يُعدَّل `php.ini` العام في XAMPP إطلاقاً — لذا مشاريعك الأخرى و Apache لا تتأثر.
يُفعَّل Xdebug فقط أثناء جلسات التصحيح عبر `PHP_INI_SCAN_DIR` المحدد في `.vscode/launch.json`:

```jsonc
"env": {
  "PHP_INI_SCAN_DIR": "${workspaceFolder}/backend/.vscode/php",
  "XDEBUG_MODE": "debug",
  "XDEBUG_CONFIG": "client_host=127.0.0.1 client_port=9003 idekey=vscode"
}
```

الملف `backend/.vscode/php/php-xdebug.ini` يحمل إعدادات Xdebug.

> للتحقق: `php -m | findstr xdebug` → يجب ألا يظهر شيء (لأن التفعيل مشروع فقط).
> للتحقق من التفعيل: `set PHP_INI_SCAN_DIR=...\backend\.vscode\php && php -m | findstr xdebug` → يظهر.

### إعدادات التصحيح

اضغط `F5` → اختر من القائمة:

| الإعداد | الوظيفة |
|---|---|
| 🔍 تصحيح الـ API (PHP) | يشغّل `artisan serve` متصلاً بالمصحح على المنفذ 9003 |
| 🧪 تشغيل اختبار الـ API | يشغّل `tools/smoke-test.php` تحت المصحح |
| 🌐 تصحيح الواجهة (Chrome) | يفتح المتصفح على 5173 مع breakpoints على الـ Vue |
| ⚙️ تصحيح عملية الخلفية (Queue) | يشغّل `queue:work` تحت المصحح |
| ▶ المتصفح + تصحيح الـ API | الاثنان معاً (compound) |

### وضع breakpoint

في `backend/app/Services/BookingService.php` ضع نقطة توقف على السطر:

```php
if (! $this->availability->isWithinWorkingHours($photographer->id, $start, $end)) {
```

ثم شغّل `F5` (🔍 تصحيح الـ API) وافتح `http://127.0.0.1:5173` واحجز موعداً →
ستتوقف الجلسة عند النقطة، وتقدر تتفرّج على `$start`, `$end`, `$package`.

> **مهم:** شغّل الـ API عبر `F5` (وليس عبر الـ Task) إن أردت الـ breakpoints — الـ Task
> يشغّل `artisan serve` في عملية منفصلة لا يعرفها المصحح.

---

## 4. أوامر مفيدة

| الأمر | المكان |
|---|---|
| `php artisan test` / `php tools/smoke-test.php` | `backend` |
| `vendor\bin\pint` | `backend` — تنسيق الكود |
| `vendor\bin\pint --test` | `backend` — فحص التنسيق فقط |
| `npm run dev` / `npm run build` | `frontend` |
| `npm run preview` | `frontend` — معاينة نسخة الإنتاج محلياً |
| `php artisan migrate:fresh --seed` | `backend` — إعادة تهيئة البيانات التجريبية |
| `php artisan queue:work` | `backend` — معالجة الطابور |

> `php artisan migrate:fresh` **يمسح** كل البيانات. لا تستخدمه على الإنتاج.

---

## 5. حل المشاكل

### `php` غير معروف في الطرفية
استخدم المسار الكامل: `C:\xampp\php\php.exe artisan serve`.
أو أضف `C:\xampp\php` إلى `PATH` من إعدادات Windows.

### `npm.ps1 cannot be loaded` (policy يمنع السكربتات)
الـ Tasks تستخدم `npm.cmd` أصلاً لتفادي هذه المشكلة. إن ظهرت لك في الطرفية:

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
```

### المنفذ 8010 مشغول
```powershell
Get-NetTCPConnection -LocalPort 8010 -State Listen | Select-Object OwningProcess
Stop-Process -Id <PID> -Force
```

### الواجهة لا تحمّل البيانات (404 على /api)
تأكد أن:
1. الـ API يعمل على 8010: `curl http://127.0.0.1:8010/api/v1/site/stats`
2. `frontend/.env` فيه `VITE_API_PROXY_TARGET=http://127.0.0.1:8010`
3. أعد تشغيل `npm run dev` (المتغيرات تُقرأ عند الإقلاع)

### الأرقام تظهر كـ `????`
ترميز الطرفية. في PowerShell:
```powershell
chcp 65001
```
أو استخدم Windows Terminal.

### Xdebug: "Time-out connecting to debugging client"
طبيعي عند تشغيل PHP خارج جلسة تصحيح — لا تعني خطأ.

### `Failed loading Zend extension 'php_xdebug.dll'`
إصدار PHP لديك **TS** (Thread-Safe) وهذا هو ملف Xdebug الصحيح. تأكد أن الملف في
`C:\xampp\php\ext\php_xdebug.dll` وأن إصدارات PHP و Xdebug متطابقة
(`php -i | findstr "Thread Safety"`).

---

## 6. حساب الدخول

| الدور | البريد | كلمة المرور |
|---|---|---|
| مدير | `admin@example.com` | `Admin@12345` |
| مصور | `photographer1@example.com` | `Photographer@123` |

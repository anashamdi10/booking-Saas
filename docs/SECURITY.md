# ملاحظات الأمان

هذه الوثيقة تشرح الإجراءات المطبّقة فعلياً في الكود، ولماذا كل واحدة منها موجودة.

---

## 1. المصادقة

### توكن Bearer فقط — بدون كوكيز

```env
# config/sanctum.php
'guard' => [],
```

الـ API لا يستخدم `web` guard ولا الجلسات إطلاقاً. النتيجة:

- لا يوجد سطح هجوم **CSRF** (لا كوكي `XSRF-TOKEN` ولا تحقق `SameSite`).
- لا يوجد **session fixation** لأن الجلسة لا تُستخدم.
- لا يمكن لمتصفح موقع آخر إرسال طلب مُصادَق تلقائياً.

كل ردّ من الـ API يحمل `Cache-Control: no-store, private` ما لم يكن رداً عاماً
يُعلَّم بـ `cache.public` — فبيانات العميل لا تُخزَّن في المتصفح ولا في وسيط مشترك.

### عمر التوكن

`SANCTUM_EXPIRATION=10080` (7 أيام). لإبطال التوكنات فوراً:
حذف التوكن من `personal_access_tokens`، أو إيقاف الحساب — عندها `EnsureUserIsActive`
يحذف توكن المستخدم الحالي تلقائياً ويردّ بـ 403.

> **انتبه للوحدات:** `sanctum.expiration` بالـ **دقائق** (نفس دلالة Sanctum عند التحقق).
> `AuthController::tokenExpiry()` يضيف الدقائق ويحدّ أقصى العمر بسنة واحدة، فلا تتجاوز
> التوكنات حدّ `DATETIME` في MySQL. يوجد فحص ضد هذا الخطأ في `tools/smoke-test.php`.

### حدود قاعدة البيانات

كل أعمدة التاريخ من نوع `DATETIME` (حتى 9999) وليست `TIMESTAMP` (حتى 2038 فقط)، لأن
`TIMESTAMP` في MySQL يرفض أي قيمة بعد 2038-01-19 — وهو ما سبّب فشل تسجيل الدخول عند
حساب تاريخ انتهاء الاشتراك. للتأكد:

```bash
php tools/inspect-schema.php   # يجب أن يعرض 0 أعمدة TIMESTAMP
```

### حماية من تسرّب الحالة بين الطلبات

`Illuminate\Auth\SessionGuard` و Sanctum يحتفظان بالمستخدم في الذاكرة. تحت `php-fpm`
هذا غير مؤثّر (طلب جديد = عملية جديدة)، لكنه خطر في بيئات التشغيل الطويلة (Octane / RoadRunner):
قد يخدم العامل طلبَ مستخدم بمستخدم آخر previously authenticated — أي تسريب بيانات بين العملاء.

الحل: `App\Http\Middleware\ResetAuthenticationState` يستدعي `auth()->forgetGuards()` قبل
معالجة كل طلب، فتُبنى الحرّاس من جديد كل مرة. النتيجة: سلوك متطابق على كل بيئات التشغيل.

```php
$middleware->api(prepend: [ResetAuthenticationState::class]);
```

---

## 2. كلمات المرور

- bcrypt بـ 12 جولة (`config/hashing.php` → `BCRYPT_ROUNDS=12`).
- `Password::uncompromised()` تتحقق من وجود كلمة المرور في قوائم كلمات المرور المسربة
  (HIBP) — ترفض كلمات مثل `password123`.
- التحقق من القوّة: 10 أحرف على الأقل + أحرف كبيرة وصغيرة + أرقام + رموز.
- `password` و`password_confirmation` غير قابلين للقراءة عبر الـ API Resources.

---

## 3. تحديد الطلبات (Rate Limiting)

| الحدّ | التفصيل |
|---|---|
| `auth` | 10 محاولات / دقيقة لكل (IP + البريد) |
| `booking` | 60 / ساعة لكل IP |
| `booking-per-email` | 5 / ساعة لكل (البريد + IP) — يمنع إغراق مصور واحد |
| `public-read` | 60 / دقيقة لكل IP |
| `api` | 120 / دقيقة لكل مستخدم أو IP |

تسجيل الدخول الفاشل يُحتسب في `auth`، و `RateLimiter::hit` يخزّن المحاولات الفاشلة.

---

## 4. التحقق من المدخلات

- كل نقطة نهاية كتابة تستخدم **FormRequest** بقواعد صريحة (`max`, `regex`, `enum`, `exists`).
- رسائل الخطأ عربية وتُعاد في `errors` لكل حقل.
- **Honeypot**: حقل `website` في التسجيل والحجز — إن أُرسل nonempty يُرفض الطلب.
- `trim` + `lowercase` تلقائي على البريد الإلكتروني قبل التحقق.
- لا استعلامات SQL خام — كل الوصول عبر Eloquent (parameterized queries).
- `declare(strict_types=1)` في كل ملفات PHP.

---

## 5. الصلاحيات

- **Policies** لكل مورد: `BookingPolicy`, `PackagePolicy`, `WorkingHourPolicy`, `PhotographerProfilePolicy`.
  القاعدة العامة: المورد ملك للمصور ⇒ يملكه فقط (أو الأدمن).
- Middleware للأدوار: `admin`, `photographer`, `user.active`.
- فحص الملكية عند **تعديل** وليس عند القراءة فقط — لا يمكن تعديل حجز مصور آخر.
- `admin` لا يستطيع تعديل `package` إلا إذا كان مالكه (سياسة موحّدة).

---

## 6. منع Mass Assignment

- كل نموذج يحدد `$fillable` صراحةً.
- `User::$hidden` يخفي `password` و `remember_token`.
- `Gallery::$hidden` يخفي `access_password` و `share_token`.
- الحقول المحسوبة (مثل `total` في الفاتورة) لا تُقبل من العميل.

---

## 7. ترويسات الأمان

`SecurityHeaders` يضيف لكل ردّ:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Cross-Origin-Resource-Policy: same-site
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

## 8. CORS

```env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://www.example.com
```

قائمة بيضاء صريحة — لا `*`.-methods و headers مسموحة لكن الـ origins محددة.

---

## 9. تخزين الملفات

- صور العملاء في قرص **خاص** تُقدَّم عبر **روابط موقّتة** (signed URLs) لا روابط عامة.
- التحقق من نوع الملف وحجمه قبل الرفع (قائمة `allowed_mimes` في `config/media.php`).
- `StorageQuotaService` يمنع تجاوز حدّ المساحة المتفق عليه.
- أسماء الملفات المولّدة UUID — لا يُستخدم اسم المستخدم الأصلي في المسار.

---

## 10. الخصوصية في التحليلات

`RecordSiteVisit` لا يخزّن IP أو User-Agent كما هما:

```php
$visitorHash = hash('sha256', $salt.'|'.$ip.'|'.$userAgent);
```

where `$salt` هو `APP_KEY`. البيانات الخام غير قابلة للاسترجاع، والعدّادات تستخدم الهاش.

---

## 11. سجل التدقيق

جدول `audit_logs` جاهز: `user_id`, `action`, `subject_type`, `subject_id`, `ip_address`,
`user_agent`, `payload`, `created_at`. يُملأ عند العمليات الحساسة (تعديل حالة المصور،
إسناد باقة، تغيير كلمة المرور) — غير مفعّل بعد في هذه المرحلة.

---

## 12. قبل الإطلاق — Checklist

- [ ] `APP_DEBUG=false` و `APP_ENV=production`
- [ ] `APP_KEY` جديد (وليس الذي في المستودع)
- [ ] تغيير كلمات مرور الحسابات التجريبية
- [ ] `CORS_ALLOWED_ORIGINS` بالنطاقات الفعلية فقط
- [ ] `FILESYSTEM_DISK` / `MEDIA_*` يشير إلى R2/S3 وليس `local`
- [ ] HTTPS على النطاقين (API والواجهة)
- [ ] `config:cache` + `route:cache` + `view:cache`
- [ ] `supervisor` لـ `queue:work` و `schedule:run` (cron كل دقيقة)
- [ ] نسخ احتياطي يومي لقاعدة البيانات + تذكير بـ R2 (قاعدة البيانات هي الأهم)
- [ ] تحويل حساب المدير التجريبي إلى حساب حقيقي

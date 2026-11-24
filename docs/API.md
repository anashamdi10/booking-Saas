# مرجع الـ API

جميع المسارات تبدأ بـ `/api/v1`. ترميز UTF-8، والمحتوى `application/json`.

## المصادقة

```http
Authorization: Bearer <token>
```

التوكن يُصدر عند `POST /auth/login` أو `/auth/register`، وصلاحيته الافتراضية 7 أيام
(`SANCTUM_EXPIRATION` بال دقائق). لا تُستخدم كوكيز في الـ API إطلاقاً.

### استجابات الأخطاء

كل خطأ يرجع بالشكل التالي:

```json
{
  "message": "البريد الإلكتروني غير صالح.",
  "errors": { "customer_email": ["البريد الإلكتروني غير صالح."] }
}
```

| الكود | المعنى |
|---|---|
| 401 | غير مصادَق عليه (توكن منتهي/مفقود) |
| 403 | لا يملك الصلاحية |
| 404 | العنصر غير موجود |
| 422 | فشل التحقق — `errors` تحتوي الأخطاء لكل حقل |
| 429 | تجاوز حدّ الطلبات |
| 500 | خطأ غير متوقع |

---

## 1. الموقع العام

### `GET /site/stats`

```json
{
  "data": {
    "photographers_count": 6,
    "cities_count": 6,
    "bookings_count": 29,
    "completed_bookings_count": 6,
    "visits_total": 118,
    "visits_today": 12,
    "invoices_paid_total": 0
  }
}
```

> قابل للتخزين المؤقت (`public, max-age=60`).

### `GET /photographers`

معاملات الاستعلام:

| المعامل | النوع | الوصف |
|---|---|---|
| `q` | string | بحث في الاسم/العنوان/النبذة/المدينة |
| `city` | string | تصفية بالمدينة |
| `featured` | boolean | المصورون المميزون فقط |
| `min_price` / `max_price` | number | نطاق سعر الباقات |
| `sort` | string | `newest` (افتراضي) · `rating` · `price_low` · `price_high` |
| `per_page` | int | 1..60 (افتراضي 12) |
| `page` | int | رقم الصفحة |

```json
{
  "data": [ { "id": 2, "slug": "sar-alhrby", "name": "سارة الحربي", "headline": "...",
              "city": "الرياض", "logo_url": null, "cover_url": null,
              "theme": { "primary": "#1f2937", "accent": "#f97316", "...": "..." },
              "rating_avg": 4.6, "rating_count": 105, "packages_count": 3 } ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 12, "total": 6 }
}
```

### `GET /photographers/{slug}`

```json
{
  "data": {
    "profile":   { "...": "نفس حقول عنصر الدليل" },
    "packages":  [ { "id": 16, "name": "جلسة تصوير", "price": 450, "currency": "SAR",
                     "duration_minutes": 60, "max_photos": 60,
                     "deliverables": ["صور عالية الدقة"] } ],
    "working_hours": [ { "weekday": 0, "start_time": "16:00", "end_time": "22:00" } ],
    "portfolio": [ { "id": 21, "url": "https://...", "thumbnail_url": "https://...",
                     "width": 1200, "height": 800 } ],
    "whatsapp_url": "https://wa.me/966501234567?text=...",
    "bookings_count": 1
  }
}
```

`weekday`: `0` = الأحد … `6` = السبت.

### `GET /photographers/{slug}/availability`

| المعامل | النوع | الوصف |
|---|---|---|
| `from` / `to` | date | نطاق التاريخ (افتراضي: من اليوم + 14 يوماً) |
| `days` | int | 1..60 — بديل عن `to` |
| `package_id` | int | يحدد مدة الجلسة حسب الباقة المختارة |

```json
{
  "data": {
    "from": "2026-10-21",
    "to": "2026-11-04",
    "duration_minutes": 60,
    "days": [
      { "date": "2026-10-25",
        "slots": [ { "start": "2026-10-25T16:00:00+03:00", "end": "...", "label": "16:00" } ] }
    ]
  }
}
```

---

## 2. الحجز

### `POST /bookings`

```json
{
  "photographer": "sar-alhrby",
  "start_at": "2026-10-25T16:00:00",
  "package_id": 16,
  "customer_name": "عبدالله المطيري",
  "customer_email": "customer@example.com",
  "customer_phone": "+966500000009",
  "notes": "تصوير عائلي"
}
```

- `photographer` هو **slug** لا الـ id.
- `package_id` اختياري؛ إن حدّد يختلف سعر الحجز ومدة الجلسة حسب الباقة.
- الحقل `website` هو **honeypot**: إن أرسله أي روبوت يُرفض الطلب (422).

الرد `201`:

```json
{
  "message": "تم استلام طلب الحجز بنجاح، سيتواصل معك المصور لتأكيد الموعد.",
  "data": { "id": 31, "reference": "BK-HVB4Z2YW", "status": "pending", "...": "..." }
}
```

يُرفض بـ `422` إذا كان الموعد خارج ساعات العمل أو محجوزاً مسبقاً.
إن حدّد `package_id` لباقة مُعطّلة يُردّ بـ `404` (كأنها غير موجودة).

**بعد نجاح الإنشاء يُبلَّغ الطرفان تلقائياً** (يحدثان بعد التزام المعاملة،
وأي فشل في الإرسال يُسجَّل ولا يُفشل الحجز):

- **المصور**: إشعار مُخزَّن في لوحته (جرس الإشعارات) + إيميل «طلب حجز جديد».
- **العميل**: إيميل «تم استلام طلب الحجز» إلى `customer_email` — وهو إلزامي،
  فلا يوجد حجز بلا عنوان يصل إليه التأكيد.

---

## 3. المصادقة

### `POST /auth/register`

```json
{ "name": "مصور جديد", "email": "a@example.com", "password": "StrongPass@2026",
  "password_confirmation": "StrongPass@2026", "phone": "+966500000000" }
```

- كلمة المرور: 10 أحرف على الأقل، مع أحرف كبيرة وصغيرة وأرقام ورموز، وغير مخترقة (HIBP).
- الحساب الجديد يبدأ `pending` → لا يستطيع الدخول إلى لوحة التحكم حتى توافق الإدارة.
- الرد `201` يحتوي `token` — استخدمه مباشرة أو انتظر التفعيل.

### `POST /auth/login`

```json
{ "email": "admin@example.com", "password": "Admin@12345" }
```

الرد `200`: `{ "message", "user": {...}, "token": "..." }`

### `POST /auth/logout` · `GET /auth/me`

### `PUT /auth/email` · `PUT /auth/password`

إعادة تعيين بيانات الدخول — كلٌّ منهما **مستقل** عن الآخر، وكلاهما يتطلب
تأكيداً بكلمة المرور الحالية حتى لا يكفي تسريب الجلسة وحده لتغييرها.

```json
// PUT /auth/email
{ "email": "new@example.com", "current_password": "********" }

// PUT /auth/password
{ "current_password": "********", "password": "NewStrong@2026",
  "password_confirmation": "NewStrong@2026" }
```

- `422` إن كانت كلمة المرور الحالية خاطئة، أو إن كان البريد مسجّلاً لحساب آخر.
- قواعد كلمة المرور الجديدة **مطابقة للتسجيل**: 10 أحرف على الأقل + حالة مختلقة
  + أرقام + رموز + فحص `uncompromised` — وهذا الفحص **يفشل مفتوحاً** عند تعذّر
  الشبكة، ويرفض فقط ما ورد فعلاً في قوائم التسريب.
- كلا المسارين محدود بـ **5 محاولات في الدقيقة لكل حساب** (مُحدِّد `credentials`
  مفتاحه المسار + معرّف المستخدم، لا عنوان الـ IP — فالزملاء خلف NAT واحد لا
  يُقفلون بعضهم، ولا يأكل اختبارٌ مؤقت حساباً تجريبياً).
- الرد `{ "message" }` — والجلسة الجارية تبقى صالحة في الحالتين (المعرف لا يعتمد
  على البريد).

---

## 4. لوحة المصور

كل المسارات تتطلب `Authorization: Bearer` + حساب `active` + دور `photographer`.

### `GET /photographer/dashboard`

```json
{ "data": { "stats": { "bookings_total": 5, "bookings_pending": 2, "bookings_confirmed": 1,
                       "bookings_completed": 1, "visits_total": 118, "visits_today": 12,
                       "revenue_paid": 0, "revenue_outstanding": 0 },
             "series": [ { "date": "2026-09-22", "visits": 3, "bookings": 1, "revenue": 0 } ] } }
```

### `GET /photographer/profile` · `PUT /photographer/profile`

PUT يقبل: `name`, `headline`, `bio`, `city`, `phone`, `whatsapp`, `website`,
`years_experience`, `theme` (`primary`, `accent`, `background`, `surface`, `text` — كل لون `#RRGGBB`).

### `PUT /photographer/profile/working-hours`

```json
{ "hours": [
  { "weekday": 0, "start_time": "16:00", "end_time": "22:00", "slot_interval_minutes": 60 },
  { "weekday": 2, "start_time": "18:00", "end_time": "23:00", "slot_interval_minutes": 90 }
] }
```

المصفوفة تُستبدل بالكامل. يجب أن يكون `end_time` بعد `start_time`.

### `GET|POST|PUT|DELETE /photographer/packages`

```json
{ "name": "جلسة تصوير", "price": 450, "currency": "SAR", "duration_minutes": 60,
  "max_photos": 60, "deliverables": ["صور عالية الدقة"], "is_active": true }
```

### `GET /photographer/bookings`

معاملات: `status` (`pending|confirmed|completed|cancelled|rejected`)، `from`، `to`، `per_page`.

```json
{ "data": [ ... ], "meta": { ... }, "counts": { "pending": 2, "confirmed": 1 } }
```

### `PATCH /photographer/bookings/{id}`

```json
{ "status": "confirmed", "cancellation_reason": "اختياري عند الإلغاء" }
```

---

## 4ب. الباقات والأسعار

كل المسارات مرتبطة بالمصور المُصادَق عليه — `GET` تُرجع باقاته هو فقط،
وأي محاولة لتعديل أو حذف باقة لمصور آخر تُردّ بـ `403`.

| الطريقة | المسار | الوصف |
|---|---|---|
| GET | `/photographer/packages` | باقات المصور (مرتبة بـ `sort_order`) |
| POST | `/photographer/packages` | إنشاء باقة |
| PUT | `/photographer/packages/{id}` | تعديل باقة |
| DELETE | `/photographer/packages/{id}` | حذف باقة |

```json
{ "name": "جلسة عائلية", "description": "اختياري", "price": 450,
  "currency": "SAR", "duration_minutes": 60, "max_photos": 60,
  "deliverables": ["صور عالية الدقة", "ألبوم رقمي"],
  "is_active": true, "sort_order": 0 }
```

`price` و`duration_minutes` إلزاميان (المدة بين 15 دقيقة و 24 ساعة)،
و`currency` من: `SAR,AED,EGP,KWD,QAR,USD`، و`is_active=false` تُخفي الباقة من
صفحة المصار والحجز دون حذفها.

---

## 4ج. الإشعارات

تُخزَّن إشعارات المصور في جدول `notifications` وتظهر في جرس لوحته.
كل المسارات مُحصرة بالمصادَق عليه: معرّف إشعار لمصور آخر يُردّ بـ `404`.

| الطريقة | المسار | الوصف |
|---|---|---|
| GET | `/photographer/notifications` | آخر 20 إشعاراً + عدد غير المقروءة |
| PATCH | `/photographer/notifications/{id}/read` | تعليم إشعار واحد كمقروء |
| PATCH | `/photographer/notifications/read-all` | تعليم الكل كمقروء |

```json
// GET /photographer/notifications
{
  "data": [{
    "id": "9f0b...", "type": "App\\Notifications\\NewBookingForPhotographer",
    "title": "طلب حجز جديد", "reference": "BK-HVB4Z2YW",
    "customer_name": "عبدالله المطيري", "start_at": "2026-10-25T16:00:00+00:00",
    "package_name": "جلسة عائلية", "url": "/photographer/bookings",
    "read_at": null, "created_at": "2026-10-24T13:34:30+00:00"
  }],
  "meta": { "unread": 1 }
}
```

القوالب تمرّ عبر `mail.notification` (HTML) و`mail.notification-text` (نص) —
قالب عربي بـ `dir="rtl"`، لا قالب Laravel الافتراضي.

---

## 5. لوحة الإدارة

تتطلب دور `admin`.

### `GET /admin/photographers`

معاملات: `status` (`pending|active|suspended`)، `q`، `per_page`، `page`.

```json
{ "data": [ { "user": { "id": 8, "name": "...", "email": "...", "status": "pending" },
              "profile": { "slug": "msor-gdyd", "city": null },
              "subscription": { "id": 1, "plan_id": 2, "status": "active",
                                "storage_limit_bytes": 26843545600 } } ], "meta": { ... } }
```

### `PATCH /admin/photographers/{id}`

```json
{ "status": "active", "plan_id": 2, "subscription_status": "active",
  "starts_at": "2026-10-21T00:00:00Z", "ends_at": "2027-10-21T00:00:00Z",
  "storage_limit_bytes": 26843545600, "admin_notes": "دفع نقداً" }
```

- `status`: تفعيل (`active`) أو إيقاف (`suspended`) أو رجوع إلى `pending`.
- `plan_id`: يُنشئ/يحدّث الاشتراك ويضبط `storage_limit_bytes` من الباقة ما لم يُحدَّد يدوياً.
- تفعيل الحساب ينشر ملفه العام تلقائياً.

### إدارة باقات الاشتراك

هذه **نماذج الاشتراك** التي تُسنَد للمصورين وتحدد مساحة تخزينهم — لا باقات
التصوير التي يختارها العميل.

| الطريقة | المسار |
|---|---|
| `GET` | `/admin/plans` |
| `POST` | `/admin/plans` |
| `PUT` | `/admin/plans/{plan}` |
| `DELETE` | `/admin/plans/{plan}` |

```json
{ "data": [ { "id": 2, "name": "الباقة الاحترافية", "slug": "professional",
              "description": "...", "price": 349, "currency": "SAR",
              "storage_limit_bytes": 26843545600, "storage_limit_gb": 25,
              "max_galleries": 50, "max_clients": null, "max_bookings_per_month": null,
              "features": ["..."], "is_active": true, "sort_order": 2,
              "subscriptions_count": 6 } ] }
```

- `GET` يُرجع **كل** الباقات (النشطة والموقوفة) ليتمكن الأدمن من إدارة
  الموقوفة أيضاً؛ أما قوائم الإسناد في الفلترة على `is_active` — فتختفي
  الباقة الموقوفة منها فوراً دون مساس بالاشتراكات القائمة عليها.
- في الإنشاء والتحديث يُرسل `storage_limit_gb` (لأن الأدمن يفكّر
  بالجيجابايت) ويُحوَّل إلى بايت للتخزين، ويُعاد توليد الـ `slug` عند تغيير
  الاسم ليظل مشتقّاً منه.
- الأسماء فريدة (`422` عند التكرار): المُنتقي يميّز الباقات بالاسم، فاسم
  مكرر يربك الإسناد.
- **الحذف محميّ**: إن أشار إليه اشتراكات فلا يُحذف (`422` مع رسالة توجيه
  لإيقافها بدل الحذف)، وتمنع قاعدة البيانات ذلك بنفسها (`restrictOnDelete`)
  فلا يضيع سجل المشتركين.
- `subscriptions_count` يظهر فقط عند طلبه، ويُمكِّن الواجهة من إخفاء زر
  الحذف قبل أن يصادم الأدمن الحماية.

### إدارة الباقات باسم المصور

الأدمن يدير باقات **أي** مصور، والمسار يذكر المصور في كل طلب — فيكون كل رابط
واضحاً عمّا يفعله:

| الطريقة | المسار |
|---|---|
| `GET` | `/admin/photographers/{id}/packages` |
| `POST` | `/admin/photographers/{id}/packages` |
| `PUT` | `/admin/photographers/{id}/packages/{package}` |
| `DELETE` | `/admin/photographers/{id}/packages/{package}` |

- الجسم مطابق تماماً لمسارات المصور (`name`، `price`، `currency`،
  `duration_minutes`, `max_photos`, `deliverables`, `is_active`, `sort_order`)،
  والـ `slug` يُولَّد تحت معرّف المصور المختار فلا يتصادم مع مصور آخر.
- `{id}` يجب أن يكون حساب **مصور**، وإلا `404` — لا قائمة فارغة توهم بالخلو.
- `{package}` يجب أن ينتمي فعلاً لذلك المصور، وإلا `404`؛ فالرابط يصف ما يلمسه فعلاً.
- ما يكتبه الأدمن يظهر مباشرة في لوحة المصور وفي صفحته العامة ونافذة الحجز.

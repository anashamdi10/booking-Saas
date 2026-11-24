# دليل النشر في الإنتاج

الهدف: خادمان (أو خادمان منطقيان) — `api.example.com` للـ API و `app.example.com` للواجهة.

---

## 1. المتطلبات

```bash
sudo apt update
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-xml \
    php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-intl unzip git supervisor
```

## 2. Backend

```bash
sudo mkdir -p /var/www/photographer-booking
sudo chown -R deploy:deploy /var/www/photographer-booking
cd /var/www/photographer-booking

git clone <repo> backend
cd backend

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

### إعداد `.env` للإنتاج

```env
APP_NAME="منصة حجوزات المصورين"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=photographers
DB_USERNAME=photographers
DB_PASSWORD=<strong-password>

CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null

FILESYSTEM_DISK=r2
MEDIA_PUBLIC_DISK=r2
MEDIA_PRIVATE_DISK=media
MEDIA_MAX_UPLOAD_KB=20480

R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_BUCKET=photographer-media
R2_ACCESS_KEY_ID=<key>
R2_SECRET_ACCESS_KEY=<secret>
R2_URL=https://media.example.com

CORS_ALLOWED_ORIGINS=https://app.example.com
SANCTUM_EXPIRATION=10080
```

### تهيئة قاعدة البيانات

```bash
php artisan migrate --force
php artisan db:seed --force          # الباقات + بيانات تجريبية (احذفها قبل الإطلاق)
php artisan storage:link              # إن استخدمت قرصاً محلياً للملفات المؤقتة
```

> **مهم:** احذف `DemoDataSeeder` قبل الإنتاج، وأنشئ حساب المدير الحقيقي يدوياً:
> `php artisan tinker` ثم `User::create([... 'role' => 'admin', 'status' => 'active'])`.

> **قاعدة بيانات MySQL:** لا تستخدم `root` في الإنتاج. أنشئ مستخدماً مخصصاً:
> ```sql
> CREATE DATABASE photographers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> CREATE USER 'photographers'@'localhost' IDENTIFIED BY '<strong-password>';
> GRANT ALL PRIVILEGES ON photographers.* TO 'photographers'@'localhost';
> FLUSH PRIVILEGES;
> ```
> كل أعمدة التاريخ `DATETIME` لا `TIMESTAMP` — لأن `TIMESTAMP` في MySQL تتوقف عند 2038.

### الكاش

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

> بعد تعديل `.env` نفّذ `php artisan config:clear` ثم `config:cache` مجدداً.

---

## 3. Supervisor (الـ Queue والسcheduler)

`/etc/supervisor/conf.d/photographer.conf`:

```ini
[program:photographer-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/photographer-booking/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/photographer-booking/backend
autostart=true
autorestart=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/photographer-worker.log
stopwaitsecs=3600

[program:photographer-scheduler]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/photographer-booking/backend/artisan schedule:work
directory=/var/www/photographer-booking/backend
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/photographer-scheduler.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

## 4. Cron

```cron
* * * * * cd /var/www/photographer-booking/backend && php artisan schedule:run >> /dev/null 2>&1
```

## 5. nginx — الـ API

`/etc/nginx/sites-available/api.example.com`:

```nginx
server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate     /etc/letsencrypt/live/api.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.example.com/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    client_max_body_size 25M;

    root /var/www/photographer-booking/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 6. nginx — الواجهة (SPA)

`/etc/nginx/sites-available/app.example.com`:

```nginx
server {
    listen 443 ssl http2;
    server_name app.example.com;

    ssl_certificate     /etc/letsencrypt/live/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.example.com/privkey.pem;

    root /var/www/photographer-booking/frontend/dist;
    index index.html;

    # SPA: أي مسار غير موجود يُعيد index.html
    location / {
        try_files $uri $uri/ /index.html;
    }

    # الأصول المبنية بصيغة hashed — تخزين طويل
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # لا تُخزّن صفحة HTML
    location = /index.html {
        add_header Cache-Control "no-cache";
    }

    location ~* \.(js|css|woff2?|svg|png|jpg|jpeg|gif|webp|ico)$ {
        expires 1y;
        access_log off;
    }
}
```

> `try_files ... /index.html` ضروري لأن التطبيق يستخدم History Mode.

---

## 7. بناء الواجهة

```bash
cd /var/www/photographer-booking/frontend
npm ci
npm run build          # ينتج dist/
```

اضبط `VITE_API_BASE_URL` قبل البناء:

```bash
# .env.production
VITE_API_BASE_URL=https://api.example.com/api/v1
```

> `VITE_*` تُدمج وقت البناء — إذا غيّرت العنوان لاحقاً يجب إعادة البناء.

### الخيار البديل: تطبيق واحد بلا CORS

يمكن لـ Laravel نفسه أن يخدم الواجهة المبنية بدل نطاقين منفصلين:

```bash
cd frontend
npm run build:spa        # يبني ثم ينسخ dist إلى backend/public
```

- `routes/web.php` يعيد `index.html` لأي رابط غير `/api/*` و`/up` و`/storage/*`،
  فلا حاجة لكتلة `server` ثانية ولا لِـ `try_files ... /index.html`.
- اترك `VITE_API_BASE_URL` بقيمته النسبية `/api/v1` — لا تضبط عنواناً مطلقاً.
- الإعداد نفسه يعمل على Wasmer Edge: [`DEPLOY_WASMER.md`](./DEPLOY_WASMER.md).

---

## 8. التوسّع لآلاف المستخدمين

| Concern | الإجراء |
|---|---|
| PHP-FPM | `pm = dynamic`, `pm.max_children = 32` حسب عدد النوى |
| قاعدة البيانات | فهارس على `bookings (photographer_id, start_at)` و `bookings (photographer_id, status)` و `site_visits (visited_at)` — موجودة مسبقاً |
| الطابور | `queue:work` بـ 4 عمليات على الأقل؛ عدّاد الزيارات يُكتب في الـ queue لا في مسار الطلب |
| الكاش | Redis لـ cache + sessions |
| التخزين | R2/S3 — لا قرص محلي للصور |
| CDN | مرّر `public, max-age=60, s-maxage=300` إلى Cloudflare (مضبوط أصلاً على المسارات العامة) |
| المراقبة | `laravel/pail` أو Logs + تنبيهات على 5xx |
| الحجز المتزامن | حجزان في اللحظة نفسها على نفس الموعد — يُمنع بـ `lockForUpdate` داخل transaction |

### تسريع استعلامات التقارير

جدول `daily_stats` مُهيّأ مسبقاً للتجميع اليومي. أضف job مجدولاً يجمّع `site_visits` و `bookings`
يومياً إلى `daily_stats` بدل حسابها لحظياً عند الحاجة.

---

## 9. النسخ الاحتياطي

```bash
# قاعدة البيانات — الأهم
mysqldump --single-transaction --quick --routines --triggers photographers \
  | gzip > /var/backups/photographers-$(date +%F-%H%M).sql.gz

# ملفات R2: فعّل versioning في bucket، ونسخ دوري عبر versioning أو عبر أداة R2
```

- احتفظ بـ **7 نسخ يومية** + **4 نسخ أسبوعية**.
- جرّب الاستعادة مرة واحدة على الأقل شهرياً.
- لا تنسَ `storage/app` إن استخدمت قرصاً محلياً.

---

## 10. Checklist قبل الإطلاق

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` جديد، و `.env` غير مضمّن في Git
- [ ] `CORS_ALLOWED_ORIGINS` بالنطاقات الفعلية فقط
- [ ] حساب المدير التجريبي محذوف/مغيّر، وكلمات مرور الحسابات التجريبية مغيّرة
- [ ] `DemoDataSeeder` غير مُستدعى في الإنتاج
- [ ] HTTPS على النطاقين + HSTS
- [ ] `config:cache`, `route:cache`, `event:cache`
- [ ] Supervisor يعمل: `supervisorctl status`
- [ ] Cron كل دقيقة مضبوط
- [ ] R2/S3: الحاوية خاصة، الروابط موقّتة للألبومات
- [ ] Redis مُهيّأ (password + bind 127.0.0.1)
- [ ] نسخة احتياطية يومية مجرّبة
- [ ] مراقبة 5xx + تنبيه عند تعطّل الـ queue

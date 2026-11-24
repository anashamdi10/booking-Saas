<?php

use App\Enums\UserStatus;
use App\Models\Package;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkingHour;
use App\Notifications\BookingConfirmationForCustomer;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end smoke test for the public API.
 *
 * Runs the real HTTP kernel in-process so it needs no running web server.
 *
 * Usage: php tools/smoke-test.php
 */
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);

// Boot the application so facades and configuration are available.
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// The rate limiters are part of the behaviour under test, so the dev cache is
// cleared to keep repeated runs deterministic.
Cache::flush();

$passed = 0;
$failed = 0;

/**
 * @param  array<string, mixed>  $body
 * @param  array<string, string>  $headers
 */
function apiRequest(
    Kernel $kernel,
    string $method,
    string $uri,
    array $body = [],
    array $headers = [],
): Response {
    $server = ['HTTP_ACCEPT' => 'application/json'];

    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    $content = null;

    if ($body !== []) {
        $content = json_encode($body, JSON_UNESCAPED_UNICODE);
        $server['CONTENT_TYPE'] = 'application/json';
    }

    $request = Request::create($uri, $method, [], [], [], $server, $content);

    return $kernel->handle($request);
}

function check(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  [PASS] {$label}".PHP_EOL;

        return;
    }

    $failed++;
    echo "  [FAIL] {$label}".($detail !== '' ? " -> {$detail}" : '').PHP_EOL;
}

/**
 * Human readable summary of an API response.
 */
function describe(Response $response): string
{
    return $response->getStatusCode().' '.substr($response->getContent(), 0, 300);
}

function jsonBody(Response $response): array
{
    return json_decode($response->getContent(), true) ?? [];
}

echo PHP_EOL.'== Public endpoints =='.PHP_EOL;

$stats = apiRequest($kernel, 'GET', '/api/v1/site/stats');
$statsBody = jsonBody($stats);
check('site stats returns 200', $stats->getStatusCode() === 200, (string) $stats->getStatusCode());
check('site stats expose photographer count', ($statsBody['data']['photographers_count'] ?? 0) > 0);

$list = apiRequest($kernel, 'GET', '/api/v1/photographers?per_page=5');
$listBody = jsonBody($list);
check('photographer list returns 200', $list->getStatusCode() === 200);
check('photographer list has items', count($listBody['data'] ?? []) > 0);
check('photographer list paginates', isset($listBody['meta']['total']));

$slug = $listBody['data'][0]['slug'] ?? null;
check('a photographer slug is available', $slug !== null);

$profile = apiRequest($kernel, 'GET', '/api/v1/photographers/'.$slug);
$profileBody = jsonBody($profile);
check('photographer profile returns 200', $profile->getStatusCode() === 200);
check('profile exposes packages', isset($profileBody['data']['packages']));
check('profile exposes working hours', isset($profileBody['data']['working_hours']));
check('profile exposes a whatsapp link', str_starts_with((string) ($profileBody['data']['whatsapp_url'] ?? ''), 'https://wa.me/'));
check('profile keeps utf-8 arabic content', str_contains((string) ($profileBody['data']['profile']['name'] ?? ''), 'سارة')
    || mb_check_encoding((string) ($profileBody['data']['profile']['name'] ?? ''), 'UTF-8'));

$availability = apiRequest($kernel, 'GET', '/api/v1/photographers/'.$slug.'/availability?days=10');
$availabilityBody = jsonBody($availability);
check('availability returns 200', $availability->getStatusCode() === 200);
check('availability returns day buckets', isset($availabilityBody['data']['days']));

$slot = null;
$slotDate = null;

foreach ($availabilityBody['data']['days'] ?? [] as $day) {
    if (($day['slots'] ?? []) !== []) {
        $slot = $day['slots'][0];
        $slotDate = $day['date'];
        break;
    }
}

check('at least one bookable slot exists', $slot !== null);

echo PHP_EOL.'== Booking flow =='.PHP_EOL;

if ($slot !== null) {
    $bookingBody = [
        'photographer' => $slug,
        'start_at' => $slotDate.'T'.substr((string) $slot['start'], 11, 8),
        'customer_name' => 'عميل تجريبي',
        'customer_email' => 'smoke@example.com',
        'customer_phone' => '+966500000001',
        'notes' => 'طلب من اختبار الدخان',
    ];

    /*
     * Both sides must hear about a new booking: the photographer in the
     * dashboard and the visitor at the address left on the form. Recording the
     * NotificationSent events observes the mail channel without faking it, so
     * the stored notification is still really written.
     */
    $sentNotifications = [];

    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sentNotifications): void {
        $route = $event->notifiable instanceof AnonymousNotifiable
            ? ($event->notifiable->routes['mail'] ?? null)
            : $event->notifiable->email;

        $sentNotifications[] = [
            'type' => $event->notification::class,
            'channel' => $event->channel,
            'route' => $route,
        ];
    });

    $booking = apiRequest($kernel, 'POST', '/api/v1/bookings', $bookingBody);
    $bookingData = jsonBody($booking);
    check('booking is created', $booking->getStatusCode() === 201, (string) $booking->getStatusCode().' '.substr($booking->getContent(), 0, 200));
    check('booking returns a reference', isset($bookingData['data']['reference']));

    $photographerUser = User::query()
        ->whereHas('profile', fn ($query) => $query->where('slug', $slug))
        ->first();

    if ($photographerUser !== null && isset($bookingData['data']['reference'])) {
        $stored = $photographerUser->notifications()->latest('created_at')->first();

        check(
            'photographer is notified in the dashboard',
            $stored !== null && ($stored->data['reference'] ?? null) === $bookingData['data']['reference'],
            'stored '.$photographerUser->notifications()->count().' notification(s)',
        );
        check('dashboard notification starts unread', $stored !== null && $stored->read_at === null);
    }

    $customerConfirmation = null;

    foreach ($sentNotifications as $sent) {
        if ($sent['type'] === BookingConfirmationForCustomer::class) {
            $customerConfirmation = $sent;
        }
    }

    check(
        'customer receives a confirmation email',
        $customerConfirmation !== null && $customerConfirmation['route'] === 'smoke@example.com',
        json_encode($sentNotifications, JSON_UNESCAPED_UNICODE),
    );

    $duplicate = apiRequest($kernel, 'POST', '/api/v1/bookings', $bookingBody);
    check('double booking on the same slot is rejected', $duplicate->getStatusCode() === 422, (string) $duplicate->getStatusCode());

    $outside = apiRequest($kernel, 'POST', '/api/v1/bookings', [
        ...$bookingBody,
        // A Friday (no working hours) shortly after midnight, inside the allowed window.
        'start_at' => (new DateTime('next friday 03:00'))->format('Y-m-d\TH:i:s'),
    ]);
    check('booking outside working hours is rejected', $outside->getStatusCode() === 422, (string) $outside->getStatusCode());

    $invalid = apiRequest($kernel, 'POST', '/api/v1/bookings', [
        'photographer' => $slug,
        'customer_name' => 'x',
        'customer_email' => 'not-an-email',
    ]);
    check('invalid booking payload returns 422', $invalid->getStatusCode() === 422, (string) $invalid->getStatusCode());
    check('validation errors are returned per field', isset(jsonBody($invalid)['errors']['customer_email']));

    /*
     * A deactivated package is hidden on the public profile page. Posting its
     * id directly must be refused too, otherwise the switch would be bypassable.
     */
    $owner = User::query()
        ->whereHas('profile', fn ($query) => $query->where('slug', $slug))
        ->first();
    $inactive = $owner !== null
        ? Package::query()->where('photographer_id', $owner->id)->orderBy('id')->first()
        : null;

    $bookedStart = $slotDate.'T'.substr((string) $slot['start'], 11, 8);
    $secondSlot = null;
    $secondDate = null;

    foreach ($availabilityBody['data']['days'] ?? [] as $day) {
        foreach ($day['slots'] ?? [] as $candidate) {
            $candidateStart = $day['date'].'T'.substr((string) $candidate['start'], 11, 8);

            if ($candidateStart !== $bookedStart) {
                $secondSlot = $candidate;
                $secondDate = $day['date'];
                break 2;
            }
        }
    }

    if ($inactive !== null && $secondSlot !== null) {
        $inactive->update(['is_active' => false]);

        $blocked = apiRequest($kernel, 'POST', '/api/v1/bookings', [
            ...$bookingBody,
            'package_id' => $inactive->id,
            'start_at' => $secondDate.'T'.substr((string) $secondSlot['start'], 11, 8),
        ]);
        check(
            'inactive package cannot be booked',
            $blocked->getStatusCode() === 404,
            (string) $blocked->getStatusCode().' '.substr($blocked->getContent(), 0, 160),
        );

        $inactive->update(['is_active' => true]);
    }
}

echo PHP_EOL.'== Authentication =='.PHP_EOL;

$login = apiRequest($kernel, 'POST', '/api/v1/auth/login', [
    'email' => 'photographer1@example.com',
    'password' => 'Photographer@123',
]);
$loginBody = jsonBody($login);
check('photographer can log in', $login->getStatusCode() === 200, (string) $login->getStatusCode());
check('login returns a token', isset($loginBody['token']));

$token = $loginBody['token'] ?? null;
$auth = $token !== null ? ['Authorization' => 'Bearer '.$token] : [];

/*
 * Guard against mixing up the units of `sanctum.expiration` (it is expressed
 * in MINUTES). Adding it as days produced an expiry date in 2054, which MySQL
 * rejects because a TIMESTAMP column stops at 2038.
 */
if ($token !== null) {
    $tokenId = explode('|', $token)[0] ?? null;
    $expiresAt = $tokenId !== null
        ? DB::table('personal_access_tokens')->where('id', $tokenId)->value('expires_at')
        : null;

    $days = $expiresAt !== null
        ? now()->diffInMinutes(Carbon::parse($expiresAt), false) / 1440
        : null;

    check(
        'token expires within the configured window',
        $days !== null && $days > 0 && $days <= 8,
        $days !== null ? number_format($days, 2).' days' : 'no expiry stored',
    );
}

$badLogin = apiRequest($kernel, 'POST', '/api/v1/auth/login', [
    'email' => 'photographer1@example.com',
    'password' => 'wrong-password',
]);
check('wrong password is rejected', $badLogin->getStatusCode() === 422, (string) $badLogin->getStatusCode());

$register = apiRequest($kernel, 'POST', '/api/v1/auth/register', [
    'name' => 'مصور جديد',
    'email' => 'new.photographer.'.uniqid().'@example.com',
    'password' => 'StrongPass@2026',
    'password_confirmation' => 'StrongPass@2026',
]);
$registerBody = jsonBody($register);
check('registration succeeds', $register->getStatusCode() === 201, (string) $register->getStatusCode().' '.substr($register->getContent(), 0, 200));
check('new account starts as pending', ($registerBody['user']['status'] ?? '') === 'pending');

$weakPassword = apiRequest($kernel, 'POST', '/api/v1/auth/register', [
    'name' => 'مصور ضعيف',
    'email' => 'weak@example.com',
    'password' => 'password',
    'password_confirmation' => 'password',
]);
check('weak password is rejected', $weakPassword->getStatusCode() === 422, (string) $weakPassword->getStatusCode());

/*
 * Credential rotation runs on the account registered above — promoted to
 * active first, because these routes sit behind `user.active`. Using a
 * throwaway account keeps a failed run from ever leaving a demo login with
 * an address or a password nobody expects.
 *
 * Four calls land on each endpoint, staying inside the route's throttle.
 */
$credentialId = $registerBody['user']['id'] ?? null;
$credentialToken = $registerBody['token'] ?? null;

if ($credentialId !== null && $credentialToken !== null) {
    User::query()->whereKey($credentialId)->update(['status' => UserStatus::Active->value]);

    $credentialAuth = ['Authorization' => 'Bearer '.$credentialToken];

    $wrongEmailPassword = apiRequest($kernel, 'PUT', '/api/v1/auth/email', [
        'email' => 'rotated@example.com',
        'current_password' => 'wrong-password',
    ], $credentialAuth);
    check(
        'email change requires the current password',
        $wrongEmailPassword->getStatusCode() === 422,
        (string) $wrongEmailPassword->getStatusCode(),
    );

    $wrongPasswordPassword = apiRequest($kernel, 'PUT', '/api/v1/auth/password', [
        'current_password' => 'wrong-password',
        'password' => 'Rotated@Pass2026',
        'password_confirmation' => 'Rotated@Pass2026',
    ], $credentialAuth);
    check(
        'password change requires the current password',
        $wrongPasswordPassword->getStatusCode() === 422,
        (string) $wrongPasswordPassword->getStatusCode(),
    );

    $takenEmail = apiRequest($kernel, 'PUT', '/api/v1/auth/email', [
        'email' => 'photographer1@example.com',
        'current_password' => 'StrongPass@2026',
    ], $credentialAuth);
    check('an address owned by someone else is rejected', $takenEmail->getStatusCode() === 422, (string) $takenEmail->getStatusCode());

    $newEmail = 'rotated.'.uniqid().'@example.com';
    $emailChange = apiRequest($kernel, 'PUT', '/api/v1/auth/email', [
        'email' => $newEmail,
        'current_password' => 'StrongPass@2026',
    ], $credentialAuth);
    $changedEmail = jsonBody($emailChange)['user']['email'] ?? null;
    check(
        'email can be reassigned',
        $emailChange->getStatusCode() === 200 && $changedEmail === $newEmail,
        (string) $emailChange->getStatusCode().' '.var_export($changedEmail, true),
    );

    $passwordChange = apiRequest($kernel, 'PUT', '/api/v1/auth/password', [
        'current_password' => 'StrongPass@2026',
        'password' => 'Rotated@Pass2026',
        'password_confirmation' => 'Rotated@Pass2026',
    ], $credentialAuth);
    check('password can be reassigned', $passwordChange->getStatusCode() === 200, (string) $passwordChange->getStatusCode());

    // Both changes together, proved by an actual sign-in.
    $relogin = apiRequest($kernel, 'POST', '/api/v1/auth/login', [
        'email' => $newEmail,
        'password' => 'Rotated@Pass2026',
    ]);
    check(
        'sign-in works with the reassigned email and password',
        $relogin->getStatusCode() === 200 && isset(jsonBody($relogin)['token']),
        (string) $relogin->getStatusCode(),
    );
}

echo PHP_EOL.'== Photographer area =='.PHP_EOL;

if ($token !== null) {
    $me = apiRequest($kernel, 'GET', '/api/v1/auth/me', [], $auth);
    check('auth/me returns the current user', $me->getStatusCode() === 200, (string) $me->getStatusCode());

    $dashboard = apiRequest($kernel, 'GET', '/api/v1/photographer/dashboard', [], $auth);
    check('dashboard returns stats', $dashboard->getStatusCode() === 200, (string) $dashboard->getStatusCode());
    check('dashboard returns a daily series', isset(jsonBody($dashboard)['data']['series']));

    $myBookings = apiRequest($kernel, 'GET', '/api/v1/photographer/bookings', [], $auth);
    $myBookingsBody = jsonBody($myBookings);
    check('photographer sees own bookings', $myBookings->getStatusCode() === 200, (string) $myBookings->getStatusCode());
    check('booking counts are returned', isset($myBookingsBody['counts']['pending']));
    check(
        'authenticated responses are not publicly cacheable',
        ! str_contains((string) $myBookings->headers->get('Cache-Control'), 'public'),
        (string) $myBookings->headers->get('Cache-Control'),
    );

    $profileUpdate = apiRequest($kernel, 'PUT', '/api/v1/photographer/profile', [
        'headline' => 'تصوير احترافي للمناسبات',
        'city' => 'الرياض',
    ], $auth);
    check('photographer can update own profile', $profileUpdate->getStatusCode() === 200, (string) $profileUpdate->getStatusCode().' '.substr($profileUpdate->getContent(), 0, 200));

    $hours = apiRequest($kernel, 'PUT', '/api/v1/photographer/profile/working-hours', [
        'hours' => [
            ['weekday' => 0, 'start_time' => '16:00', 'end_time' => '22:00', 'slot_interval_minutes' => 60],
            ['weekday' => 2, 'start_time' => '18:00', 'end_time' => '23:00', 'slot_interval_minutes' => 90],
        ],
    ], $auth);
    check('working hours can be replaced', $hours->getStatusCode() === 200, (string) $hours->getStatusCode().' '.substr($hours->getContent(), 0, 200));

    $invalidHours = apiRequest($kernel, 'PUT', '/api/v1/photographer/profile/working-hours', [
        'hours' => [['weekday' => 0, 'start_time' => '22:00', 'end_time' => '16:00']],
    ], $auth);
    check('invalid working hours are rejected', $invalidHours->getStatusCode() === 422, (string) $invalidHours->getStatusCode());

    $package = apiRequest($kernel, 'POST', '/api/v1/photographer/packages', [
        'name' => 'باقة اختبار',
        'price' => 500,
        'duration_minutes' => 60,
        'deliverables' => ['صور عالية الدقة'],
    ], $auth);
    check('package can be created', $package->getStatusCode() === 201, (string) $package->getStatusCode().' '.substr($package->getContent(), 0, 200));
    check('package gets a slug', isset(jsonBody($package)['data']['slug']));

    $packageId = jsonBody($package)['data']['id'] ?? null;

    if ($packageId !== null) {
        $updated = apiRequest($kernel, 'PUT', '/api/v1/photographer/packages/'.$packageId, [
            'name' => 'باقة اختبار',
            'price' => 750,
            'duration_minutes' => 90,
            'deliverables' => ['صور عالية الدقة', 'ألبوم رقمي'],
        ], $auth);
        check('package can be updated', $updated->getStatusCode() === 200, describe($updated));
        check('package price changes after update', (float) (jsonBody($updated)['data']['price'] ?? 0) === 750.0, describe($updated));

        // Every photographer must only ever see and touch their own packages.
        $otherLogin = apiRequest($kernel, 'POST', '/api/v1/auth/login', [
            'email' => 'photographer2@example.com',
            'password' => 'Photographer@123',
        ]);
        $otherToken = jsonBody($otherLogin)['token'] ?? null;

        if ($otherToken !== null) {
            $otherAuth = ['Authorization' => 'Bearer '.$otherToken];

            $crossEdit = apiRequest($kernel, 'PUT', '/api/v1/photographer/packages/'.$packageId, [
                'name' => 'محاولة تعديل أجنبية',
                'price' => 1,
                'duration_minutes' => 60,
            ], $otherAuth);
            check('another photographer cannot edit my package', $crossEdit->getStatusCode() === 403, describe($crossEdit));

            $crossDelete = apiRequest($kernel, 'DELETE', '/api/v1/photographer/packages/'.$packageId, [], $otherAuth);
            check('another photographer cannot delete my package', $crossDelete->getStatusCode() === 403, describe($crossDelete));

            $otherList = apiRequest($kernel, 'GET', '/api/v1/photographer/packages', [], $otherAuth);
            $otherIds = array_column(jsonBody($otherList)['data'] ?? [], 'id');
            check('package list is scoped to its owner', ! in_array($packageId, $otherIds, true), 'returned '.count($otherIds).' packages');
        }

        $deleted = apiRequest($kernel, 'DELETE', '/api/v1/photographer/packages/'.$packageId, [], $auth);
        check('package can be deleted', $deleted->getStatusCode() === 200, describe($deleted));

        $afterDelete = apiRequest($kernel, 'GET', '/api/v1/photographer/packages', [], $auth);
        $remainingIds = array_column(jsonBody($afterDelete)['data'] ?? [], 'id');
        check('deleted package is gone', ! in_array($packageId, $remainingIds, true), 'remaining '.count($remainingIds));
    }

    echo PHP_EOL.'== Authorization boundaries =='.PHP_EOL;

    $adminAttempt = apiRequest($kernel, 'GET', '/api/v1/admin/photographers', [], $auth);
    check('photographer cannot reach admin routes', $adminAttempt->getStatusCode() === 403, describe($adminAttempt));

    $anonymous = apiRequest($kernel, 'GET', '/api/v1/photographer/dashboard');
    check('anonymous cannot reach the photographer area', $anonymous->getStatusCode() === 401, describe($anonymous));

    $adminLogin = apiRequest($kernel, 'POST', '/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'Admin@12345',
    ]);
    $adminToken = jsonBody($adminLogin)['token'] ?? null;
    check('admin can log in', $adminLogin->getStatusCode() === 200, describe($adminLogin));

    if ($adminToken !== null) {
        $adminAuth = ['Authorization' => 'Bearer '.$adminToken];
        $adminList = apiRequest($kernel, 'GET', '/api/v1/admin/photographers', [], $adminAuth);
        check('admin can list photographers', $adminList->getStatusCode() === 200, describe($adminList));

        $plans = apiRequest($kernel, 'GET', '/api/v1/admin/plans', [], $adminAuth);
        check('admin can list plans', $plans->getStatusCode() === 200, describe($plans));
        check('plans define a storage quota', isset(jsonBody($plans)['data'][0]['storage_limit_bytes']));

        /*
         * Admin plan management. These are the plans a photographer is
         * ASSIGNED (storage quotas), managed from the same panel that hands
         * them out: created, edited, retired — and deleted only once nothing
         * depends on them.
         */
        $planBody = jsonBody($plans)['data'] ?? [];
        check('plans report whether they are active', isset($planBody[0]['is_active']));

        // A leftover from an interrupted run would trip the unique-name rule
        // below, so clear it out first — but never one that somehow gained
        // subscriptions, since those are guarded.
        Plan::query()
            ->where('name', 'باقة أدمن اختبار')
            ->whereDoesntHave('subscriptions')
            ->delete();

        $createdPlan = apiRequest($kernel, 'POST', '/api/v1/admin/plans', [
            'name' => 'باقة أدمن اختبار',
            'description' => 'أُنشئت من لوحة الإدارة',
            'price' => 199,
            'currency' => 'SAR',
            'storage_limit_gb' => 7,
            'features' => ['فحص'],
            'is_active' => true,
        ], $adminAuth);
        $createdPlanId = jsonBody($createdPlan)['data']['id'] ?? null;
        check('admin can create a subscription plan', $createdPlan->getStatusCode() === 201, describe($createdPlan));
        check('gigabytes are stored as bytes', (jsonBody($createdPlan)['data']['storage_limit_bytes'] ?? null) === 7 * 1024 ** 3);

        if ($createdPlanId !== null) {
            $duplicatePlan = apiRequest($kernel, 'POST', '/api/v1/admin/plans', [
                'name' => 'باقة أدمن اختبار',
                'price' => 10,
                'storage_limit_gb' => 1,
            ], $adminAuth);
            check('two plans cannot share a name', $duplicatePlan->getStatusCode() === 422, describe($duplicatePlan));

            $renamedPlan = apiRequest($kernel, 'PUT', '/api/v1/admin/plans/'.$createdPlanId, [
                'name' => 'باقة أدمن اختبار — محدّثة',
                'price' => 250,
                'currency' => 'SAR',
                'storage_limit_gb' => 12,
                'features' => ['فحص', 'أولوية'],
                'is_active' => false,
                'sort_order' => 5,
            ], $adminAuth);
            $renamed = jsonBody($renamedPlan)['data'] ?? [];
            check('admin can update a plan', $renamedPlan->getStatusCode() === 200, describe($renamedPlan));
            check(
                'the update lands with the new storage',
                ($renamed['price'] ?? null) == 250
                    && ($renamed['storage_limit_bytes'] ?? null) === 12 * 1024 ** 3
                    && ($renamed['is_active'] ?? true) === false,
                describe($renamedPlan),
            );

            $listAfterUpdate = apiRequest($kernel, 'GET', '/api/v1/admin/plans', [], $adminAuth);
            $listedIds = array_column(jsonBody($listAfterUpdate)['data'] ?? [], 'id');
            check('the retired plan still lists for the admin', in_array($createdPlanId, $listedIds, true));
        }

        // Subscriptions already point at a plan in the demo data, so it must
        // refuse deletion and keep its history intact.
        $planInUse = Plan::query()->whereHas('subscriptions')->first();
        check('demo data leaves a plan in use', $planInUse !== null);
        if ($planInUse !== null) {
            $blockedDelete = apiRequest($kernel, 'DELETE', '/api/v1/admin/plans/'.$planInUse->id, [], $adminAuth);
            check('a plan in use cannot be deleted', $blockedDelete->getStatusCode() === 422, describe($blockedDelete));
        }

        if ($createdPlanId !== null) {
            $deletedPlan = apiRequest($kernel, 'DELETE', '/api/v1/admin/plans/'.$createdPlanId, [], $adminAuth);
            check('admin can delete a plan nobody uses', $deletedPlan->getStatusCode() === 204, describe($deletedPlan));

            $listAfterDelete = apiRequest($kernel, 'GET', '/api/v1/admin/plans', [], $adminAuth);
            $remainingIds = array_column(jsonBody($listAfterDelete)['data'] ?? [], 'id');
            check('the deleted plan is gone', ! in_array($createdPlanId, $remainingIds, true));
        }

        $planAttempt = apiRequest($kernel, 'GET', '/api/v1/admin/plans', [], $auth);
        check('photographer cannot use the admin plan routes', $planAttempt->getStatusCode() === 403, describe($planAttempt));

        /*
         * Admin package management. Every path names the photographer being
         * acted on, so the checks also pin down that a package really belongs
         * to that photographer — and that what the admin writes is exactly
         * what the photographer then sees in their own dashboard.
         */
        $adminTarget = User::query()->where('email', 'photographer2@example.com')->first();
        $plainAdmin = User::query()->where('email', 'admin@example.com')->first();
        $firstPhotographer = User::query()->where('email', 'photographer1@example.com')->first();

        if ($adminTarget !== null && $plainAdmin !== null && $firstPhotographer !== null) {
            $targetBase = '/api/v1/admin/photographers/'.$adminTarget->id.'/packages';

            $adminPackages = apiRequest($kernel, 'GET', $targetBase, [], $adminAuth);
            check('admin can list a photographer\'s packages', $adminPackages->getStatusCode() === 200, describe($adminPackages));

            $created = apiRequest($kernel, 'POST', $targetBase, [
                'name' => 'باقة أدمن اختبار',
                'description' => 'أُنشئت من لوحة الإدارة',
                'price' => 640,
                'currency' => 'SAR',
                'duration_minutes' => 75,
                'max_photos' => 40,
                'deliverables' => ['صور معالجة'],
                'is_active' => true,
            ], $adminAuth);
            $adminPackageId = jsonBody($created)['data']['id'] ?? null;
            check('admin can create a package for a photographer', $created->getStatusCode() === 201, describe($created));

            if ($adminPackageId !== null) {
                // The owner must see exactly what the admin created.
                $ownerLogin = apiRequest($kernel, 'POST', '/api/v1/auth/login', [
                    'email' => 'photographer2@example.com',
                    'password' => 'Photographer@123',
                ]);
                $ownerAuth = jsonBody($ownerLogin)['token'] !== null
                    ? ['Authorization' => 'Bearer '.jsonBody($ownerLogin)['token']]
                    : [];

                $ownerList = apiRequest($kernel, 'GET', '/api/v1/photographer/packages', [], $ownerAuth);
                $ownerIds = array_column(jsonBody($ownerList)['data'] ?? [], 'id');
                check('the photographer sees the package the admin created', in_array($adminPackageId, $ownerIds, true), 'returned '.count($ownerIds).' packages');

                $updated = apiRequest($kernel, 'PUT', $targetBase.'/'.$adminPackageId, [
                    'name' => 'باقة أدمن اختبار',
                    'price' => 777,
                    'duration_minutes' => 90,
                ], $adminAuth);
                check('admin can update the package', $updated->getStatusCode() === 200, describe($updated));
                check('the update landed', (float) (jsonBody($updated)['data']['price'] ?? 0) === 777.0, describe($updated));

                // The URL has to describe what it touches: another
                // photographer's path, or a non-photographer, is a 404.
                $wrongPath = apiRequest($kernel, 'PUT', '/api/v1/admin/photographers/'.$firstPhotographer->id.'/packages/'.$adminPackageId, [
                    'name' => 'باقة في مسار مخطئ',
                    'price' => 1,
                    'duration_minutes' => 60,
                ], $adminAuth);
                check('a package under another photographer\'s path is a 404', $wrongPath->getStatusCode() === 404, describe($wrongPath));

                $notAPhotographer = apiRequest($kernel, 'GET', '/api/v1/admin/photographers/'.$plainAdmin->id.'/packages', [], $adminAuth);
                check('an account that is not a photographer is a 404', $notAPhotographer->getStatusCode() === 404, describe($notAPhotographer));

                $photographerBlocked = apiRequest($kernel, 'GET', $targetBase, [], $auth);
                check('photographer cannot use the admin package routes', $photographerBlocked->getStatusCode() === 403, describe($photographerBlocked));

                $deleted = apiRequest($kernel, 'DELETE', $targetBase.'/'.$adminPackageId, [], $adminAuth);
                check('admin can delete the package', $deleted->getStatusCode() === 200, describe($deleted));

                $gone = apiRequest($kernel, 'GET', $targetBase, [], $adminAuth);
                $goneIds = array_column(jsonBody($gone)['data'] ?? [], 'id');
                check('deleted package is gone from the photographer\'s list', ! in_array($adminPackageId, $goneIds, true), 'remaining '.count($goneIds));
            }
        }
    }
}

echo PHP_EOL.'== Cleanup =='.PHP_EOL;

// The test mutates the demo data; restore it so repeated runs stay deterministic.
if ($token !== null) {
    $photographer = User::query()->where('email', 'photographer1@example.com')->first();

    if ($photographer !== null) {
        Package::query()
            ->where('photographer_id', $photographer->id)
            ->where('name', 'باقة اختبار')
            ->delete();

        // Safety net: a failed assertion must never leave a demo package
        // deactivated between runs.
        Package::query()
            ->where('photographer_id', $photographer->id)
            ->update(['is_active' => true]);

        // Bookings raised by this run leave notifications behind.
        $photographer->notifications()->delete();

        $photographer->workingHours()->delete();

        foreach ([0, 1, 2, 3, 4] as $weekday) {
            WorkingHour::query()->create([
                'photographer_id' => $photographer->id,
                'weekday' => $weekday,
                'start_time' => '16:00:00',
                'end_time' => '22:00:00',
                'slot_interval_minutes' => 60,
                'is_active' => true,
            ]);
        }

        echo '  [DONE] demo data restored'.PHP_EOL;
    }
}

/*
 * Anything an interrupted admin-package run left on another photographer —
 * those packages are not part of the demo data.
 */
$adminLeftover = User::query()->where('email', 'photographer2@example.com')->first();

if ($adminLeftover !== null) {
    Package::query()
        ->where('photographer_id', $adminLeftover->id)
        ->where('name', 'like', 'باقة أدمن%')
        ->delete();
}

User::query()
    ->where('email', 'like', 'new.photographer.%@example.com')
    ->each(fn (User $user) => $user->delete());

/*
 * The credential checks reassign that account's address, so it no longer
 * matches the pattern above and has to be removed by its id.
 */
if (isset($credentialId) && $credentialId !== null) {
    User::query()->whereKey($credentialId)->delete();
}

echo PHP_EOL."\n{$passed} passed, {$failed} failed.".PHP_EOL;

exit($failed === 0 ? 0 : 1);

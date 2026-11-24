<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\LandingTemplate;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PhotographerProfile;
use App\Models\Plan;
use App\Models\PortfolioImage;
use App\Models\SiteVisit;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Sample photos served from a public CDN so the demo looks alive.
     */
    private const SAMPLE_IMAGES = [
        'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=1200&q=80',
        'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=1200&q=80',
        'https://images.unsplash.com/photo-1471341971476-ae15ff5dd4ea?w=1200&q=80',
        'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1200&q=80',
        'https://images.unsplash.com/photo-1452780212940-6f5c0d14d848?w=1200&q=80',
        'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=1200&q=80',
    ];

    public function run(): void
    {
        $this->seedAdmin();
        $this->seedPhotographers();
    }

    private function seedAdmin(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مدير المنصة',
                'password' => Hash::make('Admin@12345'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedPhotographers(): void
    {
        $plan = Plan::query()->where('slug', 'professional')->first();

        $photographers = [
            ['name' => 'سارة الحربي', 'city' => 'الرياض', 'headline' => 'تصوير مناسبات وعائلات', 'featured' => true, 'whatsapp' => '966501234567'],
            ['name' => 'خالد العتيبي', 'city' => 'جدة', 'headline' => 'تصوير منتجات تجاري', 'featured' => true, 'whatsapp' => '966502345678'],
            ['name' => 'نورة القحطاني', 'city' => 'الدمام', 'headline' => 'تصوير أعراس', 'featured' => true, 'whatsapp' => '966503456789'],
            ['name' => 'ماجد الشمري', 'city' => 'مكة المكرمة', 'headline' => 'تصوير معماري وعقاري', 'featured' => false, 'whatsapp' => '966504567890'],
            ['name' => 'ريم الدوسري', 'city' => 'المدينة المنورة', 'headline' => 'تصوير بورتريه احترافي', 'featured' => false, 'whatsapp' => '966505678901'],
            ['name' => 'يوسف باوزير', 'city' => 'أبها', 'headline' => 'تصوير طعام ومقاهي', 'featured' => false, 'whatsapp' => '966506789012'],
        ];

        foreach ($photographers as $index => $data) {
            $email = 'photographer'.($index + 1).'@example.com';

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('Photographer@123'),
                    'phone' => $data['whatsapp'],
                    'role' => UserRole::Photographer,
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                ],
            );

            $profile = PhotographerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'slug' => Str::slug($data['name']),
                    'headline' => $data['headline'],
                    'bio' => 'مصوّر محترف متخصص في '.$data['headline'].'. نخدم المناسبات والأفراد في '.$data['city'].' وما حولها، مع تسليم سريع وتجربة مريحة.',
                    'city' => $data['city'],
                    'country' => 'SA',
                    'phone' => $data['whatsapp'],
                    'whatsapp' => $data['whatsapp'],
                    'website' => 'https://example.com',
                    'years_experience' => 3 + $index,
                    'rating_avg' => 4.2 + ($index % 3) * 0.2,
                    'rating_count' => 20 + $index * 17,
                    'is_public' => true,
                    'is_featured' => $data['featured'],
                    'landing_template' => LandingTemplate::Classic,
                    'theme' => [
                        'primary' => '#1f2937',
                        'accent' => ['#f59e0b', '#0ea5e9', '#ec4899', '#10b981'][$index % 4],
                    ],
                    'published_at' => now(),
                ],
            );

            Subscription::query()->updateOrCreate(
                ['photographer_id' => $user->id, 'plan_id' => $plan->id],
                [
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => now()->subMonth(),
                    'ends_at' => now()->addYear(),
                    'storage_limit_bytes' => $plan?->storage_limit_bytes ?? 0,
                    'activated_at' => now()->subMonth(),
                ],
            );

            $this->seedPackages($user, $data['headline'], $index);
            $this->seedWorkingHours($user);
            $this->seedPortfolio($user, $index);
            $this->seedBookings($user, $index);
            $this->seedVisits($user, $index);
        }
    }

    private function seedPackages(User $user, string $headline, int $offset): void
    {
        $packages = [
            ['name' => 'جلسة '.$headline, 'price' => 450, 'duration_minutes' => 60, 'max_photos' => 60],
            ['name' => 'باقة '.$headline.' المميزة', 'price' => 1200, 'duration_minutes' => 120, 'max_photos' => 150],
            ['name' => 'تغطية يوم كامل', 'price' => 2500, 'duration_minutes' => 240, 'max_photos' => 300],
        ];

        // Every photographer is priced differently so the demo reads like a real
        // marketplace instead of a catalogue where everyone charges the same.
        $factor = 0.75 + ($offset * 0.15);

        foreach ($packages as $index => $package) {
            $package['price'] = (int) round(round($package['price'] * $factor, -1));

            Package::query()->updateOrCreate(
                ['photographer_id' => $user->id, 'slug' => Str::slug($package['name'])],
                $package + [
                    'description' => 'خدمة '.$headline.' مع تعديلات لا محدودة وتسليم الألبوم الرقمي خلال 48 ساعة.',
                    'currency' => 'SAR',
                    'deliverables' => ['صور عالية الدقة', 'ألبوم رقمي', 'تحرير ألوان'],
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    private function seedWorkingHours(User $user): void
    {
        $user->workingHours()->delete();

        // Sunday through Thursday, 4 PM to 10 PM.
        foreach ([0, 1, 2, 3, 4] as $weekday) {
            WorkingHour::query()->create([
                'photographer_id' => $user->id,
                'weekday' => $weekday,
                'start_time' => '16:00:00',
                'end_time' => '22:00:00',
                'slot_interval_minutes' => 60,
                'is_active' => true,
            ]);
        }
    }

    private function seedPortfolio(User $user, int $offset): void
    {
        if ($user->portfolioImages()->exists()) {
            return;
        }

        foreach (array_slice(self::SAMPLE_IMAGES, 0, 4) as $index => $url) {
            PortfolioImage::query()->create([
                'photographer_id' => $user->id,
                'disk' => config('media.public_disk'),
                'path' => $url,
                'thumbnail_path' => $url,
                'original_name' => 'sample-'.$index.'.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => 1_200_000,
                'width' => 1200,
                'height' => 800,
                'sort_order' => $index,
                'is_visible' => true,
            ]);
        }
    }

    private function seedBookings(User $user, int $offset): void
    {
        if ($user->bookings()->exists()) {
            return;
        }

        $statuses = [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::Cancelled];
        $names = ['خالد الزهراني', 'منى السبيعي', 'عبدالله المطيري', 'لمى الغامدي', 'فهد العنزي'];

        foreach ($statuses as $index => $status) {
            $start = now()->addDays($index + $offset)->setTime(18, 0);

            $booking = Booking::query()->create([
                'reference' => Booking::generateReference(),
                'photographer_id' => $user->id,
                'customer_name' => $names[$index],
                'customer_email' => 'customer'.$index.'@example.com',
                'customer_phone' => '+96650000000'.$index,
                'start_at' => $start,
                'end_at' => $start->copy()->addHour(),
                'status' => $status,
                'source' => BookingSource::Website,
                'notes' => 'أرغب في تصوير داخل الاستوديو.',
                'currency' => 'SAR',
            ]);

            // Spread the creation dates across the last month so the dashboard
            // chart shows a real trend instead of every booking landing today.
            $booking->forceFill([
                'created_at' => now()->subDays(max(0, 27 - ($index * 7) + ($offset % 5))),
            ])->save();
        }
    }

    /**
     * Seeds page views so the dashboards have something to chart.
     *
     * A slow wave plus a per-photographer offset keeps the demo data looking
     * organic while staying reproducible on every seed run.
     */
    private function seedVisits(User $user, int $offset): void
    {
        if (SiteVisit::query()->where('photographer_id', $user->id)->exists()) {
            return;
        }

        $slug = $user->profile()->value('slug') ?? 'demo';
        $path = '/photographers/'.$slug;

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $visits = 2 + ((($daysAgo * 7) + ($offset * 5)) % 8);

            for ($index = 0; $index < $visits; $index++) {
                $visitedAt = now()
                    ->subDays($daysAgo)
                    ->setTime(9 + (($index + $offset) % 12), ($index * 7) % 60);

                // Never write a visit into the future when seeding early in the day.
                if ($visitedAt->isFuture()) {
                    $visitedAt = now()->subMinutes(15 + $index);
                }

                SiteVisit::query()->create([
                    'visitor_hash' => hash('sha256', 'visitor-'.$offset.'-'.$daysAgo.'-'.$index),
                    'session_hash' => hash('sha256', 'session-'.$offset.'-'.$daysAgo.'-'.$index),
                    'photographer_id' => $user->id,
                    'path' => $path,
                    'visited_at' => $visitedAt,
                ]);
            }
        }
    }
}

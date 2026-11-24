<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'الباقة الأساسية',
                'slug' => 'basic',
                'description' => 'مناسبة للمصورين الجدد who يبدأون رحلتهم.',
                'price' => 149,
                'currency' => 'SAR',
                'storage_limit_bytes' => 5 * 1024 ** 3,
                'max_galleries' => 10,
                'max_clients' => 30,
                'max_bookings_per_month' => 30,
                'features' => ['صفحة شخصية', 'حجز المواعيد', 'معرض أعمال', 'دعم عبر واتساب'],
                'sort_order' => 1,
            ],
            [
                'name' => 'الباقة الاحترافية',
                'slug' => 'professional',
                'description' => 'للمصورين الذين يديرون عدداً أكبر من العملاء والمشاريع.',
                'price' => 349,
                'currency' => 'SAR',
                'storage_limit_bytes' => 25 * 1024 ** 3,
                'max_galleries' => 50,
                'max_clients' => 150,
                'max_bookings_per_month' => 120,
                'features' => ['كل مزايا الأساسية', 'ألبومات عملاء غير محدودة', 'فواتير', 'تصميم ألوان الصفحة'],
                'sort_order' => 2,
            ],
            [
                'name' => 'باقة الأعمال',
                'slug' => 'business',
                'description' => 'مساحة تخزين كبيرة وصلاحيات متعددة للمؤسسات.',
                'price' => 799,
                'currency' => 'SAR',
                'storage_limit_bytes' => 100 * 1024 ** 3,
                'max_galleries' => 250,
                'max_clients' => 1000,
                'max_bookings_per_month' => null,
                'features' => ['كل مزايا الاحترافية', '100 جيجابايت تخزين', 'أعضاء فريق', 'تقارير متقدمة'],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan + ['is_active' => true]);
        }
    }
}

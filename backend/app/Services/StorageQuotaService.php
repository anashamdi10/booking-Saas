<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use RuntimeException;

/**
 * Enforces the storage quota granted by the photographer's active subscription.
 */
class StorageQuotaService
{
    /**
     * @return array{limit: int, used: int, remaining: int, unlimited: bool}
     */
    public function usageFor(User $photographer): array
    {
        $subscription = $this->activeSubscriptionFor($photographer);

        $limit = (int) ($subscription?->storage_limit_bytes ?? 0);
        $used = (int) $photographer->portfolioImages()->sum('size_bytes');

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'unlimited' => $limit === 0 && $subscription !== null,
        ];
    }

    public function activeSubscriptionFor(User $photographer): ?Subscription
    {
        return $photographer->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('activated_at')
            ->first();
    }

    /**
     * @throws RuntimeException when the photographer has no usable quota.
     */
    public function assertCanUpload(User $photographer, int $incomingBytes): void
    {
        $usage = $this->usageFor($photographer);

        if ($usage['limit'] === 0) {
            throw new RuntimeException('لا يوجد اشتراك فعّال يتيح رفع الصور حالياً.');
        }

        if ($usage['limit'] < $usage['used'] + $incomingBytes) {
            throw new RuntimeException('المساحة المتاحة في اشتراكك غير كافية لرفع هذه الملفات.');
        }
    }
}

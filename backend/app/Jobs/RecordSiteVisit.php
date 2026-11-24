<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PhotographerProfile;
use App\Models\SiteVisit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Persists a single visit, storing only hashed identifiers for privacy.
 */
class RecordSiteVisit implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $path,
        public readonly ?string $referer = null,
        public readonly ?string $photographerSlug = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $sessionId = null,
    ) {}

    public function handle(): void
    {
        $salt = (string) config('app.key');
        $ip = $this->ipAddress ?? '0.0.0.0';
        $userAgent = $this->userAgent ?? 'unknown';

        $visitorHash = hash('sha256', $salt.'|'.$ip.'|'.$userAgent);
        $sessionHash = hash('sha256', $salt.'|'.$ip.'|'.($this->sessionId ?? ''));

        $photographerId = null;

        if ($this->photographerSlug !== null) {
            $photographerId = PhotographerProfile::query()
                ->where('slug', $this->photographerSlug)
                ->value('user_id');
        }

        SiteVisit::query()->create([
            'visitor_hash' => $visitorHash,
            'session_hash' => $sessionHash,
            'photographer_id' => $photographerId,
            'path' => mb_substr($this->path, 0, 512),
            'referer' => $this->referer !== null ? mb_substr($this->referer, 0, 512) : null,
            'ip_hash' => hash('sha256', $salt.'|'.$ip),
            'user_agent_hash' => hash('sha256', $salt.'|'.$userAgent),
            'visited_at' => now(),
        ]);
    }
}

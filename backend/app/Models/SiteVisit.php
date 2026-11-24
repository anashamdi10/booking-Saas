<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisit extends Model
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasFactory;

    protected $fillable = [
        'visitor_hash',
        'session_hash',
        'photographer_id',
        'path',
        'referer',
        'ip_hash',
        'user_agent_hash',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }
}

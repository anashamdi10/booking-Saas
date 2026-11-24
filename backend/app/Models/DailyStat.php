<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DailyStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyStat extends Model
{
    /** @use HasFactory<DailyStatFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'photographer_id',
        'visits',
        'unique_visitors',
        'page_views',
        'bookings_count',
        'revenue',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'visits' => 'integer',
            'unique_visitors' => 'integer',
            'page_views' => 'integer',
            'bookings_count' => 'integer',
            'revenue' => 'decimal:2',
        ];
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }
}

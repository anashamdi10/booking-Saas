<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'photographer_id',
        'package_id',
        'client_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'start_at',
        'end_at',
        'status',
        'source',
        'notes',
        'total_price',
        'currency',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_price' => 'decimal:2',
        ];
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Bookings that still block the time slot.
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
            BookingStatus::Completed->value,
        ]);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
        ]);
    }

    public static function generateReference(): string
    {
        return 'BK-'.Str::upper(Str::random(8));
    }
}

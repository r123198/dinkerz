<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'facility_id', 'name', 'type', 'price_per_slot', 'deposit_per_slot',
    'opens_at', 'closes_at', 'slot_minutes', 'buffer_minutes',
    'booking_window_days', 'capacity', 'archived_at',
])]
class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory, StoresDatesInUtc;

    /**
     * Mirrors the database column default (doubles).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'capacity' => 4,
    ];

    protected function casts(): array
    {
        return [
            'price_per_slot' => 'integer',
            'deposit_per_slot' => 'integer',
            'slot_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'booking_window_days' => 'integer',
            'capacity' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ResourceBlock::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Amount collected online to confirm a booking. When no deposit is
     * configured, the full slot price is charged online (full payment).
     */
    public function depositAmount(): int
    {
        return $this->deposit_per_slot > 0 ? $this->deposit_per_slot : $this->price_per_slot;
    }

    /**
     * Whether part of the slot price is settled on-site rather than online.
     */
    public function requiresOnSiteBalance(): bool
    {
        return $this->depositAmount() < $this->price_per_slot;
    }

    /**
     * How many courts a party of the given size would need so everyone gets
     * game time. A suggestion only — players are never forced to book more.
     */
    public function suggestedCourtsFor(int $partySize): int
    {
        $capacity = max(1, $this->capacity);

        return (int) max(1, (int) ceil($partySize / $capacity));
    }

    /**
     * Resolve the opening and closing instants for one operating session that
     * starts on the given day. Courts whose closing time is at or before their
     * opening time run past midnight, so the closing instant rolls over to the
     * next calendar day (e.g. 18:00–04:00 closes at 4 AM the following day).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function operatingWindowFor(CarbonImmutable $day): array
    {
        $opens = $day->setTimeFromTimeString($this->opens_at);
        $closes = $day->setTimeFromTimeString($this->closes_at);

        if ($closes->lessThanOrEqualTo($opens)) {
            $closes = $closes->addDay();
        }

        return [$opens, $closes];
    }

    /**
     * Total minutes the court is open in a single session, accounting for
     * sessions that run past midnight.
     */
    public function operatingMinutesPerDay(): int
    {
        $opens = CarbonImmutable::parse($this->opens_at);
        $closes = CarbonImmutable::parse($this->closes_at);

        if ($closes->lessThanOrEqualTo($opens)) {
            $closes = $closes->addDay();
        }

        return (int) round($opens->diffInMinutes($closes, true));
    }
}

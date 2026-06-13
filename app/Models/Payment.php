<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\StoresDatesInUtc;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'tenant_id', 'booking_id', 'provider', 'provider_id', 'status',
    'amount', 'currency', 'checkout_url', 'meta', 'paid_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, StoresDatesInUtc;

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Bookings this payment settles — one, or all slots booked together in a
     * group checkout. Resolved from the representative booking's group.
     *
     * @return Collection<int, Booking>
     */
    public function relatedBookings(): Collection
    {
        $booking = $this->booking;

        if ($booking === null) {
            return collect();
        }

        return $booking->group_id
            ? Booking::query()->where('group_id', $booking->group_id)->get()
            : collect([$booking]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }
}

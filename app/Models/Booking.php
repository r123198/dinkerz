<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\StoresDatesInUtc;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id', 'resource_id', 'user_id', 'group_id', 'guest_name', 'guest_email', 'party_size',
    'starts_at', 'ends_at', 'status', 'amount', 'deposit_amount', 'currency',
    'expires_at', 'cancelled_at', 'reminder_sent_at',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, StoresDatesInUtc;

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->reference ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'amount' => 'integer',
            'deposit_amount' => 'integer',
            'party_size' => 'integer',
        ];
    }

    /**
     * Amount collected online to confirm the booking. Falls back to the full
     * amount (full payment) when no deposit was recorded.
     */
    public function depositAmount(): int
    {
        return $this->deposit_amount ?? $this->amount;
    }

    /**
     * Remaining balance the player settles on-site, if any.
     */
    public function balanceDue(): int
    {
        return max(0, $this->amount - $this->depositAmount());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Bookings that currently occupy court inventory.
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', BookingStatus::blocking());
    }

    public function scopeOverlapping(Builder $query, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt): Builder
    {
        return $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }

    public function playerName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Guest';
    }

    public function playerEmail(): ?string
    {
        return $this->user?->email ?? $this->guest_email;
    }

    /**
     * Apply a guarded state machine transition and record the event.
     *
     * @throws \DomainException when the transition is not allowed
     */
    public function transitionTo(BookingStatus $target, ?User $actor = null, array $metadata = []): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new \DomainException(
                "Booking {$this->reference} cannot transition from {$this->status->value} to {$target->value}."
            );
        }

        $from = $this->status;
        $this->status = $target;

        if ($target === BookingStatus::Cancelled) {
            $this->cancelled_at = now();
        }

        $this->save();

        $this->events()->create([
            'from_status' => $from->value,
            'to_status' => $target->value,
            'actor_id' => $actor?->id,
            'metadata' => $metadata ?: null,
        ]);
    }
}

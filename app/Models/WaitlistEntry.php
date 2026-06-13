<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use App\Models\Concerns\StoresDatesInUtc;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id', 'resource_id', 'user_id', 'guest_name', 'guest_email',
    'starts_at', 'ends_at', 'status', 'notified_at',
])]
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory, StoresDatesInUtc;

    protected static function booted(): void
    {
        static::creating(function (WaitlistEntry $entry) {
            $entry->token ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => WaitlistStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
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

    public function playerName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Guest';
    }

    public function email(): string
    {
        return $this->user?->email ?? $this->guest_email;
    }
}

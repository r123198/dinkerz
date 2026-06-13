<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'status', 'logo_path', 'primary_color', 'timezone', 'currency'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function acceptsBookings(): bool
    {
        return $this->status->acceptsBookings();
    }
}

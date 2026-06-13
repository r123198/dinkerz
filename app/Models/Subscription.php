<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\StoresDatesInUtc;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'plan', 'status', 'trial_ends_at', 'current_period_ends_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, StoresDatesInUtc;

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

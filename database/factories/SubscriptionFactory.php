<?php

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan' => SubscriptionPlan::Starter,
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
        ];
    }

    public function active(SubscriptionPlan $plan = SubscriptionPlan::Growth): static
    {
        return $this->state(fn () => [
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_ends_at' => now()->addMonth(),
        ]);
    }
}

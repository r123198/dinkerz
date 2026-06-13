<?php

namespace Database\Factories;

use App\Enums\WaitlistStatus;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'tenant_id' => Tenant::factory(),
            'resource_id' => Resource::factory(),
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'status' => WaitlistStatus::Waiting,
        ];
    }
}

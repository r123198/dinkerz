<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Resource;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<resource>
 */
class ResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'facility_id' => Facility::factory(),
            'name' => 'Court '.fake()->unique()->numberBetween(1, 500),
            'type' => 'pickleball_court',
            'price_per_slot' => 50000, // ₱500.00 in centavos
            'opens_at' => '06:00',
            'closes_at' => '22:00',
            'slot_minutes' => 60,
            'buffer_minutes' => 0,
            'booking_window_days' => 30,
            'capacity' => 4,
            'archived_at' => null,
        ];
    }

    public function configure(): static
    {
        // Keep the facility on the same tenant as the resource.
        return $this->afterMaking(function (Resource $resource) {
            if ($resource->facility && $resource->facility->tenant_id !== $resource->tenant_id) {
                $resource->facility->tenant_id = $resource->tenant_id;
                $resource->facility->save();
            }
        });
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }

    public function withBuffer(int $minutes = 15): static
    {
        return $this->state(fn () => ['buffer_minutes' => $minutes]);
    }
}

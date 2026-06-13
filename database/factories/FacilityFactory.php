<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company().' Sports Center',
            'address' => fake()->address(),
            'timezone' => 'Asia/Manila',
        ];
    }
}

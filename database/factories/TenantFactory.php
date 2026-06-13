<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => TenantStatus::Active,
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}

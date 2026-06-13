<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceBlock>
 */
class ResourceBlockFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'tenant_id' => Tenant::factory(),
            'resource_id' => Resource::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'reason' => 'Maintenance',
        ];
    }
}

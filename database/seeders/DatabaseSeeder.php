<?php

namespace Database\Seeders;

use App\Enums\SubscriptionPlan;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a demo tenant with courts, users, and bookings.
     *
     * Safe to re-run: existing demo data is left untouched.
     */
    public function run(): void
    {
        if (User::query()->where('email', 'admin@courtos.test')->doesntExist()) {
            User::factory()->superAdmin()->create([
                'name' => 'Platform Admin',
                'email' => 'admin@courtos.test',
            ]);
        }

        if (Tenant::query()->where('slug', 'ace')->exists()) {
            $this->command?->warn('Demo tenant "ace" already seeded — skipping.');

            return;
        }

        $tenant = Tenant::factory()->create([
            'name' => 'Ace Pickleball Club',
            'slug' => 'ace',
            'primary_color' => '#16a34a',
        ]);

        Subscription::factory()->active(SubscriptionPlan::Growth)->create([
            'tenant_id' => $tenant->id,
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Operator,
            'name' => 'Demo Operator',
            'email' => 'operator@ace.test',
        ]);

        $facility = Facility::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ace Pickleball Club — Main',
        ]);

        $courts = collect([
            ['name' => 'Court 1', 'buffer_minutes' => 0],
            ['name' => 'Court 2', 'buffer_minutes' => 0],
            // Court 3 runs late and takes a ₱200 deposit; balance paid on-site.
            ['name' => 'Court 3', 'buffer_minutes' => 15, 'closes_at' => '02:00', 'deposit_per_slot' => 20000],
            ['name' => 'Court 4', 'buffer_minutes' => 0],
        ])->map(fn (array $attributes) => Resource::factory()->create([
            'tenant_id' => $tenant->id,
            'facility_id' => $facility->id,
            ...$attributes,
        ]));

        $players = User::factory(5)->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Player,
        ]);

        // A spread of past and upcoming bookings for dashboard data.
        foreach ($courts as $index => $court) {
            Booking::factory()->completed()->create([
                'tenant_id' => $tenant->id,
                'resource_id' => $court->id,
                'user_id' => $players[$index % $players->count()]->id,
                'guest_name' => null,
                'guest_email' => null,
                'starts_at' => now()->subDays($index + 1)->setTime(9 + $index, 0),
                'ends_at' => now()->subDays($index + 1)->setTime(10 + $index, 0),
            ]);

            Booking::factory()->create([
                'tenant_id' => $tenant->id,
                'resource_id' => $court->id,
                'starts_at' => now()->addDays($index + 1)->setTime(17, 0),
                'ends_at' => now()->addDays($index + 1)->setTime(18, 0),
            ]);
        }
    }
}

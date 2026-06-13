<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->setTime(fake()->numberBetween(8, 18), 0);

        return [
            'tenant_id' => Tenant::factory(),
            'resource_id' => Resource::factory(),
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'party_size' => null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'status' => BookingStatus::Confirmed,
            'amount' => 50000,
            'deposit_amount' => 50000,
            'currency' => 'PHP',
        ];
    }

    public function withDeposit(int $deposit = 20000): static
    {
        return $this->state(fn (array $attributes) => [
            'deposit_amount' => $deposit,
        ]);
    }

    public function pendingPayment(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::PendingPayment,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function completed(): static
    {
        $start = now()->subDay()->setTime(10, 0);

        return $this->state(fn () => [
            'status' => BookingStatus::Completed,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
        ]);
    }
}

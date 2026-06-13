<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'booking_id' => Booking::factory(),
            'provider' => 'fake',
            'provider_id' => 'cs_'.Str::random(24),
            'status' => PaymentStatus::Pending,
            'amount' => 50000,
            'currency' => 'PHP',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}

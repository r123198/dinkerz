<?php

namespace App\Services\Payments;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

class PaymentManager
{
    public function __construct(protected Application $app) {}

    public function driver(?string $name = null): PaymentProvider
    {
        $name ??= (string) config('courtos.payment_provider');

        return match ($name) {
            'fake' => $this->app->make(FakePaymentProvider::class),
            'paymongo' => $this->app->make(PayMongoProvider::class),
            default => throw new InvalidArgumentException("Unsupported payment provider [{$name}]."),
        };
    }
}

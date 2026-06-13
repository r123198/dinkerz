<?php

namespace App\Http\Controllers;

use App\Exceptions\WebhookVerificationException;
use App\Services\Payments\PaymentManager;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        PaymentManager $manager,
        PaymentService $payments,
    ): JsonResponse {
        try {
            $driver = $manager->driver($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        try {
            $event = $driver->verifyWebhook($request);
        } catch (WebhookVerificationException) {
            abort(403, 'Invalid webhook signature.');
        }

        $payments->process($event);

        return response()->json(['received' => true]);
    }
}

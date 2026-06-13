<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Payments\WebhookEvent;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * In-app checkout used by the "fake" payment provider for local
 * development and demos. Real providers redirect to hosted checkouts.
 */
class PaymentSimulatorController extends Controller
{
    public function show(Tenant $tenant, Payment $payment): Response|RedirectResponse
    {
        $this->ensureSimulatable($tenant, $payment);

        $booking = $payment->booking;

        // Once paid, the checkout is spent — never let a player re-enter it.
        if ($payment->status !== PaymentStatus::Pending) {
            return redirect()->route('portal.booked', [
                'tenant' => $tenant->slug,
                'reference' => $booking->reference,
            ]);
        }

        $related = $payment->relatedBookings();
        $timezone = $booking->resource->facility->timezone;
        $balanceDue = $related->sum(fn ($b) => $b->balanceDue());

        return Inertia::render('portal/PaySimulator', [
            'tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug, 'color' => $tenant->primary_color],
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount / 100,
                'balance_due' => $balanceDue / 100,
                'court' => $related->count() > 1
                    ? $related->count().' courts'
                    : $booking->resource->name,
                'starts_at' => $related->count() > 1
                    ? null
                    : $booking->starts_at->setTimezone($timezone)->format('l, F j · g:i A'),
                'reference' => $booking->reference,
            ],
        ]);
    }

    public function complete(Request $request, Tenant $tenant, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $this->ensureSimulatable($tenant, $payment);

        $validated = $request->validate([
            'outcome' => ['required', 'in:paid,failed'],
        ]);

        // Mirrors a verified provider webhook landing on the webhook endpoint.
        $payments->process(new WebhookEvent(
            provider: 'fake',
            eventId: 'evt_sim_'.Str::random(20),
            type: 'payment.'.$validated['outcome'],
            providerPaymentId: $payment->provider_id,
            payload: ['simulated' => true],
        ));

        return redirect()->route('portal.booked', [
            'tenant' => $tenant->slug,
            'reference' => $payment->booking->reference,
        ]);
    }

    private function ensureSimulatable(Tenant $tenant, Payment $payment): void
    {
        abort_unless($payment->tenant_id === $tenant->id, 404);
        abort_unless($payment->provider === 'fake', 404);
        abort_if(app()->isProduction(), 404);
    }
}

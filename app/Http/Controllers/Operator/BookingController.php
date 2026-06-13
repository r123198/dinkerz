<?php

namespace App\Http\Controllers\Operator;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $timezone = $tenant->timezone;

        $bookings = $tenant->bookings()
            ->with(['resource:id,name', 'user:id,name,email'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query
                ->where('status', $request->string('status')))
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Booking $booking) => [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'court' => $booking->resource->name,
                'player_name' => $booking->playerName(),
                'player_email' => $booking->playerEmail(),
                'party_size' => $booking->party_size,
                'starts_at' => $booking->starts_at->setTimezone($timezone)->format('D, M j · g:i A'),
                'status' => $booking->status->value,
                'amount' => $booking->amount / 100,
                'balance_due' => $booking->balanceDue() / 100,
                'cancellable' => $booking->status === BookingStatus::Confirmed,
            ]);

        return Inertia::render('operator/Bookings', [
            'bookings' => $bookings,
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        abort_unless($booking->tenant_id === $request->user()->tenant_id, 404);

        $bookings->cancel($booking, $request->user(), ['source' => 'operator']);

        return back()->with('success', 'Booking cancelled. The waitlist has been notified.');
    }
}

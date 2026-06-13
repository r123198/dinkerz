<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourtRequest;
use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourtController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('operator/Courts', [
            'courts' => $tenant->resources()
                ->active()
                ->withCount(['bookings as upcoming_bookings_count' => fn ($query) => $query
                    ->blocking()
                    ->where('starts_at', '>=', now()),
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Resource $court) => $this->presentCourt($court)),
            'courtLimit' => $tenant->subscription?->plan->courtLimit(),
        ]);
    }

    public function store(StoreCourtRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $limit = $tenant->subscription?->plan->courtLimit();

        if ($limit !== null && $tenant->resources()->active()->count() >= $limit) {
            return back()->withErrors([
                'name' => "Your plan allows up to {$limit} courts. Upgrade to add more.",
            ]);
        }

        $facility = $tenant->facilities()->firstOrCreate(['name' => $tenant->name]);

        $tenant->resources()->create([
            ...$request->courtAttributes(),
            'facility_id' => $facility->id,
        ]);

        return back()->with('success', 'Court created.');
    }

    public function update(StoreCourtRequest $request, Resource $court): RedirectResponse
    {
        abort_unless($court->tenant_id === $request->user()->tenant_id, 404);

        $court->update($request->courtAttributes());

        return back()->with('success', 'Court updated.');
    }

    public function destroy(Request $request, Resource $court): RedirectResponse
    {
        abort_unless($court->tenant_id === $request->user()->tenant_id, 404);

        $court->update(['archived_at' => now()]);

        return back()->with('success', 'Court archived.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCourt(Resource $court): array
    {
        return [
            'id' => $court->id,
            'name' => $court->name,
            'price' => $court->price_per_slot / 100,
            'deposit' => $court->deposit_per_slot ? $court->deposit_per_slot / 100 : null,
            'opens_at' => substr($court->opens_at, 0, 5),
            'closes_at' => substr($court->closes_at, 0, 5),
            'slot_minutes' => $court->slot_minutes,
            'buffer_minutes' => $court->buffer_minutes,
            'booking_window_days' => $court->booking_window_days,
            'capacity' => $court->capacity,
            'upcoming_bookings_count' => $court->upcoming_bookings_count ?? 0,
        ];
    }
}

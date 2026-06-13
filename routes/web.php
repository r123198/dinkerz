<?php

use App\Enums\TenantStatus;
use App\Http\Controllers\Operator\BookingController;
use App\Http\Controllers\Operator\CourtController;
use App\Http\Controllers\Operator\DashboardController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Portal\PaymentSimulatorController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Middleware\EnsureUserCanManageFacility;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Inertia\Inertia::render('Welcome', [
        'clubs' => Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('name')
            ->get(['name', 'slug', 'primary_color'])
            ->map(fn ($tenant) => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'color' => $tenant->primary_color,
            ]),
    ]);
})->name('home');

Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)
    ->name('webhooks.payments');

Route::middleware(['auth', 'verified', EnsureUserCanManageFacility::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('courts', [CourtController::class, 'index'])->name('courts.index');
    Route::post('courts', [CourtController::class, 'store'])->name('courts.store');
    Route::put('courts/{court}', [CourtController::class, 'update'])->name('courts.update');
    Route::delete('courts/{court}', [CourtController::class, 'destroy'])->name('courts.destroy');

    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
});

require __DIR__.'/settings.php';

// Public, tenant-scoped booking portal. Registered last so app routes win.
Route::prefix('{tenant:slug}')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'show'])->name('home');
    Route::get('book', [PortalController::class, 'show'])->name('book');
    Route::post('book', [PortalController::class, 'store'])
        ->middleware('throttle:20,1')->name('book.store');
    Route::post('waitlist', [PortalController::class, 'joinWaitlist'])
        ->middleware('throttle:20,1')->name('waitlist.join');
    Route::get('cancel', [PortalController::class, 'showCancel'])->name('cancel');
    Route::post('cancel', [PortalController::class, 'cancel'])
        ->middleware('throttle:10,1')->name('cancel.store');
    Route::get('booked/{reference}', [PortalController::class, 'success'])->name('booked');
    Route::get('bookings/{reference}', [PortalController::class, 'confirmation'])
        ->name('bookings.show');
    Route::get('payments/{payment}/simulate', [PaymentSimulatorController::class, 'show'])
        ->name('payments.simulate');
    Route::post('payments/{payment}/simulate', [PaymentSimulatorController::class, 'complete'])
        ->name('payments.complete');
});

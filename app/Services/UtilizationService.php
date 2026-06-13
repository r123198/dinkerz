<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

class UtilizationService
{
    /**
     * Days of history used for utilization and insight metrics.
     */
    private const WINDOW_DAYS = 30;

    /**
     * @return array{
     *     revenue: array{today: int, week: int, month: int},
     *     utilization: array{rate: float, booked_hours: float, available_hours: float, peak_hours: array<int, array{hour: string, bookings: int}>},
     *     insights: array{total_bookings: int, cancellation_rate: float, recovery_rate: float}
     * }
     */
    public function dashboardMetrics(Tenant $tenant): array
    {
        $now = CarbonImmutable::now();
        $sellable = [BookingStatus::Confirmed, BookingStatus::Completed];

        $revenue = fn (CarbonImmutable $from): int => (int) $tenant->bookings()
            ->whereIn('status', $sellable)
            ->whereBetween('starts_at', [$from, $now->endOfDay()])
            ->sum('amount');

        $windowStart = $now->subDays(self::WINDOW_DAYS)->startOfDay();

        $windowBookings = $tenant->bookings()
            ->whereBetween('starts_at', [$windowStart, $now])
            ->get(['status', 'starts_at', 'ends_at']);

        $sold = $windowBookings->filter(fn (Booking $booking) => in_array($booking->status, $sellable, true));

        $bookedHours = round($sold->sum(
            fn (Booking $booking) => $booking->starts_at->diffInMinutes($booking->ends_at) / 60
        ), 1);

        $availableHours = $tenant->resources()->active()->get()
            ->sum(fn (Resource $court) => ($court->operatingMinutesPerDay() / 60) * self::WINDOW_DAYS);

        $timezone = $tenant->timezone;
        $peakHours = $sold
            ->groupBy(fn (Booking $booking) => $booking->starts_at->setTimezone($timezone)->format('H:00'))
            ->map->count()
            ->sortDesc()
            ->take(3)
            ->map(fn (int $count, string $hour) => ['hour' => $hour, 'bookings' => $count])
            ->values()
            ->all();

        $cancelled = $windowBookings->where('status', BookingStatus::Cancelled)->count();
        $decided = $windowBookings->count();

        $waitlistInWindow = $tenant->waitlistEntries()->where('created_at', '>=', $windowStart);
        $converted = (clone $waitlistInWindow)->where('status', WaitlistStatus::Converted)->count();
        $offered = (clone $waitlistInWindow)->whereIn('status', [
            WaitlistStatus::Notified, WaitlistStatus::Converted, WaitlistStatus::Expired,
        ])->count();

        return [
            'revenue' => [
                'today' => $revenue($now->startOfDay()),
                'week' => $revenue($now->startOfWeek()),
                'month' => $revenue($now->startOfMonth()),
            ],
            'utilization' => [
                'rate' => $availableHours > 0 ? round($bookedHours / $availableHours * 100, 1) : 0.0,
                'booked_hours' => $bookedHours,
                'available_hours' => round($availableHours, 1),
                'peak_hours' => $peakHours,
            ],
            'insights' => [
                'total_bookings' => $decided,
                'cancellation_rate' => $decided > 0 ? round($cancelled / $decided * 100, 1) : 0.0,
                'recovery_rate' => $offered > 0 ? round($converted / $offered * 100, 1) : 0.0,
            ],
        ];
    }
}

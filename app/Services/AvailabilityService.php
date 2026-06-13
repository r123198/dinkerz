<?php

namespace App\Services;

use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Generate the sellable time slots for a resource on a given date.
     *
     * Slots are generated in the facility's timezone from the resource's
     * operating hours, slot duration, and buffer. A slot is unavailable when
     * it is in the past, overlaps an inventory-blocking booking, or overlaps
     * an operator block.
     *
     * @param  string  $date  Date in Y-m-d format, interpreted in the facility timezone.
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, available: bool}>
     */
    public function slotsFor(Resource $resource, string $date): Collection
    {
        $timezone = $resource->facility->timezone;
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();

        if ($resource->isArchived() || ! $this->isWithinBookingWindow($resource, $day)) {
            return collect();
        }

        [$opens, $closes] = $resource->operatingWindowFor($day);

        $bookings = $resource->bookings()
            ->blocking()
            ->overlapping($opens->utc(), $closes->utc())
            ->get(['starts_at', 'ends_at']);

        $blocks = $resource->blocks()
            ->where('starts_at', '<', $closes->utc())
            ->where('ends_at', '>', $opens->utc())
            ->get(['starts_at', 'ends_at']);

        $step = $resource->slot_minutes + $resource->buffer_minutes;
        $slots = collect();

        for (
            $start = $opens;
            $start->addMinutes($resource->slot_minutes)->lte($closes);
            $start = $start->addMinutes($step)
        ) {
            $end = $start->addMinutes($resource->slot_minutes);

            $slots->push([
                'starts_at' => $start,
                'ends_at' => $end,
                'available' => $start->isFuture()
                    && ! $this->overlapsAny($bookings, $start, $end)
                    && ! $this->overlapsAny($blocks, $start, $end),
            ]);
        }

        return $slots;
    }

    /**
     * Whether a specific slot exists on the availability grid and is open.
     *
     * A past-midnight slot belongs to the session that opened the previous
     * evening, so both the slot's own date and the day before are checked.
     */
    public function isSlotAvailable(Resource $resource, CarbonImmutable $startsAt): bool
    {
        $clockDate = $startsAt->setTimezone($resource->facility->timezone);

        foreach ([$clockDate, $clockDate->subDay()] as $sessionDay) {
            $matches = $this->slotsFor($resource, $sessionDay->format('Y-m-d'))->contains(
                fn (array $slot) => $slot['starts_at']->equalTo($startsAt) && $slot['available']
            );

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    public function isWithinBookingWindow(Resource $resource, CarbonImmutable $day): bool
    {
        $today = CarbonImmutable::now($day->getTimezone())->startOfDay();

        return $day->betweenIncluded($today, $today->addDays($resource->booking_window_days));
    }

    /**
     * @param  Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $periods
     */
    private function overlapsAny(Collection $periods, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $periods->contains(
            fn ($period) => $period->starts_at->lt($end) && $period->ends_at->gt($start)
        );
    }
}

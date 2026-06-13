<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class ProcessBookingLifecycle extends Command
{
    protected $signature = 'bookings:process-lifecycle';

    protected $description = 'Expire lapsed payment holds and complete finished bookings';

    public function handle(BookingService $bookings): int
    {
        $expired = $bookings->expireOverdue();
        $completed = $bookings->completeFinished();

        $this->info("Expired {$expired} lapsed holds, completed {$completed} finished bookings.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Normalize datetime attributes to UTC before persistence.
 *
 * Eloquent formats datetimes using the instance's own timezone, so a
 * facility-timezone Carbon would otherwise be stored as local wall-clock
 * time and read back as UTC, shifting the instant.
 */
trait StoresDatesInUtc
{
    public function fromDateTime($value)
    {
        if ($value instanceof DateTimeInterface) {
            $value = CarbonImmutable::instance($value)->utc();
        }

        return parent::fromDateTime($value);
    }
}

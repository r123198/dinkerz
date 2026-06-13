<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'provider', 'provider_event_id', 'type', 'payload'])]
class PaymentEvent extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

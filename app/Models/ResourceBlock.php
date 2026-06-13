<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use Database\Factories\ResourceBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'resource_id', 'starts_at', 'ends_at', 'reason'])]
class ResourceBlock extends Model
{
    /** @use HasFactory<ResourceBlockFactory> */
    use HasFactory, StoresDatesInUtc;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}

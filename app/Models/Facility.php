<?php

namespace App\Models;

use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'address', 'timezone'])]
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}

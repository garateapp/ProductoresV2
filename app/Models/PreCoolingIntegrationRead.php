<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreCoolingIntegrationRead extends Model
{
    protected $table = 'pre_cooling_integration_reads';

    protected $fillable = [
        'pre_cooling_load_id',
        'folios_found',
        'folios_missing',
        'is_partial_success',
        'read_at',
    ];

    protected $casts = [
        'folios_found' => 'array',
        'folios_missing' => 'array',
        'is_partial_success' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function preCoolingLoad(): BelongsTo
    {
        return $this->belongsTo(PreCoolingLoad::class, 'pre_cooling_load_id');
    }
}

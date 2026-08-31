<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreCoolingTunelParametro extends Model
{
    protected $fillable = [
        'tunel_id',
        'dimension',
        'valor',
        'orden',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function tunel(): BelongsTo
    {
        return $this->belongsTo(PreCoolingTunel::class, 'tunel_id');
    }
}

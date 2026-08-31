<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreCoolingCamaraParametro extends Model
{
    protected $fillable = [
        'camara_id',
        'dimension',
        'valor',
        'orden',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function camara(): BelongsTo
    {
        return $this->belongsTo(PreCoolingCamara::class, 'camara_id');
    }
}

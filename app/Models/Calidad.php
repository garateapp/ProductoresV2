<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calidad extends Model
{
    protected $fillable = [
        'recepcion_id',
        't_muestra',
        'materia_vegetal',
        'piedras',
        'barro',
        'pedicelo_largo',
        'racimo',
        'esponjas',
        'h_esponjas',
        'llenado_tottes',
        'embalaje',
    ];

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(Recepcion::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(Detalle::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(QualityControlPhoto::class);
    }
}

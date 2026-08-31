<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreCoolingCamara extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'activo',
        'bodega_id',
    ];

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(PreCoolingBodega::class, 'bodega_id');
    }

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function parametros(): HasMany
    {
        return $this->hasMany(PreCoolingCamaraParametro::class, 'camara_id');
    }

    public function parametrosPorDimension(): array
    {
        return $this->parametros
            ->sortBy('orden')
            ->groupBy('dimension')
            ->map(fn ($items) => $items->where('activo', true)->pluck('valor')->values())
            ->toArray();
    }
}

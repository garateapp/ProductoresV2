<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPackaging extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'peso_std',
        'tramo_sag_embalajes',
        'descripcion',
        'altura',
        'cantidad_cajas',
        'multiplicador',
        'metadata',
        'activo',
    ];

    protected $casts = [
        'peso_std' => 'decimal:4',
        'cantidad_cajas' => 'decimal:4',
        'multiplicador' => 'decimal:4',
        'metadata' => 'array',
        'activo' => 'boolean',
    ];

    public function technicalSheets(): HasMany
    {
        return $this->hasMany(InventoryTechnicalSheet::class, 'packaging_id');
    }

    public function productions(): HasMany
    {
        return $this->hasMany(InventoryProduction::class, 'packaging_id');
    }
}

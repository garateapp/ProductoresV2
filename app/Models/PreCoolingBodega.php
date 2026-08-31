<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreCoolingBodega extends Model
{
    protected $table = 'pre_cooling_bodegas';

    protected $fillable = [
        'codigo', 'nombre', 'filas', 'columnas', 'alto_maximo',
        'capacidad', 'tipo_posiciones', 'pos_x', 'pos_y', 'activo',
    ];

    protected $casts = [
        'filas' => 'integer',
        'columnas' => 'integer',
        'alto_maximo' => 'integer',
        'capacidad' => 'integer',
        'pos_x' => 'decimal:2',
        'pos_y' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function tuneles(): HasMany
    {
        return $this->hasMany(PreCoolingTunel::class, 'bodega_id');
    }

    public function camaras(): HasMany
    {
        return $this->hasMany(PreCoolingCamara::class, 'bodega_id');
    }

    public function scopeTuneles($query)
    {
        return $query->where('codigo', 'like', 'TN%');
    }

    public function scopeCamaras($query)
    {
        return $query->where('codigo', 'like', 'CA%');
    }

    public function scopeProductoTerminado($query)
    {
        return $query->where('codigo', 'like', 'PT%');
    }
}

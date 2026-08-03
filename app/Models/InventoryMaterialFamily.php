<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryMaterialFamily extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(InventoryMaterial::class, 'family_id');
    }
}

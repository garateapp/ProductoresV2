<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryUnit extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(InventoryMaterial::class, 'unit_id');
    }
}

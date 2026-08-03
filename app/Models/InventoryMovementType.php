<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryMovementType extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'afecta_stock',
        'requiere_origen',
        'requiere_destino',
        'requiere_motivo',
        'permite_direcciones_mixtas',
    ];

    protected $casts = [
        'afecta_stock' => 'boolean',
        'requiere_origen' => 'boolean',
        'requiere_destino' => 'boolean',
        'requiere_motivo' => 'boolean',
        'permite_direcciones_mixtas' => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'movement_type_id');
    }
}

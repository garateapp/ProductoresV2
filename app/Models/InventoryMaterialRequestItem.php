<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMaterialRequestItem extends Model
{
    protected $fillable = [
        'material_request_id',
        'material_id',
        'cantidad_solicitada',
        'cantidad_entregada',
        'notas',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterialRequest::class, 'material_request_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }
}

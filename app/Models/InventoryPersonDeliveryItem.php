<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPersonDeliveryItem extends Model
{
    protected $fillable = [
        'person_delivery_id',
        'material_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(InventoryPersonDelivery::class, 'person_delivery_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }
}

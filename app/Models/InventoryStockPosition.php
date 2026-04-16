<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockPosition extends Model
{
    protected $hidden = [
        'logistic_unit_key',
        'lot_code_key',
    ];

    protected $fillable = [
        'material_id',
        'location_id',
        'logistic_unit_id',
        'quantity',
        'lot_code',
        'status',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function logisticUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryLogisticUnit::class, 'logistic_unit_id');
    }
}

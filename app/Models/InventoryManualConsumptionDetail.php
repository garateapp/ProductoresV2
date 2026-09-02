<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryManualConsumptionDetail extends Model
{
    protected $table = 'inventory_manual_consumption_details';

    protected $fillable = [
        'manual_consumption_id',
        'material_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
    ];

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(InventoryManualConsumption::class, 'manual_consumption_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }
}

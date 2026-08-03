<?php

namespace App\Models;

use App\Models\InventoryMaterial;
use App\Models\InventoryStockPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReturnItem extends Model
{
    protected $fillable = [
        'return_id',
        'material_id',
        'position_id',
        'cantidad_devuelta',
        'notas',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(InventoryReturn::class, 'return_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(InventoryStockPosition::class, 'position_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStockLocation extends Model
{
    protected $fillable = [
        'location_id',
        'material_id',
        'stock_actual',
        'last_ledger_event_id',
        'last_rebuilt_at',
    ];

    protected $casts = [
        'stock_actual' => 'decimal:4',
        'last_rebuilt_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function stockPositions(): HasMany
    {
        return $this->hasMany(InventoryStockPosition::class, 'location_id', 'location_id');
    }
}

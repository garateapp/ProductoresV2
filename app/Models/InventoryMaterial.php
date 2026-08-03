<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryMaterial extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'family_id',
        'unit_id',
        'service_id',
        'tipo_material',
        'sap_on_hand',
        'sap_avg_price',
        'metadata',
        'stock_minimo',
        'activo',
    ];

    protected $casts = [
        'sap_on_hand' => 'decimal:4',
        'sap_avg_price' => 'decimal:4',
        'stock_minimo' => 'decimal:4',
        'metadata' => 'array',
        'activo' => 'boolean',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterialFamily::class, 'family_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'unit_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function stockLocations(): HasMany
    {
        return $this->hasMany(InventoryStockLocation::class, 'material_id');
    }

    public function stockPositions(): HasMany
    {
        return $this->hasMany(InventoryStockPosition::class, 'material_id');
    }

    public function logisticUnits(): HasMany
    {
        return $this->hasMany(InventoryLogisticUnit::class, 'material_id');
    }

    public function scopeUnderMinimumStock($query)
    {
        return $query->where('activo', true)
            ->whereColumn('stock_minimo', '>', 'sap_on_hand'); // Assuming sap_on_hand is the current stock source
    }
}

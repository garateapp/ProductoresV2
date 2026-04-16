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
        'tipo_material',
        'sap_on_hand',
        'sap_avg_price',
        'metadata',
        'activo',
    ];

    protected $casts = [
        'sap_on_hand' => 'decimal:4',
        'sap_avg_price' => 'decimal:4',
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

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(InventoryWasteRecord::class, 'material_id');
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    use Auditable;

    protected $fillable = [
        'codigo',
        'scan_code',
        'nombre',
        'tipo',
        'parent_id',
        'depth',
        'path_code',
        'es_ubicacion_operable',
        'requiere_confirmacion_scan',
        'sort_order',
        'permite_stock_negativo',
        'metadata',
        'activo',
        'es_bodega_central',
    ];

    protected $casts = [
        'depth' => 'integer',
        'es_ubicacion_operable' => 'boolean',
        'requiere_confirmacion_scan' => 'boolean',
        'sort_order' => 'integer',
        'permite_stock_negativo' => 'boolean',
        'metadata' => 'array',
        'activo' => 'boolean',
        'es_bodega_central' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function originMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'origin_location_id');
    }

    public function destinationMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'destination_location_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStockLocation::class, 'location_id');
    }

    public function stockPositions(): HasMany
    {
        return $this->hasMany(InventoryStockPosition::class, 'location_id');
    }

    public function logisticUnits(): HasMany
    {
        return $this->hasMany(InventoryLogisticUnit::class, 'current_location_id');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inventory_location_user', 'inventory_location_id', 'user_id')
            ->withTimestamps();
    }

    public function detectedWasteRecords(): HasMany
    {
        return $this->hasMany(InventoryWasteRecord::class, 'detected_location_id');
    }

    public function quarantineWasteRecords(): HasMany
    {
        return $this->hasMany(InventoryWasteRecord::class, 'quarantine_location_id');
    }

    public function transferUnitsAsOrigin(): HasMany
    {
        return $this->hasMany(InventoryTransferUnit::class, 'origin_location_id');
    }

    public function transferUnitsAsDestination(): HasMany
    {
        return $this->hasMany(InventoryTransferUnit::class, 'destination_location_id');
    }
}

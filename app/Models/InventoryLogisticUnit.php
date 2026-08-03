<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLogisticUnit extends Model
{
    protected $fillable = [
        'license_plate_number',
        'material_id',
        'current_location_id',
        'spatial_prefix',
        'spatial_column',
        'spatial_row',
        'status',
        'base_quantity',
        'available_quantity',
        'unit_id',
        'lot_code',
        'supplier_lot',
        'production_batch',
        'reference_type',
        'reference_id',
        'received_at',
        'last_moved_at',
        'dispatch_guide',
        'metadata',
        'created_by',
        'closed_at',
    ];

    protected $casts = [
        'base_quantity' => 'decimal:4',
        'available_quantity' => 'decimal:4',
        'received_at' => 'datetime',
        'last_moved_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'current_location_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryMovementAllocation::class, 'logistic_unit_id');
    }

    public function stockPositions(): HasMany
    {
        return $this->hasMany(InventoryStockPosition::class, 'logistic_unit_id');
    }

    public function transferUnits(): HasMany
    {
        return $this->hasMany(InventoryTransferUnit::class, 'logistic_unit_id');
    }

    public function ledgerEvents(): HasMany
    {
        return $this->hasMany(InventoryLedgerEvent::class, 'logistic_unit_id');
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(InventoryWasteRecord::class, 'logistic_unit_id');
    }

    public function normalizedLotCode(): ?string
    {
        $lotCode = trim((string) $this->lot_code);

        return $lotCode === '' ? null : $lotCode;
    }
}

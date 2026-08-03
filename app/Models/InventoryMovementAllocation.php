<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovementAllocation extends Model
{
    protected $fillable = [
        'movement_detail_id',
        'logistic_unit_id',
        'allocated_quantity',
        'from_location_id',
        'to_location_id',
        'allocation_type',
        'metadata',
    ];

    protected $casts = [
        'allocated_quantity' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function movementDetail(): BelongsTo
    {
        return $this->belongsTo(InventoryMovementDetail::class, 'movement_detail_id');
    }

    public function logisticUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryLogisticUnit::class, 'logistic_unit_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }
}

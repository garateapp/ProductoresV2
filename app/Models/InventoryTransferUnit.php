<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferUnit extends Model
{
    protected $fillable = [
        'movement_id',
        'logistic_unit_id',
        'material_id',
        'origin_location_id',
        'destination_location_id',
        'quantity',
        'status',
        'dispatched_by',
        'dispatched_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'received_by',
        'received_at',
        'returned_by',
        'returned_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'dispatched_at' => 'datetime',
        'rejected_at' => 'datetime',
        'received_at' => 'datetime',
        'returned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function logisticUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryLogisticUnit::class, 'logistic_unit_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function returnReceiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}

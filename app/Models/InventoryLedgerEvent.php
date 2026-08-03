<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLedgerEvent extends Model
{
    protected $fillable = [
        'sequence',
        'event_uuid',
        'event_type',
        'correlation_uuid',
        'movement_id',
        'movement_detail_id',
        'allocation_id',
        'material_id',
        'location_id',
        'logistic_unit_id',
        'signed_quantity',
        'stock_effect',
        'previous_hash',
        'event_hash',
        'payload',
        'occurred_at',
        'actor_user_id',
        'actor_name_snapshot',
        'device_code',
        'app_version',
    ];

    protected $casts = [
        'signed_quantity' => 'decimal:4',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function movementDetail(): BelongsTo
    {
        return $this->belongsTo(InventoryMovementDetail::class, 'movement_detail_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(InventoryMovementAllocation::class, 'allocation_id');
    }

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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

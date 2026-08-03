<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryWasteRecord extends Model
{
    protected $fillable = [
        'code',
        'movement_id',
        'movement_detail_id',
        'material_id',
        'logistic_unit_id',
        'detected_location_id',
        'quarantine_location_id',
        'waste_reason_id',
        'waste_type_id',
        'quantity',
        'status',
        'severity',
        'requires_supervisor_review',
        'photo_path',
        'evidence_payload',
        'reported_by',
        'reviewed_by',
        'reported_at',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'requires_supervisor_review' => 'boolean',
        'evidence_payload' => 'array',
        'reported_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function movementDetail(): BelongsTo
    {
        return $this->belongsTo(InventoryMovementDetail::class, 'movement_detail_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function logisticUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryLogisticUnit::class, 'logistic_unit_id');
    }

    public function detectedLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'detected_location_id');
    }

    public function quarantineLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'quarantine_location_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(InventoryWasteReason::class, 'waste_reason_id');
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(InventoryWasteType::class, 'waste_type_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function destructionAct()
    {
        return $this->hasOne(InventoryDestructionAct::class, 'waste_record_id');
    }
}

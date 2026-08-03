<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryMovement extends Model
{
    protected $fillable = [
        'folio',
        'movement_type_id',
        'fecha_movimiento',
        'origin_location_id',
        'destination_location_id',
        'material_request_id',
        'return_id',
        'estado',
        'referencia_tipo',
        'referencia_id',
        'motivo',
        'observacion',
        'created_by',
        'approved_by',
        'confirmed_by',
        'applied_at',
        'confirmed_at',
        'receipt_hash',
        'ledger_hash',
        'ledger_sequence_from',
        'ledger_sequence_to',
        'scan_session_uuid',
        'waste_reason_id',
        'reversal_of_movement_id',
        'requires_photo_evidence',
        'metadata',
    ];

    protected $casts = [
        'fecha_movimiento' => 'datetime',
        'applied_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'requires_photo_evidence' => 'boolean',
        'metadata' => 'array',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(InventoryMovementType::class, 'movement_type_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterialRequest::class, 'material_request_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(InventoryReturn::class, 'return_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InventoryMovementDetail::class, 'movement_id');
    }

    public function ledgerEvents(): HasMany
    {
        return $this->hasMany(InventoryLedgerEvent::class, 'movement_id');
    }

    public function transferUnits(): HasMany
    {
        return $this->hasMany(InventoryTransferUnit::class, 'movement_id');
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(InventoryWasteRecord::class, 'movement_id');
    }

    public function personDelivery(): HasOne
    {
        return $this->hasOne(InventoryPersonDelivery::class, 'movement_id');
    }

    public function wasteReason(): BelongsTo
    {
        return $this->belongsTo(InventoryWasteReason::class, 'waste_reason_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_movement_id');
    }

    public function reversedBy(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_movement_id');
    }
}

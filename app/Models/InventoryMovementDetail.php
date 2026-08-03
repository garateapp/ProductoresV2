<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryMovementDetail extends Model
{
    protected $fillable = [
        'movement_id',
        'material_id',
        'sentido',
        'cantidad',
        'costo_referencial',
        'observacion',
        'metadata',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'costo_referencial' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryMovementAllocation::class, 'movement_detail_id');
    }

    public function ledgerEvents(): HasMany
    {
        return $this->hasMany(InventoryLedgerEvent::class, 'movement_detail_id');
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(InventoryWasteRecord::class, 'movement_detail_id');
    }
}

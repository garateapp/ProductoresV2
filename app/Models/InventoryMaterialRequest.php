<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryMaterialRequest extends Model
{
    use Auditable;

    protected $fillable = [
        'codigo',
        'created_by',
        'origin_location_id',
        'destination_location_id',
        'estado',
        'observacion',
        'fecha_solicitud',
        'fecha_requerida',
        'metadata',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_requerida' => 'datetime',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryMaterialRequestItem::class, 'material_request_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'material_request_id');
    }
}

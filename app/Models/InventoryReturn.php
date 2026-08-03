<?php

namespace App\Models;

use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovement;
use App\Models\InventoryStockPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReturn extends Model
{
    protected $fillable = [
        'codigo',
        'created_by',
        'origin_location_id',
        'destination_location_id',
        'estado',
        'observacion',
        'fecha_solicitud',
        'fecha_requerida',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'fecha_requerida' => 'datetime',
        ];
    }

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
        return $this->hasMany(InventoryReturnItem::class, 'return_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'return_id');
    }
}

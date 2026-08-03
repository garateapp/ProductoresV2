<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPersonDelivery extends Model
{
    use Auditable;

    protected $fillable = [
        'codigo',
        'movement_id',
        'created_by',
        'origin_location_id',
        'person_name',
        'person_position',
        'delivered_at',
        'signature_data_url',
        'notes',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'origin_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryPersonDeliveryItem::class, 'person_delivery_id');
    }
}

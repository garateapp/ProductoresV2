<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryConsumptionOrigin extends Model
{
    protected $table = 'inventory_consumption_origins';

    protected $fillable = [
        'linea',
        'turno',
        'location_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }
}
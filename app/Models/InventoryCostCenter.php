<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCostCenter extends Model
{
    protected $table = 'inventory_cost_centers';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'service_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function personDeliveries(): HasMany
    {
        return $this->hasMany(InventoryPersonDelivery::class, 'cost_center_id');
    }
}

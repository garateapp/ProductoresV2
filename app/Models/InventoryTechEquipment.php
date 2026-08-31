<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTechEquipment extends Model
{
    use Auditable;

    protected $table = 'inventory_tech_equipment';

    protected $fillable = [
        'marca',
        'fecha',
        'numero_serie',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'activo' => 'boolean',
    ];

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(InventoryTechEquipmentDeliveryActItem::class, 'equipment_id');
    }
}

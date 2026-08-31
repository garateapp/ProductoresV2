<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTechEquipmentDeliveryActItem extends Model
{
    protected $table = 'inventory_tech_equipment_delivery_act_items';

    protected $fillable = [
        'delivery_act_id',
        'equipment_id',
    ];

    public function deliveryAct(): BelongsTo
    {
        return $this->belongsTo(InventoryTechEquipmentDeliveryAct::class, 'delivery_act_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(InventoryTechEquipment::class, 'equipment_id');
    }
}

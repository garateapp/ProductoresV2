<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTechEquipmentDeliveryAct extends Model
{
    use Auditable;

    protected $table = 'inventory_tech_equipment_delivery_acts';

    protected $fillable = [
        'codigo',
        'created_by',
        'person_name',
        'person_rut',
        'departamento',
        'cargo',
        'condicion',
        'delivered_at',
        'signature_data_url',
        'observations',
        'returned_at',
        'return_observations',
        'return_signature_data_url',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTechEquipmentDeliveryActItem::class, 'delivery_act_id');
    }
}

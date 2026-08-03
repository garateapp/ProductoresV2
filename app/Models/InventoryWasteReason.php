<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryWasteReason extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(InventoryWasteRecord::class, 'waste_reason_id');
    }
}

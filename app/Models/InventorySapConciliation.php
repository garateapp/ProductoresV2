<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySapConciliation extends Model
{
    protected $fillable = [
        'fecha',
        'material_id',
        'stock_sap',
        'stock_interno',
        'diferencia',
        'observacion',
        'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'stock_sap' => 'decimal:4',
        'stock_interno' => 'decimal:4',
        'diferencia' => 'decimal:4',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

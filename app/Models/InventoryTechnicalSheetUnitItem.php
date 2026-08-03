<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTechnicalSheetUnitItem extends Model
{
    protected $fillable = [
        'technical_sheet_id',
        'material_id',
        'replacement_material_id',
        'cantidad_estandar',
        'calibre',
    ];

    protected $casts = [
        'cantidad_estandar' => 'decimal:6',
    ];

    public function technicalSheet(): BelongsTo
    {
        return $this->belongsTo(InventoryTechnicalSheet::class, 'technical_sheet_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function replacementMaterial(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'replacement_material_id');
    }
}

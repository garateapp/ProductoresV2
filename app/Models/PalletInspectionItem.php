<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalletInspectionItem extends Model
{
    protected $fillable = [
        'pallet_inspection_id',
        'technical_sheet_pallet_item_id',
        'material_utilizado_id',
        'es_reemplazo',
        'cumple',
        'motivo_no_cumple',
    ];

    protected $casts = [
        'cumple' => 'boolean',
        'es_reemplazo' => 'boolean',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(PalletInspection::class, 'pallet_inspection_id');
    }

    public function technicalSheetPalletItem(): BelongsTo
    {
        return $this->belongsTo(InventoryTechnicalSheetPalletItem::class, 'technical_sheet_pallet_item_id');
    }

    public function materialUtilizado(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_utilizado_id');
    }
}

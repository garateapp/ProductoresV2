<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PalletInspection extends Model
{
    protected $fillable = [
        'folio',
        'c_embalaje',
        'technical_sheet_id',
        'user_id',
        'resultado',
        'observaciones_generales',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function technicalSheet(): BelongsTo
    {
        return $this->belongsTo(InventoryTechnicalSheet::class, 'technical_sheet_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PalletInspectionItem::class, 'pallet_inspection_id');
    }
}

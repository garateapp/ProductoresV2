<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTechnicalSheetImage extends Model
{
    protected $fillable = [
        'technical_sheet_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'descripcion',
        'orden',
    ];

    protected $casts = [
        'size' => 'integer',
        'orden' => 'integer',
    ];

    public function technicalSheet(): BelongsTo
    {
        return $this->belongsTo(InventoryTechnicalSheet::class, 'technical_sheet_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLabel extends Model
{
    protected $table = 'inventory_labels';

    protected $fillable = [
        'codigo',
        'nombre',
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

    public function technicalSheets(): HasMany
    {
        return $this->hasMany(InventoryTechnicalSheet::class, 'etiqueta_id');
    }
}

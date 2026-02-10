<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineCapacity extends Model
{
    protected $fillable = [
        'packing_line_id',
        'especie',
        'bins_por_hora',
        'shift_id',
        'vigencia_desde',
        'vigencia_hasta',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'bins_por_hora' => 'decimal:2',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
    ];

    public function packingLine(): BelongsTo
    {
        return $this->belongsTo(PackingLine::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}


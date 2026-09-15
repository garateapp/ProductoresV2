<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetencionMotivoCausa extends Model
{
    protected $table = 'detenciones_motivo_causas';

    protected $fillable = [
        'motivo_tipo_id',
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(DetencionMotivoTipo::class, 'motivo_tipo_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(DetencionRegistro::class, 'motivo_causa_id');
    }
}
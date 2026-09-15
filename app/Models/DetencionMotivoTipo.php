<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetencionMotivoTipo extends Model
{
    protected $table = 'detenciones_motivo_tipos';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function causas(): HasMany
    {
        return $this->hasMany(DetencionMotivoCausa::class, 'motivo_tipo_id');
    }

    public function causasActivas(): HasMany
    {
        return $this->causas()->where('activo', true)->orderBy('codigo');
    }
}
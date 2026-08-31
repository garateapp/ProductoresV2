<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreCoolingTipoProceso extends Model
{
    protected $table = 'pre_cooling_tipos_procesos';

    protected $fillable = [
        'codigo',
        'nombre',
        'tiempo_objetivo_minutos',
        'activo',
    ];

    protected $casts = [
        'tiempo_objetivo_minutos' => 'integer',
        'activo' => 'boolean',
    ];

    public function cargas(): HasMany
    {
        return $this->hasMany(PreCoolingLoad::class, 'tipo_proceso_id');
    }
}

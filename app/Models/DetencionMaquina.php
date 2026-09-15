<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetencionMaquina extends Model
{
    protected $table = 'detenciones_maquinas';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function turnos(): HasMany
    {
        return $this->hasMany(DetencionTurno::class, 'maquina_id');
    }
}
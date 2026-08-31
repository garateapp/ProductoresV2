<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreCoolingAtributo extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'tipo_dato',
        'opciones',
        'requerido',
        'activo',
    ];

    protected $casts = [
        'opciones' => 'array',
        'requerido' => 'boolean',
        'activo' => 'boolean',
    ];
}

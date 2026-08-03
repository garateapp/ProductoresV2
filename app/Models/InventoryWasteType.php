<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryWasteType extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
        'permite_devolucion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'permite_devolucion' => 'boolean',
    ];
}

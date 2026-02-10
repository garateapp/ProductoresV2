<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackagingMatrixRule extends Model
{
    protected $table = 'packaging_matrix_rules';

    protected $fillable = [
        'matrix',
        'especie',
        'destino',
        'nota',
        'variedad',
        'color',
        'require_sdp',
        'c_item',
        'desc_embalaje',
        'peso_caja',
        'allowed_calibres',
        'calibres_note',
        'sobre_calibre_note',
        'priority',
        'activo',
    ];

    protected $casts = [
        'require_sdp' => 'boolean',
        'allowed_calibres' => 'array',
        'priority' => 'integer',
        'activo' => 'boolean',
        'peso_caja' => 'decimal:2',
    ];
}


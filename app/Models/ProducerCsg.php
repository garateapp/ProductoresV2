<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProducerCsg extends Model
{
    protected $fillable = [
        'user_id',
        'idprod',
        'csg_code',
        'sdp',
        'variedad',
        'especie',
        'clasificacion',
        'sdp_validado',
        'sdp_validado_at',
        'predio_name',
        'predio_direccion',
    ];
}

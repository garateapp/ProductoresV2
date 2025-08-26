<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Valor extends Model
{
    use HasFactory;

    protected $table = 'valors';

    protected $appends = ['nombre'];

    // Accessor for 'nombre' to match frontend expectation
    public function getNombreAttribute()
    {
        return $this->attributes['name'];
    }

    public function parametro()
    {
        return $this->belongsTo(Parametro::class);
    }
}

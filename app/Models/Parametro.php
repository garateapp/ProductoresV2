<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    use HasFactory;

    protected $table = 'parametros';

    protected $appends = ['nombre'];

    // Accessor for 'nombre' to match frontend expectation
    public function getNombreAttribute()
    {
        return $this->attributes['name'];
    }

    public function valors()
    {
        return $this->hasMany(Valor::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variedad extends Model
{
    use HasFactory;

    protected $table = 'variedads';

    protected $fillable = [
        'name',
        'especie_id',
    ];

    public function especie()
    {
        return $this->belongsTo(Especie::class, 'especie_id');
    }

    public function markets()
    {
        return $this->belongsToMany(Market::class, 'market_variedad');
    }
}

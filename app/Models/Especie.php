<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Especie extends Model
{
    use HasFactory;

    protected $table = 'especies';

    protected $fillable = [
        'name',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'especie_user', 'especie_id', 'user_id');
    }

    public function variedads()
    {
        return $this->hasMany(Variedad::class, 'especie_id');
    }

    public function certifications()
    {
        return $this->hasMany(ProducerCertification::class);
    }

    public function csgEspecieCountryStatuses()
    {
        return $this->hasMany(CsgEspecieCountryStatus::class, 'especie_id');
    }

    public function markets()
    {
        return $this->belongsToMany(Market::class, 'market_especie');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'continent_id'];

    public function continent()
    {
        return $this->belongsTo(Continent::class);
    }

    public function markets()
    {
        return $this->hasMany(Market::class);
    }

    public function csgEspecieCountryStatuses()
    {
        return $this->hasMany(CsgEspecieCountryStatus::class, 'country_id');
    }
}


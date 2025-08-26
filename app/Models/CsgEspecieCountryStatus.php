<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CsgEspecieCountryStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'especie_id',
        'country_id',
        'status',
        'last_updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function especie()
    {
        return $this->belongsTo(Especie::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
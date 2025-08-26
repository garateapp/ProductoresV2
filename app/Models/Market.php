<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Market extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'treatment_requirements',
        'other_requirements',
        'sampling_level',
        'quarantine_pests',
        'is_active',
        'authorization_type_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function certificateTypes()
    {
        return $this->belongsToMany(CertificateType::class, 'market_certificate_type');
    }

    public function authorizationType()
    {
        return $this->belongsTo(AuthorizationType::class);
    }

    public function especies()
    {
        return $this->belongsToMany(Especie::class, 'market_especie');
    }

    public function variedads()
    {
        return $this->belongsToMany(Variedad::class, 'market_variedad');
    }
}


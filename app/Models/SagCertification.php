<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SagCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'producer_rut', // legacy
        'csg_user_id', // CSG-level ownership
        'name',
        'description',
        'file_path',
        'issue_date',
        'expiration_date',
        'certification_type',
        'especie_id',
        'country_id',
        'is_active',
    ];

    public function csgUser()
    {
        return $this->belongsTo(User::class, 'csg_user_id');
    }

    public function sdps()
    {
        return $this->belongsToMany(\App\Models\SdpSite::class, 'sag_certification_sdp');
    }

    public function especie()
    {
        return $this->belongsTo(\App\Models\Especie::class);
    }

    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class);
    }
}

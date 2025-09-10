<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SdpSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'csg_user_id',
        'code',
        'name',
        'address',
        'is_active',
    ];

    public function csgUser()
    {
        return $this->belongsTo(User::class, 'csg_user_id');
    }

    public function certifications()
    {
        return $this->belongsToMany(SagCertification::class, 'sag_certification_sdp');
    }
}


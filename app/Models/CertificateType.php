<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function certifications()
    {
        return $this->hasMany(ProducerCertification::class);
    }

    public function markets()
    {
        return $this->belongsToMany(Market::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}


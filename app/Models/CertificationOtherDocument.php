<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificationOtherDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'certification_id',
        'file_path',
        'description',
    ];

    public function certification()
    {
        return $this->belongsTo(ProducerCertification::class);
    }
}


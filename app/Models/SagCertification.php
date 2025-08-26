<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SagCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'producer_rut',
        'name',
        'description',
        'file_path',
        'issue_date',
        'expiration_date',
        'certification_type',
    ];

    // Removed public function user()
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'email',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}

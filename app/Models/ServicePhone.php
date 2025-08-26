<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePhone extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'phone',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}

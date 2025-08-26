<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class QualityControlPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'calidad_id',
        'photo_type_id',
        'path',
    ];

    protected $appends = ['url'];

    public function calidad()
    {
        return $this->belongsTo(Calidad::class);
    }

    public function photoType()
    {
        return $this->belongsTo(PhotoType::class);
    }

    public function getUrlAttribute()
    {
        return Storage::url($this->path);
    }
}
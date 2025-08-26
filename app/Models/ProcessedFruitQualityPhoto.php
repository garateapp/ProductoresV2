<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessedFruitQualityPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'processed_fruit_quality_id',
        'photo_type_id',
        'photo_path',
    ];

    public function processedFruitQuality()
    {
        return $this->belongsTo(ProcessedFruitQuality::class);
    }

    public function photoType()
    {
        return $this->belongsTo(PhotoType::class);
    }
}
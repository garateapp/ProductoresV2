<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationSeason extends Model
{
    use HasFactory;

    protected $table = 'estimation_seasons';

    protected $fillable = [
        'code',
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function weeks()
    {
        return $this->hasMany(EstimationWeek::class, 'season_id');
    }

    public function versions()
    {
        return $this->hasMany(EstimationVersion::class, 'season_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationWeek extends Model
{
    use HasFactory;

    protected $table = 'estimation_weeks';

    protected $fillable = [
        'season_id',
        'week_number',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function season()
    {
        return $this->belongsTo(EstimationSeason::class, 'season_id');
    }
}
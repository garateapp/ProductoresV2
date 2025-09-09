<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyHarvestEstimate extends Model
{
    use HasFactory;

    protected $table = 'weekly_harvest_estimates';

    protected $fillable = [
        'user_id',
        'agronomist_id',
        'especie_id',
        'variedad_id',
        'season_code',
        'iso_year',
        'iso_week',
        'week_start_date',
        'week_end_date',
        'predio',
        'block',
        'estimated_kilos',
        'estimated_bins',
        'estimated_boxes',
        'confidence_pct',
        'status',
        'source',
        'notes',
        'acopio',
        'radio_mosca',
        'corea_greenex',
        'tipo_cereza',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'estimated_kilos' => 'decimal:2',
        'estimated_bins' => 'decimal:2',
        'confidence_pct' => 'integer',
        'acopio' => 'boolean',
        'radio_mosca' => 'boolean',
        'corea_greenex' => 'boolean',
    ];

    public function producer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agronomist()
    {
        return $this->belongsTo(User::class, 'agronomist_id');
    }

    public function especie()
    {
        return $this->belongsTo(Especie::class, 'especie_id');
    }

    public function variedad()
    {
        return $this->belongsTo(Variedad::class, 'variedad_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

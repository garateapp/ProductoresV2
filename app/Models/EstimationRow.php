<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationRow extends Model
{
    use HasFactory;

    protected $table = 'estimation_rows';

    protected $fillable = [
        'estimation_version_id',
        'grupo',
        'tipo_productor',
        'producer_id',
        'agronomist_id',
        'status_id',
        'variedad_id',
        'variedad_rotulada',
        'planta',
        'mexico',
        'acopio',
        'radio_mosca',
        'corea_greenex',
        'tipo_cereza',
        'total_kilo',
    ];

    protected $casts = [
        'acopio' => 'boolean',
        'radio_mosca' => 'boolean',
        'corea_greenex' => 'boolean',
        'mexico' => 'boolean',
        'total_kilo' => 'decimal:3',
    ];

    public function version()
    {
        return $this->belongsTo(EstimationVersion::class, 'estimation_version_id');
    }

    public function producer()
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    public function agronomist()
    {
        return $this->belongsTo(User::class, 'agronomist_id');
    }

    public function status()
    {
        return $this->belongsTo(EstimationStatus::class, 'status_id');
    }

    public function variedad()
    {
        return $this->belongsTo(Variedad::class, 'variedad_id');
    }

    public function weekValues()
    {
        return $this->hasMany(EstimationWeekValue::class, 'estimation_row_id');
    }
}

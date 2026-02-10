<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationBiweeklyRow extends Model
{
    use HasFactory;

    protected $table = 'estimation_biweekly_rows';

    protected $fillable = [
        'estimation_biweekly_version_id',
        'producer_id',
        'agronomist_id',
        'variedad_id',
        'planta',
        'sucursal',
        'csg',
        'especie',
        'tipo',
        'acopio',
        'mexico',
        'dia',
        'semana',
        'total_kilo',
    ];

    protected $casts = [
        'acopio' => 'boolean',
        'mexico' => 'boolean',
        'dia' => 'date',
        'semana' => 'integer',
        'total_kilo' => 'decimal:3',
    ];

    public function version()
    {
        return $this->belongsTo(EstimationBiweeklyVersion::class, 'estimation_biweekly_version_id');
    }

    public function producer()
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    public function agronomist()
    {
        return $this->belongsTo(User::class, 'agronomist_id');
    }

    public function variedad()
    {
        return $this->belongsTo(Variedad::class, 'variedad_id');
    }
}

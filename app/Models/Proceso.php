<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proceso extends Model
{
    use HasFactory;

    protected $table = 'procesos';

    protected $fillable = [
        'agricola',
        'n_proceso',
        'especie',
        'variedad',
        'fecha',
        'kilos_netos',
        'id_empresa',
        'informe',
        'exp',
        'comercial',
        'desecho',
        'merma',
        'temporada',
        'c_productor',
    ];

    public function recepcion()
    {
        return $this->belongsTo(Recepcion::class, 'n_proceso', 'numero_g_recepcion'); // Assuming n_proceso links to numero_g_recepcion
    }

    public function processedFruitQualities()
    {
        return $this->hasMany(ProcessedFruitQuality::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'exportadora',
        'informe',
        'informe_uploaded_at',
        'exp',
        'comercial',
        'desecho',
        'merma',
        'temporada',
        'c_productor',
        'LPP_recepcion',
        'lote_recepcion',
        'estado',
    ];

    public function recepcion()
    {
        return $this->belongsTo(Recepcion::class, 'n_proceso', 'numero_g_recepcion'); // Assuming n_proceso links to numero_g_recepcion
    }

    public function processedFruitQualities()
    {
        return $this->hasMany(ProcessedFruitQuality::class);
    }

    public function cuadraturaWorkflow(): HasOne
    {
        return $this->hasOne(ProcesoCuadratura::class, 'proceso_id');
    }

    public function cuadraturaEventos(): HasMany
    {
        return $this->hasMany(ProcesoCuadraturaEvento::class, 'proceso_id');
    }
}

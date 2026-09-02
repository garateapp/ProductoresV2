<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreCoolingLoad extends Model
{
    protected $table = 'pre_cooling_loads';

    protected $fillable = [
        'numero',
        'tipo_proceso_id',
        'tunel_id',
        'camara_destino_id',
        'estado',
        'fecha_hora_inicio',
        'fecha_hora_inversion',
        'fecha_hora_fin',
        'fecha_hora_termino',
        'fecha_hora_descarga',
        'usuario_ingreso_id',
        'usuario_inicio_id',
        'usuario_inversion_id',
        'usuario_fin_id',
        'observaciones',
        'temperatura_objetivo',
        'temperatura_ambiente_inicio',
        'temperatura_ambiente_inversion',
        'temperatura_ambiente_final',
        'atributos',
    ];

    protected $casts = [
        'estado' => 'string',
        'fecha_hora_inicio' => 'datetime',
        'fecha_hora_inversion' => 'datetime',
        'fecha_hora_fin' => 'datetime',
        'fecha_hora_termino' => 'datetime',
        'fecha_hora_descarga' => 'datetime',
        'temperatura_objetivo' => 'decimal:2',
        'temperatura_ambiente_inicio' => 'decimal:2',
        'temperatura_ambiente_inversion' => 'decimal:2',
        'temperatura_ambiente_final' => 'decimal:2',
        'atributos' => 'array',
    ];

    public function tipoProceso(): BelongsTo
    {
        return $this->belongsTo(PreCoolingTipoProceso::class, 'tipo_proceso_id');
    }

    public function tunel(): BelongsTo
    {
        return $this->belongsTo(PreCoolingTunel::class, 'tunel_id');
    }

    public function camaraDestino(): BelongsTo
    {
        return $this->belongsTo(PreCoolingCamara::class, 'camara_destino_id');
    }

    public function folios(): HasMany
    {
        return $this->hasMany(PreCoolingLoadFolio::class, 'load_id');
    }

    public function foliosPendientes(): HasMany
    {
        return $this->folios()->whereNull('fecha_hora_salida');
    }

    public function usuarioIngreso(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_ingreso_id');
    }

    public function usuarioInicio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_inicio_id');
    }

    public function usuarioInversion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_inversion_id');
    }

    public function usuarioFin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_fin_id');
    }

}

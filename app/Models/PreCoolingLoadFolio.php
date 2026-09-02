<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreCoolingLoadFolio extends Model
{
    protected $table = 'pre_cooling_load_folios';

    protected $fillable = [
        'load_id',
        'camara_destino_id',
        'tipo_proceso_id',
        'folio',
        'banda',
        'posicion',
        'altura',
        'nivel',
        'exportadora',
        'productor',
        'especie',
        'variedad',
        'embalaje',
        'categoria',
        'calibre',
        'cajas',
        'pallets',
        'temperatura_inicial',
        'temperatura_inicio',
        'temperatura_inversion_interior',
        'temperatura_inversion_exterior',
        'temperatura_final_interna',
        'temperatura_final_externa',
        'fecha_hora_salida',
        'temperatura_ambiente_tunel_salida',
        'temperatura_ambiente_camara_salida',
        'usuario_salida_id',
        'metadata',
    ];

    protected $casts = [
        'cajas' => 'integer',
        'pallets' => 'integer',
        'temperatura_inicial' => 'decimal:2',
        'temperatura_inicio' => 'decimal:2',
        'temperatura_inversion_interior' => 'decimal:2',
        'temperatura_inversion_exterior' => 'decimal:2',
        'temperatura_final_interna' => 'decimal:2',
        'temperatura_final_externa' => 'decimal:2',
        'fecha_hora_salida' => 'datetime',
        'temperatura_ambiente_tunel_salida' => 'decimal:2',
        'temperatura_ambiente_camara_salida' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(PreCoolingLoad::class, 'load_id');
    }

    public function tipoProceso(): BelongsTo
    {
        return $this->belongsTo(PreCoolingTipoProceso::class, 'tipo_proceso_id');
    }

    public function camaraDestino(): BelongsTo
    {
        return $this->belongsTo(PreCoolingCamara::class, 'camara_destino_id');
    }

    public function usuarioSalida(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_salida_id');
    }

    public function getTemperatureByTypeAttribute(): ?array
    {
        $valores = array_filter([
            'T° Inicio' => $this->temperatura_inicio !== null ? (float) $this->temperatura_inicio : null,
            'T° Inversión Interior' => $this->temperatura_inversion_interior !== null ? (float) $this->temperatura_inversion_interior : null,
            'T° Inversión Exterior' => $this->temperatura_inversion_exterior !== null ? (float) $this->temperatura_inversion_exterior : null,
            'T° Final Interna' => $this->temperatura_final_interna !== null ? (float) $this->temperatura_final_interna : null,
            'T° Final Externa' => $this->temperatura_final_externa !== null ? (float) $this->temperatura_final_externa : null,
        ], fn ($value) => $value !== null);

        return $valores === [] ? null : $valores;
    }
}

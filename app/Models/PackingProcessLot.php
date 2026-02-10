<?php

namespace App\Models;

use App\Enums\PlanningLotStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PackingProcessLot extends Model
{
    protected $table = 'process_lots';

    protected $fillable = [
        'process_id',
        'packing_line_id',
        'n_g_recepcion',
        'split_index',
        'setup_nota_calidad',
        'setup_calibre',
        'setup_color',
        'setup_hash',
        'destino',
        'brix',
        'variedad_id',
        'n_variedad',
        'id_productor',
        'c_productor',
        'csg_productor',
        'n_productor',
        'fecha_recepcion',
        'tipo_proceso',
        'variedad_original',
        'productor_real',
        'categoria_origen',
        'sdp_centrocosto',
        'envase_origen',
        'altura_origen',
        'c_embalaje',
        'n_embalaje',
        'cp2_cajas_por_pallet',
        'extra_packagings',
        'packaging_indications',
        'cantidad_bins',
        'peso_neto',
        'orden',
        'inicio_estimado',
        'fin_estimado',
        'estado',
    ];

    protected $casts = [
        'brix' => 'decimal:2',
        'peso_neto' => 'decimal:3',
        'inicio_estimado' => 'datetime',
        'fin_estimado' => 'datetime',
        'fecha_recepcion' => 'date',
        'cantidad_bins' => 'integer',
        'orden' => 'integer',
        'split_index' => 'integer',
        'estado' => PlanningLotStatus::class,
        'cp2_cajas_por_pallet' => 'integer',
        'id_productor' => 'integer',
        'extra_packagings' => 'array',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(PackingProcess::class, 'process_id');
    }

    public function packingLine(): BelongsTo
    {
        return $this->belongsTo(PackingLine::class, 'packing_line_id');
    }

    public function variedad(): BelongsTo
    {
        return $this->belongsTo(Variedad::class, 'variedad_id');
    }

    public function packagingChanges(): HasMany
    {
        return $this->hasMany(PackingProcessLotPackagingChange::class, 'process_lot_id');
    }

    public function lastPackagingChange(): HasOne
    {
        return $this->hasOne(PackingProcessLotPackagingChange::class, 'process_lot_id')->latestOfMany();
    }
}

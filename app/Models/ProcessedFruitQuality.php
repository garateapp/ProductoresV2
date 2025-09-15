<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessedFruitQuality extends Model
{
    use HasFactory;

    protected $fillable = [
        'proceso_id',
        'numero_de_caja',
        'fecha',
        'observaciones',
        'responsable',
        'estado',
        'firma_productor',
        'firma_responsable',
        't_muestra',
        'numero_embaladora_mano',
        'peso_exacto_caja',
        'codigo_embalaje',
        'categoria',
        'destino',
        'calibre',
        'color_cubrimiento',
        'color_fondo',
        'materia_vegetal',
        'piedras',
        'barro',
        'pedicelo_largo',
        'racimo',
        'esponjas',
        'h_esponjas',
        'llenado_tottes',
        'embalaje',
        'obs_ext',
    ];

    public function proceso()
    {
        return $this->belongsTo(Proceso::class);
    }

    public function details()
    {
        return $this->hasMany(ProcessedFruitQualityDetail::class);
    }

    public function photos()
    {
        return $this->hasMany(ProcessedFruitQualityPhoto::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessedFruitQualityDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'processed_fruit_quality_id',
        'parametro_id',
        'valor_id',
        'cantidad_muestra',
        'porcentaje_muestra',
        'categoria',
        'temperatura',
        'valor_ss',
        'tipo_item',
        'detalle_item',
        'tipo_detalle',
    ];

    public function processedFruitQuality()
    {
        return $this->belongsTo(ProcessedFruitQuality::class);
    }

    public function parametro()
    {
        return $this->belongsTo(Parametro::class);
    }

    public function valor()
    {
        return $this->belongsTo(Valor::class);
    }
}
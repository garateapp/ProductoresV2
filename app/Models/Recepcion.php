<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recepcion extends Model
{
    use HasFactory;

    protected $table = 'recepcions'; // Asegúrate de que el nombre de la tabla sea correcto

    protected $fillable = [
        'id_g_recepcion',
        'tipo_g_recepcion',
        'numero_g_recepcion',
        'fecha_g_recepcion',
        'id_emisor',
        'r_emisor',
        'n_emisor',
        'Codigo_Sag_emisor',
        'tipo_documento_recepcion',
        'numero_documento_recepcion',
        'n_especie',
        'n_variedad',
        'cantidad',
        'peso_neto',
        'nota_calidad',
        'n_estado',
        'informe',
        'temporada',
    ];

    public function calidad(): HasOne
    {
        return $this->hasOne(Calidad::class);
    }
}

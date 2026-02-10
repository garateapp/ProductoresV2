<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectoProductor extends Model
{
    protected $table = 'prospecto_productor';

    protected $fillable = [
        'razon_social',
        'rut',
        'ggn',
        'tipo_empresa',
        'giro',
        'direccion_comercial',
        'comuna_comercial',
        'fono',
        'fax_comercial',
        'direccion_predio',
        'comuna_predio',
        'email',
        'fax_contacto',
        'nombre_rep_legal',
        'rut_rep_legal',
        'direccion_rep_legal',
        'banco',
        'nombre_titular',
        'cuenta_corriente',
        'moneda',
        'sucursal',
        'constitucion_fecha_escritura',
        'notario_publico',
        'predios',
        'produccion',
        'validated_at',
        'validated_by',
        'producer_id',
        'service_id',
        'created_by',
    ];

    protected $casts = [
        'predios' => 'array',
        'produccion' => 'array',
        'constitucion_fecha_escritura' => 'date',
        'validated_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

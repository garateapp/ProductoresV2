<?php

namespace App\Models;

use App\Enums\IntegrationFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationOutputField extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'profile_version_id', 'clave_externa', 'etiqueta', 'descripcion',
        'tipo_dato', 'obligatorio', 'permite_nulo', 'valor_defecto',
        'largo_maximo', 'precision', 'escala_decimal', 'mascara_formato',
        'posicion', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_dato' => IntegrationFieldType::class,
            'obligatorio' => 'boolean',
            'permite_nulo' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function profileVersion(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfileVersion::class, 'profile_version_id');
    }
}

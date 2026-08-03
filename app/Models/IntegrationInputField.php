<?php

namespace App\Models;

use App\Enums\IntegrationFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationInputField extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'profile_version_id', 'clave', 'etiqueta', 'descripcion', 'tipo_dato',
        'ruta_valor', 'obligatorio', 'permite_nulo', 'valor_ejemplo',
        'posicion', 'activo', 'config_adicional',
    ];

    protected function casts(): array
    {
        return [
            'tipo_dato' => IntegrationFieldType::class,
            'obligatorio' => 'boolean',
            'permite_nulo' => 'boolean',
            'activo' => 'boolean',
            'config_adicional' => 'array',
        ];
    }

    public function profileVersion(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfileVersion::class, 'profile_version_id');
    }
}

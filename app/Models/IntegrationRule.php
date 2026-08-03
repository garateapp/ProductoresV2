<?php

namespace App\Models;

use App\Enums\IntegrationRuleErrorPolicy;
use App\Enums\IntegrationRuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationRule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'profile_version_id', 'tipo', 'nombre', 'descripcion', 'orden',
        'configuracion', 'obligatoria', 'politica_error', 'valor_defecto',
        'mensaje_error_personalizado', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => IntegrationRuleType::class,
            'politica_error' => IntegrationRuleErrorPolicy::class,
            'obligatoria' => 'boolean',
            'activo' => 'boolean',
            'configuracion' => 'array',
        ];
    }

    public function profileVersion(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfileVersion::class, 'profile_version_id');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(IntegrationRuleInput::class, 'rule_id');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(IntegrationRuleOutput::class, 'rule_id');
    }
}

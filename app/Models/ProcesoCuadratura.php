<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesoCuadratura extends Model
{
    use HasFactory;

    protected $table = 'proceso_cuadraturas';

    protected $fillable = [
        'proceso_id',
        'estado',
        'ciclo',
        'enviado_jefe_at',
        'aprobado_jefe_at',
        'rechazado_jefe_at',
        'comentario_rechazo',
        'ultimo_actor_id',
        'ultimo_actor_nombre',
        'ultimo_actor_email',
    ];

    protected $casts = [
        'enviado_jefe_at' => 'datetime',
        'aprobado_jefe_at' => 'datetime',
        'rechazado_jefe_at' => 'datetime',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class, 'proceso_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(ProcesoCuadraturaEvento::class, 'proceso_cuadratura_id')->latest();
    }
}


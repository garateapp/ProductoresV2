<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcesoCuadraturaEvento extends Model
{
    use HasFactory;

    protected $table = 'proceso_cuadratura_eventos';

    protected $fillable = [
        'proceso_cuadratura_id',
        'proceso_id',
        'accion',
        'detalle',
        'actor_user_id',
        'actor_nombre',
        'actor_email',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ProcesoCuadratura::class, 'proceso_cuadratura_id');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class, 'proceso_id');
    }
}


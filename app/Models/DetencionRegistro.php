<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetencionRegistro extends Model
{
    protected $table = 'detenciones_registros';

    protected $fillable = [
        'turno_id',
        'motivo_causa_id',
        'hora_detencion',
        'hora_reinicio',
        'observaciones',
        'usuario_created_id',
    ];

    protected $casts = [
        'hora_detencion' => 'datetime',
        'hora_reinicio' => 'datetime',
    ];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(DetencionTurno::class, 'turno_id');
    }

    public function causa(): BelongsTo
    {
        return $this->belongsTo(DetencionMotivoCausa::class, 'motivo_causa_id')->with('tipo');
    }

    public function usuarioCreated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_created_id');
    }

    public function estaEnCurso(): bool
    {
        return $this->hora_reinicio === null;
    }

    public function minutosPerdidos(): int
    {
        if ($this->hora_reinicio === null || $this->hora_reinicio->lessThanOrEqualTo($this->hora_detencion)) {
            return 0;
        }

        return (int) $this->hora_detencion->diffInMinutes($this->hora_reinicio);
    }
}
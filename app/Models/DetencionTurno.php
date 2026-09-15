<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetencionTurno extends Model
{
    protected $table = 'detenciones_turnos';

    protected $fillable = [
        'maquina_id',
        'fecha',
        'hora_inicio_turno',
        'hora_fin_turno',
        'operador',
        'observaciones',
        'usuario_created_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio_turno' => 'datetime',
        'hora_fin_turno' => 'datetime',
    ];

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(DetencionMaquina::class, 'maquina_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(DetencionRegistro::class, 'turno_id');
    }

    public function usuarioCreated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_created_id');
    }

    public function estaCerrado(): bool
    {
        return $this->hora_fin_turno !== null;
    }

    public function minutosTrabajados(): ?float
    {
        if ($this->hora_fin_turno === null) {
            return null;
        }

        $total = $this->hora_inicio_turno->diffInMinutes($this->hora_fin_turno);
        $detenciones = $this->registros->sum(fn (DetencionRegistro $r) => $r->minutosPerdidos());

        return max(0, $total - $detenciones);
    }
}
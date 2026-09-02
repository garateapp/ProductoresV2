<?php

namespace App\Services\PreCooling;

use App\Models\PreCoolingCamara;
use App\Models\PreCoolingSaldo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CamaraSaldoService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function ingresarManual(
        int $camaraId,
        string $banda,
        string $fila,
        string $columna,
        string $altura,
        string $nivel,
        string $folio,
        array $datos,
        User $usuario,
    ): PreCoolingSaldo {
        return DB::transaction(function () use ($camaraId, $banda, $fila, $columna, $altura, $nivel, $folio, $datos, $usuario) {
            $camara = PreCoolingCamara::query()->lockForUpdate()->findOrFail($camaraId);

            if (! $camara->activo) {
                throw ValidationException::withMessages(['camara_id' => 'La cámara está inactiva.']);
            }

            $this->validarSlot($camara, $banda, $fila, $columna, $altura, $nivel);

            $folioNormalizado = trim($folio);
            $yaEstaEnCamara = PreCoolingSaldo::query()
                ->where('folio', $folioNormalizado)
                ->lockForUpdate()
                ->exists();

            if ($yaEstaEnCamara) {
                throw ValidationException::withMessages([
                    'folio' => "El folio {$folioNormalizado} ya se encuentra registrado en una cámara.",
                ]);
            }

            try {
                $saldo = PreCoolingSaldo::create([
                    'camara_id' => $camara->id,
                    'banda' => $banda,
                    'fila' => $fila,
                    'columna' => $columna,
                    'altura' => $altura,
                    'nivel' => $nivel,
                    'folio' => $folioNormalizado,
                    'tipo_proceso_id' => $datos['tipo_proceso_id'] ?? null,
                    'cajas' => $datos['cajas'] ?? null,
                    'pallets' => $datos['pallets'] ?? null,
                    'especie' => $datos['especie'] ?? null,
                    'variedad' => $datos['variedad'] ?? null,
                    'productor' => $datos['productor'] ?? null,
                    'usuario_id' => $usuario->id,
                ]);
            } catch (QueryException $exception) {
                if ($this->esViolacionUnica($exception)) {
                    throw ValidationException::withMessages([
                        'ubicacion' => "La ubicación {$banda}/{$fila}/{$columna}/{$altura}/{$nivel} ya está ocupada.",
                    ]);
                }

                throw $exception;
            }

            $this->audit->log(
                $usuario,
                'ingreso_manual_folio_camara',
                null,
                $saldo->folio,
                null,
                ['saldo' => $saldo->toArray()],
            );

            return $saldo;
        });
    }

    public function ubicar(
        int $saldoId,
        int $camaraId,
        string $banda,
        string $fila,
        string $columna,
        string $altura,
        string $nivel,
        User $usuario,
    ): PreCoolingSaldo {
        return DB::transaction(function () use ($saldoId, $camaraId, $banda, $fila, $columna, $altura, $nivel, $usuario) {
            $saldo = PreCoolingSaldo::query()->with('loadFolio')->lockForUpdate()->findOrFail($saldoId);
            $camara = PreCoolingCamara::query()->lockForUpdate()->findOrFail($camaraId);

            if (! $camara->activo) {
                throw ValidationException::withMessages(['camara_id' => 'La cámara está inactiva.']);
            }

            $this->validarSlot($camara, $banda, $fila, $columna, $altura, $nivel);

            $antes = $saldo->toArray();

            try {
                $saldo->update([
                    'camara_id' => $camara->id,
                    'banda' => $banda,
                    'fila' => $fila,
                    'columna' => $columna,
                    'altura' => $altura,
                    'nivel' => $nivel,
                    'usuario_id' => $usuario->id,
                ]);
            } catch (QueryException $exception) {
                if ($this->esViolacionUnica($exception)) {
                    throw ValidationException::withMessages([
                        'ubicacion' => "La ubicación {$banda}/{$fila}/{$columna}/{$altura}/{$nivel} ya está ocupada.",
                    ]);
                }

                throw $exception;
            }

            $this->audit->log(
                $usuario,
                'ubicar_folio_camara',
                $saldo->loadFolio?->load_id,
                $saldo->folio,
                ['saldo' => $antes],
                ['saldo' => $saldo->fresh()->toArray()],
            );

            return $saldo->refresh();
        });
    }

    public function retirar(int $saldoId, User $usuario): void
    {
        DB::transaction(function () use ($saldoId, $usuario): void {
            $saldo = PreCoolingSaldo::query()->with('loadFolio')->lockForUpdate()->findOrFail($saldoId);
            $antes = $saldo->toArray();
            $loadId = $saldo->loadFolio?->load_id;
            $folio = $saldo->folio;

            $saldo->delete();

            $this->audit->log(
                $usuario,
                'retirar_folio_camara',
                $loadId,
                $folio,
                ['saldo' => $antes],
                null,
            );
        });
    }

    private function validarSlot(
        PreCoolingCamara $camara,
        string $banda,
        string $fila,
        string $columna,
        string $altura,
        string $nivel,
    ): void {
        $valores = $camara->parametros()
            ->where('activo', true)
            ->get()
            ->groupBy('dimension')
            ->map(fn ($items) => $items->pluck('valor')->values());

        foreach (['banda' => $banda, 'columna' => $columna, 'altura' => $altura, 'nivel' => $nivel] as $dimension => $valor) {
            if (! $valores->get($dimension, collect())->contains($valor)) {
                throw ValidationException::withMessages([
                    $dimension => "El valor \"{$valor}\" no existe en la parametrización de la cámara.",
                ]);
            }
        }

        $dimensionFila = match ($banda) {
            'Izquierda' => 'fila_izquierda',
            'Central-Izq' => 'fila_central_izq',
            'Central-Dcha' => 'fila_central_dcha',
            'Derecha' => 'fila_derecha',
            default => 'fila',
        };

        if (! $valores->get($dimensionFila, collect())->contains($fila)) {
            throw ValidationException::withMessages([
                'fila' => "La fila \"{$fila}\" no existe en la banda {$banda}.",
            ]);
        }
    }

    private function esViolacionUnica(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            || str_contains(strtolower($exception->getMessage()), 'unique constraint')
            || str_contains(strtolower($exception->getMessage()), 'duplicate entry');
    }
}

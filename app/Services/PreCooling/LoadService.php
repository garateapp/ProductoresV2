<?php

namespace App\Services\PreCooling;

use App\Models\PreCoolingCamara;
use App\Models\PreCoolingLoad;
use App\Models\PreCoolingLoadFolio;
use App\Models\PreCoolingSaldo;
use App\Models\PreCoolingTipoProceso;
use App\Models\PreCoolingTunel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoadService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {
    }

    public function crearCarga(int $tunelId, int $tipoProcesoId, string $fechaHoraInicio, User $usuario, ?int $camaraDestinoId = null, ?float $temperaturaObjetivo = null, ?array $atributos = null): PreCoolingLoad
    {
        return DB::transaction(function () use ($tunelId, $tipoProcesoId, $fechaHoraInicio, $usuario, $camaraDestinoId, $temperaturaObjetivo, $atributos) {
            $tunel = PreCoolingTunel::query()->lockForUpdate()->findOrFail($tunelId);
            $tipoProceso = PreCoolingTipoProceso::query()->lockForUpdate()->findOrFail($tipoProcesoId);

            if (! $tunel->activo) {
                throw ValidationException::withMessages(['tunel_id' => 'El túnel está inactivo.']);
            }

            if (! $tipoProceso->activo) {
                throw ValidationException::withMessages(['tipo_proceso_id' => 'El tipo de proceso está inactivo.']);
            }

            if ($camaraDestinoId) {
                $camara = PreCoolingCamara::query()->findOrFail($camaraDestinoId);
                if (! $camara->activo) {
                    throw ValidationException::withMessages(['camara_destino_id' => 'La cámara de destino está inactiva.']);
                }
            }

            $existente = PreCoolingLoad::query()
                ->where('tunel_id', $tunelId)
                ->whereIn('estado', ['ingresado', 'iniciado'])
                ->first();

            if ($existente) {
                throw ValidationException::withMessages(['tunel_id' => 'El túnel ya tiene un proceso activo.']);
            }

            $numero = $this->generarNumero($tipoProceso->codigo);

            try {
                $load = PreCoolingLoad::create([
                    'numero' => $numero,
                    'tipo_proceso_id' => $tipoProcesoId,
                    'tunel_id' => $tunelId,
                    'camara_destino_id' => $camaraDestinoId,
                    'estado' => 'ingresado',
                    'fecha_hora_inicio' => $fechaHoraInicio,
                    'temperatura_objetivo' => $temperaturaObjetivo,
                    'atributos' => $atributos,
                    'usuario_ingreso_id' => $usuario->id,
                ]);
            } catch (QueryException $e) {
                if ($this->esViolacionUnica($e)) {
                    throw ValidationException::withMessages(['tunel_id' => 'El túnel ya tiene un proceso activo.']);
                }
                throw $e;
            }

            $this->audit->log($usuario, 'crear_load', $load->id, null, null, ['load' => $load->toArray()]);

            return $load;
        });
    }

    public function actualizarCarga(int $loadId, array $datos, User $usuario): PreCoolingLoad
    {
        return DB::transaction(function () use ($loadId, $datos, $usuario) {
            $load = PreCoolingLoad::query()->lockForUpdate()->findOrFail($loadId);

            $permitidos = array_filter($datos, fn ($v) => $v !== null || array_key_exists('camara_destino_id', $datos) || array_key_exists('atributos', $datos));
            $permitidos = array_map(fn ($v) => $v === '' ? null : $v, $permitidos);

            if (isset($permitidos['tipo_proceso_id'])) {
                $tipoProceso = PreCoolingTipoProceso::query()->findOrFail($permitidos['tipo_proceso_id']);
                if (! $tipoProceso->activo) {
                    throw ValidationException::withMessages(['tipo_proceso_id' => 'El tipo de proceso está inactivo.']);
                }
            }

            if (isset($permitidos['camara_destino_id']) && $permitidos['camara_destino_id'] !== null) {
                $camara = PreCoolingCamara::query()->findOrFail($permitidos['camara_destino_id']);
                if (! $camara->activo) {
                    throw ValidationException::withMessages(['camara_destino_id' => 'La cámara de destino está inactiva.']);
                }
            }

            $antes = $load->toArray();
            $load->update($permitidos);

            $this->audit->log($usuario, 'actualizar_load', $load->id, null, ['load' => $antes], ['load' => $load->toArray()]);

            return $load;
        });
    }

    public function agregarFolio(int $loadId, string $folio, string $nivel, string $banda, string $posicion, string $altura, array $datos, User $usuario): PreCoolingLoadFolio
    {
        return DB::transaction(function () use ($loadId, $folio, $nivel, $banda, $posicion, $altura, $datos, $usuario) {
            $load = PreCoolingLoad::query()->lockForUpdate()->findOrFail($loadId);

            $this->validarEstadoIngresado($load);

            $tunel = $load->tunel;
            $valores = $this->valoresPorDimension($tunel);

            foreach (['banda' => $banda, 'posicion' => $posicion, 'altura' => $altura, 'nivel' => $nivel] as $dimension => $valor) {
                if (! $valores[$dimension]->contains($valor)) {
                    throw ValidationException::withMessages([$dimension => "El valor \"{$valor}\" de {$dimension} no existe en la parametrización del túnel."]);
                }
            }

            $existente = PreCoolingLoadFolio::query()
                ->where('load_id', $loadId)
                ->where('banda', $banda)
                ->where('posicion', $posicion)
                ->where('altura', $altura)
                ->where('nivel', $nivel)
                ->first();

            if ($existente) {
                throw ValidationException::withMessages(['posicion' => "La celda {$banda}/{$posicion}/{$altura}/{$nivel} ya está ocupada en este proceso."]);
            }

            $existenteEnOtroProceso = PreCoolingLoadFolio::query()
                ->whereHas('carga', function ($q) {
                    $q->whereIn('estado', ['ingresado', 'iniciado']);
                })
                ->where('folio', $folio)
                ->first();

            if ($existenteEnOtroProceso) {
                throw ValidationException::withMessages(['folio' => "El folio {$folio} ya está registrado en otro proceso activo de este túnel."]);
            }

            try {
                $registro = $load->folios()->create([
                    'tipo_proceso_id' => $load->tipo_proceso_id,
                    'folio' => $folio,
                    'banda' => $banda,
                    'posicion' => $posicion,
                    'altura' => $altura,
                    'nivel' => $nivel,
                    'exportadora' => $datos['exportadora'] ?? null,
                    'productor' => $datos['productor'] ?? null,
                    'especie' => $datos['especie'] ?? null,
                    'variedad' => $datos['variedad'] ?? null,
                    'embalaje' => $datos['embalaje'] ?? null,
                    'categoria' => $datos['categoria'] ?? null,
                    'calibre' => $datos['calibre'] ?? null,
                    'cajas' => $datos['cajas'] ?? null,
                    'pallets' => $datos['pallets'] ?? null,
                    'temperatura_inicial' => $datos['temperatura_inicial'] ?? null,
                    'metadata' => $datos['metadata'] ?? null,
                ]);
            } catch (QueryException $e) {
                if ($this->esViolacionUnica($e)) {
                    throw ValidationException::withMessages(['posicion' => "La celda {$banda}/{$posicion}/{$altura}/{$nivel} ya está ocupada en este proceso."]);
                }
                throw $e;
            }

            $this->audit->log($usuario, 'agregar_folio', $load->id, $folio, null, ['folio' => $registro->toArray()]);

            return $registro;
        });
    }

    public function quitarFolio(int $loadId, int $folioId, User $usuario): void
    {
        DB::transaction(function () use ($loadId, $folioId, $usuario) {
            $load = PreCoolingLoad::query()->lockForUpdate()->findOrFail($loadId);

            $this->validarEstadoIngresado($load);

            $registro = $load->folios()->findOrFail($folioId);
            $antes = $registro->toArray();
            $registro->delete();

            $this->audit->log($usuario, 'quitar_folio', $load->id, $registro->folio, ['folio' => $antes], null);
        });
    }

    public function iniciar(int $loadId, User $usuario): PreCoolingLoad
    {
        return DB::transaction(function () use ($loadId, $usuario) {
            $load = PreCoolingLoad::query()->lockForUpdate()->findOrFail($loadId);

            if ($load->estado !== 'ingresado') {
                throw ValidationException::withMessages(['estado' => 'La carga ya se encuentra iniciada.']);
            }

            if ($load->folios()->count() === 0) {
                throw ValidationException::withMessages(['folio' => 'Debe registrar al menos un folio antes de iniciar la carga.']);
            }

            $antes = $load->toArray();

            $load->update([
                'estado' => 'iniciado',
                'usuario_inicio_id' => $usuario->id,
            ]);

            $this->audit->log($usuario, 'iniciar_load', $load->id, null, ['load' => $antes], ['load' => $load->toArray()]);

            return $load;
        });
    }

    public function registrarInversion(int $loadId, string $fechaHoraInversion, User $usuario): PreCoolingLoad
    {
        return DB::transaction(function () use ($loadId, $fechaHoraInversion, $usuario) {
            $load = PreCoolingLoad::query()->lockForUpdate()->findOrFail($loadId);

            if ($load->estado !== 'iniciado') {
                throw ValidationException::withMessages(['estado' => 'Solo las cargas INICIADAS pueden registrar inversión del flujo.']);
            }

            $antes = $load->toArray();

            $load->update([
                'fecha_hora_inversion' => $fechaHoraInversion,
                'usuario_inversion_id' => $usuario->id,
            ]);

            $this->audit->log($usuario, 'registrar_inversion', $load->id, null, ['load' => $antes], ['load' => $load->toArray()]);

            return $load;
        });
    }

    public function salir(int $loadId, ?string $fechaHoraFin, int $camaraId, array $ubicaciones, User $usuario): PreCoolingLoad
    {
        return DB::transaction(function () use ($loadId, $fechaHoraFin, $camaraId, $ubicaciones, $usuario) {
            $load = PreCoolingLoad::query()->lockForUpdate()->findOrFail($loadId);

            if ($load->estado !== 'iniciado') {
                throw ValidationException::withMessages(['estado' => 'Solo las cargas INICIADAS pueden salir del túnel.']);
            }

            $folios = $load->folios()->lockForUpdate()->get();
            if ($folios->isEmpty()) {
                throw ValidationException::withMessages(['folio' => 'La carga no tiene folios registrados.']);
            }

            $camara = PreCoolingCamara::query()->lockForUpdate()->findOrFail($camaraId);

            if (! $camara->activo) {
                throw ValidationException::withMessages(['camara_id' => 'La cámara está inactiva.']);
            }

            foreach ($folios as $folio) {
                if (! isset($ubicaciones[$folio->id])) {
                    throw ValidationException::withMessages(["ubicaciones.{$folio->id}" => "Debe indicar la ubicación del folio {$folio->folio}."]);
                }
                $ubic = $ubicaciones[$folio->id];
                $this->validarSlotCamara($camara, $ubic['banda'], $ubic['fila'], $ubic['columna'], $ubic['altura'], $ubic['nivel']);
            }

            $ubicacionesUsadas = [];
            foreach ($folios as $folio) {
                $key = implode('|', $ubicaciones[$folio->id]);
                if (isset($ubicacionesUsadas[$key])) {
                    throw ValidationException::withMessages(["ubicaciones.{$folio->id}" => 'Dos folios de la carga no pueden compartir la misma ubicación en la cámara.']);
                }
                $ubicacionesUsadas[$key] = true;
            }

            foreach ($folios as $folio) {
                $ubic = $ubicaciones[$folio->id];

                try {
                    PreCoolingSaldo::create([
                        'camara_id' => $camaraId,
                        'banda' => $ubic['banda'],
                        'fila' => $ubic['fila'],
                        'columna' => $ubic['columna'],
                        'altura' => $ubic['altura'],
                        'nivel' => $ubic['nivel'],
                        'folio' => $folio->folio,
                        'tipo_proceso_id' => $load->tipo_proceso_id,
                        'cajas' => $folio->cajas,
                        'pallets' => $folio->pallets,
                        'especie' => $folio->especie,
                        'variedad' => $folio->variedad,
                        'productor' => $folio->productor,
                        'usuario_id' => $usuario->id,
                    ]);
                } catch (QueryException $e) {
                    if ($this->esViolacionUnica($e)) {
                        throw ValidationException::withMessages(["ubicaciones.{$folio->id}" => "La ubicación {$ubic['banda']}/{$ubic['fila']}/{$ubic['columna']}/{$ubic['altura']}/{$ubic['nivel']} ya está ocupada en la cámara."]);
                    }
                    throw $e;
                }
            }

            $antes = $load->toArray();

            $load->update([
                'estado' => 'salido',
                'fecha_hora_fin' => $fechaHoraFin,
                'usuario_fin_id' => $usuario->id,
            ]);

            $this->audit->log($usuario, 'salir_load', $load->id, null, ['load' => $antes], ['load' => $load->toArray()]);

            return $load;
        });
    }

    protected function validarSlotCamara(PreCoolingCamara $camara, string $banda, string $fila, string $columna, string $altura, string $nivel): void
    {
        $valores = $this->valoresPorDimensionCamara($camara);

        foreach (['banda' => $banda, 'columna' => $columna, 'altura' => $altura, 'nivel' => $nivel] as $dimension => $valor) {
            if (! ($valores[$dimension] ?? collect())->contains($valor)) {
                throw ValidationException::withMessages(['ubicaciones' => "El valor \"{$valor}\" de {$dimension} no existe en la parametrización de la cámara."]);
            }
        }

        $dimensionFila = match ($banda) {
            'Izquierda' => 'fila_izquierda',
            'Central-Izq' => 'fila_central_izq',
            'Central-Dcha' => 'fila_central_dcha',
            'Derecha' => 'fila_derecha',
            default => 'fila',
        };

        if (! ($valores[$dimensionFila] ?? collect())->contains($fila)) {
            throw ValidationException::withMessages([
                'ubicaciones' => "La fila \"{$fila}\" no existe en la banda {$banda}.",
            ]);
        }
    }

    protected function valoresPorDimensionCamara(PreCoolingCamara $camara): array
    {
        $valores = $camara->parametros()
            ->where('activo', true)
            ->get()
            ->groupBy('dimension');

        return $valores
            ->map(fn ($items) => $items->pluck('valor')->values())
            ->all();
    }

    protected function valoresPorDimension(PreCoolingTunel $tunel): array
    {
        $valores = $tunel->parametros()
            ->where('activo', true)
            ->get()
            ->groupBy('dimension');

        return [
            'banda' => $valores->get('banda', collect())->pluck('valor')->values(),
            'posicion' => $valores->get('posicion', collect())->pluck('valor')->values(),
            'altura' => $valores->get('altura', collect())->pluck('valor')->values(),
            'nivel' => $valores->get('nivel', collect())->pluck('valor')->values(),
        ];
    }

    protected function validarEstadoIngresado(PreCoolingLoad $load): void
    {
        if ($load->estado !== 'ingresado') {
            throw ValidationException::withMessages(['estado' => 'Solo se pueden modificar cargas en estado INGRESADO.']);
        }
    }

    protected function esViolacionUnica(QueryException $e): bool
    {
        $mensaje = $e->getMessage();

        return $e->getCode() === '23000'
            || str_contains($mensaje, 'Duplicate entry')
            || str_contains($mensaje, 'UNIQUE constraint failed');
    }

    protected function generarNumero(string $tipoCodigo): string
    {
        $prefijo = strtoupper($tipoCodigo);

        $ultimo = PreCoolingLoad::query()
            ->where('numero', 'like', "{$prefijo}-%")
            ->orderByDesc('numero')
            ->value('numero');

        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $consecutivo = ((int) ($partes[1] ?? 0)) + 1;
        } else {
            $consecutivo = 1;
        }

        return sprintf('%s-%04d', $prefijo, $consecutivo);
    }
}

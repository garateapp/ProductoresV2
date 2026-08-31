<?php

namespace App\Services\PreCooling;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ParametrizacionService
{
    public function validar(string $tabla, array $parametros): array
    {
        $dimensiones = match ($tabla) {
            'pre_cooling_tuneles' => ['banda', 'posicion', 'altura', 'nivel'],
            'pre_cooling_camaras' => [
                'banda',
                'fila_izquierda',
                'fila_central_izq',
                'fila_central_dcha',
                'fila_derecha',
                'columna',
                'altura',
                'nivel',
            ],
            default => [],
        };

        $validated = [];

        foreach ($dimensiones as $dimension) {
            $valores = array_values(array_unique(array_filter(array_map('trim', $parametros[$dimension] ?? []))));

            if (empty($valores)) {
                throw ValidationException::withMessages([
                    'parametros.'.$dimension => "Debe indicar al menos un valor para {$dimension}.",
                ]);
            }

            foreach ($valores as $valor) {
                if (mb_strlen($valor) > 50) {
                    throw ValidationException::withMessages([
                        'parametros.'.$dimension => "El valor \"{$valor}\" supera los 50 caracteres.",
                    ]);
                }
            }

            $validated[$dimension] = $valores;
        }

        return $validated;
    }

    public function sincronizar(Model $equipo, array $parametros): void
    {
        $equipo->parametros()->delete();

        foreach ($parametros as $dimension => $valores) {
            foreach ($valores as $orden => $valor) {
                $equipo->parametros()->create([
                    'dimension' => $dimension,
                    'valor' => $valor,
                    'orden' => $orden,
                ]);
            }
        }
    }
}

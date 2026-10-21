<?php

namespace App\Services\Garces\PalletBox;

use App\Models\GarcesPalletDefault;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Normalizer
{
    private GarcesPalletDefault $defaultModel;

    public function __construct()
    {
        $this->defaultModel = new GarcesPalletDefault;
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $cabeceras
     * @param  Collection<int,array<string,mixed>>  $detalles
     * @return array<int,array<string,mixed>>
     */
    public function normalize(Collection $cabeceras, Collection $detalles): array
    {
        $detByFolio = $detalles->groupBy(fn ($row) => trim((string) ($this->prop($row, 'folio') ?? '')));

        $result = [];
        foreach ($cabeceras as $cab) {
            $folio = trim((string) ($this->prop($cab, 'folio') ?? ''));

            if ($folio === '') {
                continue;
            }

            $detalleList = collect($detByFolio->get($folio, []));

            $totCajas = (int) ($this->prop($cab, 'cantidad') ?? 0);
            $totKilos = (float) ($this->prop($cab, 'peso_neto') ?? 0);

            if ($detalleList->isNotEmpty()) {
                $calcCajas = (int) $detalleList->sum(fn ($r) => (int) ($this->prop($r, 'ncaja') ?? 0));
                $calcKilos = (float) $detalleList->sum(function ($r) {
                    $peso = $this->prop($r, 'Peso_medido') ?? $this->prop($r, 'peso_medido') ?? 0;
                    $cajas = (int) ($this->prop($r, 'ncaja') ?? 0);

                    return (float) $peso * (int) $cajas;
                });

                if ($totCajas <= 0) {
                    $totCajas = $calcCajas;
                }
                if ($totKilos <= 0.0) {
                    $totKilos = $calcKilos;
                }
            } elseif ($totCajas <= 0) {
                $totCajas = (int) ($this->prop($cab, 'cantidad_cajas') ?? 0);
            }
            if ($totKilos <= 0.0 && ! $detalleList->isNotEmpty()) {
                $totKilos = (float) ($this->prop($cab, 'peso_neto_total') ?? 0);
            }

            if ($totKilos < 0) {
                $totKilos = 0;
            }

            $fechaProd = $this->toCarbon($this->prop($cab, 'fecha_produccion'));
            $fecha = $fechaProd?->format('Y-m-d');

            $cEspecie = trim((string) ($this->prop($cab, 'id_especie') ?? ($this->prop($cab, 'c_especie') ?? '')));
            $cVariedad = trim((string) ($this->prop($cab, 'id_variedad') ?? ($this->prop($cab, 'c_variedad') ?? '')));

            $subsidiary = trim((string) ($this->prop($cab, 'code_subsidiary') ?? ($this->prop($cab, 'id_empresa') ?? ($this->prop($cab, 'c_exportadora') ?? config('services.garces_pallet_box.code_subsidiary') ?? ''))));
            $season = trim((string) ($this->prop($cab, 'code_season') ?? config('services.garces_pallet_box.code_season') ?? ''));
            $defaults = $this->resolveDefaults($subsidiary, $season);

            $result[] = [
                'folio' => $folio,
                'fecha_produccion' => $fecha,
                'especie_interna' => $cEspecie,
                'variedad_interna' => $cVariedad,
                'cantidad_cajas' => $totCajas,
                'peso_neto_total' => round($totKilos, 3),
                'subsidiary' => $subsidiary,
                'season' => $season,
                'code_subsidiary' => $subsidiary,
                'code_season' => $season,
                'defaults' => $defaults,
                'cabecera' => $cab,
                'detalles' => $detalleList->values()->all(),
            ];
        }

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveDefaults(string $subsidiary, string $season): array
    {
        if ($subsidiary === '' || $season === '') {
            return $this->emptyDefaults();
        }

        $row = GarcesPalletDefault::query()
            ->plantaTemporada($subsidiary, $season)
            ->activo()
            ->first();

        if (! $row) {
            return $this->emptyDefaults();
        }

        return [
            'subsidiary_garces' => $row->subsidiary_garces ?? null,
            'season_garces' => $row->season_garces ?? null,
            'tipo_tarja' => $row->tipo_tarja ?? null,
            'tipo_fruta' => $row->tipo_fruta ?? null,
            'tipo_pallet' => $row->tipo_pallet ?? null,
            'is_export' => $row->is_export ?? GarcesPalletDefault::IS_EXPORT_DEFAULT,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyDefaults(): array
    {
        return [
            'subsidiary_garces' => null,
            'season_garces' => null,
            'tipo_tarja' => null,
            'tipo_fruta' => null,
            'tipo_pallet' => null,
            'is_export' => GarcesPalletDefault::IS_EXPORT_DEFAULT,
        ];
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function prop(mixed $obj, string $key): mixed
    {
        if (is_object($obj)) {
            return $obj->{$key} ?? null;
        }

        if (is_array($obj)) {
            return $obj[$key] ?? null;
        }

        return null;
    }
}

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
        $detByFolio = $detalles->groupBy(fn ($row) => trim((string) (is_object($row) ? ($row->folio ?? '') : ($row['folio'] ?? ''))));

        $result = [];

        foreach ($cabeceras as $cab) {
            $folio = trim((string) ($this->prop($cab, 'folio') ?? ''));

            if ($folio === '') {
                continue;
            }

            $detalleList = collect($detByFolio->get($folio, []));
            $totCajas = (int) $detalleList->sum(fn ($r) => (int) (is_object($r) ? ($r->ncaja ?? 0) : ($r['ncaja'] ?? 0)));
            $totKilos = (float) $detalleList->sum(function ($r) {
                $peso = is_object($r) ? ($r->Peso_medido ?? $r->peso_medido ?? 0) : ($r['Peso_medido'] ?? $r['peso_medido'] ?? 0);
                $cajas = is_object($r) ? ($r->ncaja ?? 0) : ($r['ncaja'] ?? 0);

                return (float) $peso * (int) $cajas;
            });

            if ($totCajas < 0) {
                $totCajas = 0;
            }

            if ($totKilos < 0) {
                $totKilos = 0;
            }

            $fechaProd = $this->toCarbon($this->prop($cab, 'fecha_produccion'));
            $fecha = $fechaProd?->format('Y-m-d');

            $cEspecie = trim((string) ($this->prop($cab, 'c_especie') ?? ''));
            $cVariedad = trim((string) ($this->prop($cab, 'c_variedad') ?? ''));

            $subsidiary = trim((string) ($this->prop($cab, 'code_subsidiary') ?? ''));
            $season = trim((string) ($this->prop($cab, 'code_season') ?? ''));
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

<?php

namespace App\Http\Controllers;

use App\Models\CommercialDiscard;
use App\Models\Parametro;
use App\Models\Valor;
use App\Models\Proceso;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Str;

class CommercialDiscardController extends Controller
{
    public function index()
    {
        $records = CommercialDiscard::with(['user'])
            ->latest('fecha')
            ->paginate(10)
            ->through(function (CommercialDiscard $discard) {
                return [
                    'id' => $discard->id,
                    'fecha' => optional($discard->fecha)->format('d/m/Y H:i'),
                    'productor' => $discard->productor,
                    'especie' => $discard->especie,
                    'variedad' => $discard->variedad,
                    'linea' => $discard->linea,
                    'turno' => $discard->turno,
                    'user' => $discard->user?->name,
                    'pdf_url' => route('commercial-discards.pdf', $discard),
                ];
            });

        return Inertia::render('ControlCalidad/DescarteComercialList', [
            'records' => $records,
        ]);
    }

    public function create()
    {
        $parametros = Parametro::with(['valors' => function ($query) {
            $query->orderBy('name');
        }])
            ->whereIn('id', [3, 4, 5])
            ->orderBy('name')
            ->get();

        return Inertia::render('ControlCalidad/DescarteComercial', [
            'parametros' => $parametros,
            'defaultDate' => now()->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date'],
            'linea' => ['required', 'string', 'max:50'],
            'turno' => ['required', 'string', 'max:50'],
            'productor' => ['required', 'string', 'max:255'],
            'especie' => ['required', 'string', 'max:255'],
            'variedad' => ['required', 'string', 'max:255'],
            'lote' => ['required', 'string', 'max:100'],
            'proceso' => ['required', 'string', 'max:100'],
            'frutos' => ['required', 'integer', 'min:0'],
            'defects' => ['required', 'array', 'min:1'],
            'defects.*.parametro_id' => ['required', 'exists:parametros,id'],
            'defects.*.valor_id' => ['required', 'exists:valors,id'],
            'defects.*.comercial' => ['required', 'numeric', 'min:0'],
            'defects.*.desecho' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'signature_data' => ['required', 'string'],
        ]);

        $signaturePath = $this->storeSignature($validated['signature_data']);

        $discard = CommercialDiscard::create([
            'user_id' => Auth::id(),
            'fecha' => Carbon::parse($validated['fecha']),
            'linea' => $validated['linea'],
            'turno' => $validated['turno'],
            'productor' => trim($validated['productor']),
            'especie' => trim($validated['especie']),
            'variedad' => trim($validated['variedad']),
            'lote' => trim($validated['lote']),
            'proceso' => $validated['proceso'],
            'frutos' => (int) $validated['frutos'],
            'observaciones' => $validated['observaciones'] ?? null,
            'signature_path' => $signaturePath,
        ]);

        foreach ($validated['defects'] as $index => $defect) {
            if (! in_array((int) $defect['parametro_id'], [3, 4, 5], true)) {
                throw ValidationException::withMessages([
                    "defects.$index.parametro_id" => 'El tipo seleccionado no es válido.',
                ]);
            }

            $valor = Valor::where('id', $defect['valor_id'])
                ->where('parametro_id', $defect['parametro_id'])
                ->where(function ($inner) use ($validated) {
                    $especie = strtolower(trim($validated['especie']));
                    $inner->whereNull('especie')
                        ->orWhere('especie', '')
                        ->orWhereRaw('LOWER(especie) = ?', [$especie]);
                })
                ->first();

            if (! $valor) {
                throw ValidationException::withMessages([
                    "defects.$index.valor_id" => 'El valor seleccionado no pertenece al tipo de defecto elegido.',
                ]);
            }

            $discard->details()->create([
                'parametro_id' => $defect['parametro_id'],
                'valor_id' => $defect['valor_id'],
                'comercial' => (int) $defect['comercial'],
                'desecho' => (int) $defect['desecho'],
            ]);
        }

        return redirect()
            ->route('commercial-discards.create')
            ->with('success', 'Descarte comercial registrado correctamente.')
            ->with('pdf_url', route('commercial-discards.pdf', $discard));
    }

    public function pdf(CommercialDiscard $commercialDiscard)
    {
        $commercialDiscard->load(['details.parametro', 'details.valor', 'user']);

        $signatureDataUrl = null;
        if ($commercialDiscard->signature_path && Storage::disk('public')->exists($commercialDiscard->signature_path)) {
            $signatureDataUrl = 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($commercialDiscard->signature_path));
        }

        $html = view('reports.descarte_comercial', [
            'record' => $commercialDiscard,
            'signatureDataUrl' => $signatureDataUrl,
        ])->render();

        $pdf = Browsershot::html($html)
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->pdf();

        $filename = 'Descarte_Comercial_'.$commercialDiscard->id.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function lookupProcess(string $nProceso)
    {
        $proceso = Proceso::with('recepcion')->where('n_proceso', $nProceso)->first();

        if (! $proceso) {
            return response()->json(['message' => 'Proceso no encontrado.'], 404);
        }

        $recepcion = $proceso->recepcion;

        return response()->json([
            'productor' => $proceso->agricola ?? $recepcion->n_emisor ?? null,
            'variedad' => $proceso->variedad ?? $recepcion->n_variedad ?? null,
            'especie' => $proceso->especie ?? $recepcion->n_especie ?? null,
            'lote' => $proceso->LPP_recepcion ?? $recepcion->numero_g_recepcion ?? null,
            'frutos' => $recepcion?->cantidad ?? $recepcion?->peso_neto ?? $proceso->kilos_netos ?? 0,
        ]);
    }

    private function storeSignature(string $signatureData): string
    {
        if (! str_contains($signatureData, ',')) {
            throw ValidationException::withMessages([
                'signature_data' => 'El formato de la firma es inválido.',
            ]);
        }

        [$meta, $encoded] = explode(',', $signatureData, 2);

        if (! str_contains($meta, 'image/png')) {
            throw ValidationException::withMessages([
                'signature_data' => 'La firma debe ser una imagen PNG.',
            ]);
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            throw ValidationException::withMessages([
                'signature_data' => 'No se pudo procesar la firma.',
            ]);
        }

        $path = 'signatures/'.date('Y/m').'/'.Str::uuid()->toString().'.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}

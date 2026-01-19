<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class SystemLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['Admin', 'Administrador']);

        abort_unless($isAdmin, 403);

        $filters = $request->validate([
            'file' => ['nullable', 'string'],
            'lines' => ['nullable', 'integer', 'min:50', 'max:2000'],
            'search' => ['nullable', 'string'],
        ]);

        $logDir = storage_path('logs');
        $files = collect(File::exists($logDir) ? File::files($logDir) : [])
            ->filter(fn ($file) => $file->getExtension() === 'log')
            ->map(function ($file) {
                return [
                    'name' => $file->getFilename(),
                    'path' => $file->getRealPath(),
                    'size' => $file->getSize(),
                    'modified_at' => $file->getMTime(),
                ];
            })
            ->sortByDesc('modified_at')
            ->values();

        $selected = $files->firstWhere('name', $filters['file'] ?? null) ?? $files->first();
        $lines = max(50, min(2000, (int) ($filters['lines'] ?? 500)));
        $search = trim($filters['search'] ?? '');

        $content = '';
        $matchedCount = 0;
        $modifiedAt = null;
        $fileSize = null;

        if ($selected) {
            $rawLines = $this->tailFile($selected['path'], $lines);
            $filtered = $search === ''
                ? $rawLines
                : array_values(array_filter($rawLines, fn ($line) => stripos($line, $search) !== false));

            $content = implode("\n", $filtered);
            $matchedCount = count($filtered);
            $modifiedAt = date('Y-m-d H:i:s', $selected['modified_at']);
            $fileSize = $selected['size'];
        }

        return Inertia::render('Admin/SystemLogs/Index', [
            'files' => $files->map(fn ($file) => [
                'name' => $file['name'],
                'size' => $file['size'],
                'modified_at' => date('Y-m-d H:i:s', $file['modified_at']),
            ]),
            'selectedFile' => $selected['name'] ?? null,
            'filters' => [
                'file' => $selected['name'] ?? '',
                'lines' => (string) $lines,
                'search' => $search,
            ],
            'log' => [
                'content' => $content,
                'line_count' => $matchedCount,
                'file_size' => $fileSize,
                'modified_at' => $modifiedAt,
            ],
        ]);
    }

    protected function tailFile(string $path, int $lines): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $chunkSize = 4096;

        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);
        $offset = 0;
        $lineCount = 0;

        while ($fileSize + $offset > 0 && $lineCount <= $lines) {
            $readSize = min($chunkSize, $fileSize + $offset);
            $offset -= $readSize;
            fseek($handle, $offset, SEEK_END);
            $chunk = fread($handle, $readSize);

            if ($chunk === false) {
                break;
            }

            $buffer = $chunk.$buffer;
            $lineCount = substr_count($buffer, "\n");
        }

        fclose($handle);

        $linesArray = preg_split("/\r\n|\n|\r/", $buffer);
        if (count($linesArray) > $lines) {
            $linesArray = array_slice($linesArray, -$lines);
        }

        return $linesArray;
    }
}

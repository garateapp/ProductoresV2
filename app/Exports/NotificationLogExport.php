<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class NotificationLogExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $logs;

    protected ?string $typeFilter;

    public function __construct(Collection $logs, ?string $typeFilter = null)
    {
        $this->logs = $logs;
        $this->typeFilter = $typeFilter;
    }

    public function collection(): Collection
    {
        return $this->logs;
    }

    public function headings(): array
    {
        $recipientHeading = $this->isWhatsappType($this->typeFilter) ? 'Teléfono' : 'Email';

        return [
            'Tipo',
            $recipientHeading,
            'Estado',
            'Fecha envío',
            'Contexto',
        ];
    }

    public function map($log): array
    {
        $context = $log->context ?? [];

        return [
            $log->type,
            $log->recipient,
            $log->status,
            optional($log->created_at)->format('Y-m-d H:i:s'),
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    protected function isWhatsappType(?string $type): bool
    {
        return in_array(strtolower((string) $type), ['whatsapp', 'whtsapp'], true);
    }
}

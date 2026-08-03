<?php

namespace App\Services\Inventory;

use App\Models\InventoryLedgerEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LedgerService
{
    public function currentHead(bool $forUpdate = false): array
    {
        $query = InventoryLedgerEvent::query()->orderByDesc('sequence');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $event = $query->first();

        return [
            'sequence' => (int) ($event?->sequence ?? 0),
            'hash' => (string) ($event?->event_hash ?? str_repeat('0', 64)),
        ];
    }

    public function append(array $eventData): InventoryLedgerEvent
    {
        $head = $this->currentHead(true);
        $sequence = $head['sequence'] + 1;
        $previousHash = $head['hash'];
        $eventUuid = (string) ($eventData['event_uuid'] ?? Str::uuid());
        $occurredAt = $eventData['occurred_at'] ?? now();
        $payload = $eventData['payload'] ?? [];

        $eventHash = $this->makeHash(
            $sequence,
            $previousHash,
            $eventUuid,
            $occurredAt,
            (string) $eventData['event_type'],
            $payload,
        );

        return InventoryLedgerEvent::create([
            'sequence' => $sequence,
            'event_uuid' => $eventUuid,
            'event_type' => $eventData['event_type'],
            'correlation_uuid' => $eventData['correlation_uuid'] ?? null,
            'movement_id' => $eventData['movement_id'] ?? null,
            'movement_detail_id' => $eventData['movement_detail_id'] ?? null,
            'allocation_id' => $eventData['allocation_id'] ?? null,
            'material_id' => $eventData['material_id'] ?? null,
            'location_id' => $eventData['location_id'] ?? null,
            'logistic_unit_id' => $eventData['logistic_unit_id'] ?? null,
            'signed_quantity' => $eventData['signed_quantity'] ?? 0,
            'stock_effect' => $eventData['stock_effect'] ?? 'none',
            'previous_hash' => $previousHash,
            'event_hash' => $eventHash,
            'payload' => $payload,
            'occurred_at' => $occurredAt,
            'actor_user_id' => $eventData['actor_user_id'],
            'actor_name_snapshot' => $eventData['actor_name_snapshot'] ?? null,
            'device_code' => $eventData['device_code'] ?? null,
            'app_version' => $eventData['app_version'] ?? config('app.version', 'appgreenex'),
        ]);
    }

    public function appendMany(array $events): array
    {
        $created = [];

        foreach ($events as $event) {
            $created[] = $this->append($event);
        }

        return $created;
    }

    public function verifyChain(?int $fromSequence = null): array
    {
        $query = InventoryLedgerEvent::query()->orderBy('sequence');

        if ($fromSequence !== null) {
            $query->where('sequence', '>=', $fromSequence);
        }

        $events = $query->get();

        $previous = str_repeat('0', 64);
        $previousSequence = 0;

        if ($fromSequence !== null && $fromSequence > 1) {
            $head = InventoryLedgerEvent::query()->where('sequence', '<', $fromSequence)->orderByDesc('sequence')->first();
            $previous = (string) ($head?->event_hash ?? str_repeat('0', 64));
            $previousSequence = (int) ($head?->sequence ?? 0);
        }

        foreach ($events as $event) {
            $expectedSequence = $previousSequence + 1;
            if ((int) $event->sequence !== $expectedSequence) {
                return [
                    'valid' => false,
                    'checked' => $previousSequence,
                    'failed_sequence' => (int) $event->sequence,
                    'message' => 'Secuencia no continua en ledger.',
                ];
            }

            $expectedHash = $this->makeHash(
                (int) $event->sequence,
                $previous,
                (string) $event->event_uuid,
                $event->occurred_at,
                (string) $event->event_type,
                (array) $event->payload,
            );

            if ($expectedHash !== (string) $event->event_hash || (string) $event->previous_hash !== $previous) {
                return [
                    'valid' => false,
                    'checked' => $previousSequence,
                    'failed_sequence' => (int) $event->sequence,
                    'message' => 'Hash inconsistente en ledger.',
                    'expected_previous_hash' => $previous,
                    'found_previous_hash' => (string) $event->previous_hash,
                    'expected_hash' => $expectedHash,
                    'found_hash' => (string) $event->event_hash,
                ];
            }

            $previous = (string) $event->event_hash;
            $previousSequence = (int) $event->sequence;
        }

        return [
            'valid' => true,
            'checked' => $events->count(),
            'last_sequence' => $previousSequence,
            'last_hash' => $previous,
        ];
    }

    public function canonicalPayload(array $payload): string
    {
        $normalized = $this->sortRecursively($payload);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function makeHash(int $sequence, string $previousHash, string $eventUuid, CarbonInterface|string $occurredAt, string $eventType, array $payload): string
    {
        $timestamp = $occurredAt instanceof CarbonInterface
            ? $occurredAt->copy()->utc()->format('Y-m-d\TH:i:s\Z')
            : Carbon::parse($occurredAt)->utc()->format('Y-m-d\TH:i:s\Z');

        return hash('sha256', implode('|', [
            $sequence,
            $previousHash,
            $eventUuid,
            $timestamp,
            $eventType,
            $this->canonicalPayload($payload),
        ]));
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        return $value;
    }
}

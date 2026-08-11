<?php

namespace App\Services\Inventory;

use App\Models\InventoryLogisticUnit;
use App\Support\EnvFile;
use Illuminate\Support\Facades\Schema;

class LotService
{
    private const LOT_PATTERN = '/\bL([0-9]+)\b/i';

    private const ENV_KEY = 'INVENTORY_LAST_LOT';

    public function __construct(
        private readonly EnvFile $envFile,
    ) {}

    public function currentNumber(): int
    {
        return max((int) config('inventory.last_lot', 0), $this->maxNumberFromDatabase());
    }

    public function peekNextCode(): string
    {
        return 'L'.($this->currentNumber() + 1);
    }

    public function nextCode(): string
    {
        $number = $this->currentNumber() + 1;
        $this->persist($number);

        return 'L'.$number;
    }

    public function advancePast(?string $lotCode): void
    {
        $number = $this->parseLotNumber($lotCode);

        if ($number !== null) {
            $this->persist(max($this->currentNumber(), $number));
        }
    }

    public function parseLotNumber(?string $lotCode): ?int
    {
        if ($lotCode === null || trim((string) $lotCode) === '') {
            return null;
        }

        return preg_match(self::LOT_PATTERN, (string) $lotCode, $matches)
            ? (int) $matches[1]
            : null;
    }

    public function persist(int $number): void
    {
        $this->envFile->update(self::ENV_KEY, (string) $number);
        config(['inventory.last_lot' => $number]);
    }

    private function maxNumberFromDatabase(): int
    {
        try {
            if (! Schema::hasTable('inventory_logistic_units')) {
                return 0;
            }

            $codes = InventoryLogisticUnit::query()
                ->whereNotNull('lot_code')
                ->pluck('lot_code');
        } catch (\Throwable) {
            return 0;
        }

        $max = 0;
        foreach ($codes as $code) {
            $number = $this->parseLotNumber($code);

            if ($number !== null) {
                $max = max($max, $number);
            }
        }

        return $max;
    }
}

<?php

namespace App\Services\Garces\PalletBox;

use Illuminate\Support\Facades\DB;

class SyncState
{
    private string $key = 'garces_palletbox_last_sync';

    public function getLastId(): ?int
    {
        $value = DB::table('garces_sync_states')->where('key', $this->key)->value('last_id');

        return $value ? (int) $value : null;
    }

    public function setLastId(int $id): void
    {
        DB::table('garces_sync_states')->updateOrInsert(
            ['key' => $this->key],
            ['last_id' => $id, 'updated_at' => now()]
        );
    }
}

<?php

namespace App\Console\Commands;

use App\Models\SagCertification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifySagCertifications extends Command
{
    protected $signature = 'sag:notify-certifications {--days=90 : Threshold in days for expiring soon}';

    protected $description = 'Lists SAG certifications that are expired or expiring within N days and logs a summary. Integrate with mail as needed.';

    public function handle(): int
    {
        $threshold = (int) $this->option('days');
        $now = Carbon::now();

        $certs = SagCertification::whereNotNull('expiration_date')->get();

        $grouped = [
            'expired' => collect(),
            'expiring_soon' => collect(),
        ];

        foreach ($certs as $c) {
            $diff = $now->diffInDays(Carbon::parse($c->expiration_date), false);
            if ($diff < 0) {
                $grouped['expired']->push($c);
            } elseif ($diff <= $threshold) {
                $grouped['expiring_soon']->push($c);
            }
        }

        $this->info('Expired: '.$grouped['expired']->count().', Expiring within '.$threshold.' days: '.$grouped['expiring_soon']->count());
        Log::info('[SAG] Certifications status', [
            'expired' => $grouped['expired']->count(),
            'expiring_soon' => $grouped['expiring_soon']->count(),
        ]);

        // Example: group by CSG and output a simple table
        foreach (['expired', 'expiring_soon'] as $key) {
            if ($grouped[$key]->isEmpty()) {
                continue;
            }
            $this->line(strtoupper($key));
            $byCsg = $grouped[$key]->groupBy('csg_user_id');
            foreach ($byCsg as $csgId => $items) {
                $csg = $csgId ? User::find($csgId) : null;
                $this->line('CSG: '.($csg->csg ?? 'N/A').' - '.$items->count().' docs');
                foreach ($items as $doc) {
                    $this->line(' - '.$doc->name.' | vence: '.$doc->expiration_date);
                }
            }
        }

        return self::SUCCESS;
    }
}

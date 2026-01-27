<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ReconcileBalances implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $accounts = Account::all();
        $issues = [];

        foreach ($accounts as $acc) {
            $sum = Entry::where('account_id', $acc->id)->sum('amount');
            $diff = abs($acc->balance - $sum);

            if ($diff > 0.0001) {
                $issues[] = [
                    'account_id' => $acc->id,
                    'type' => $acc->type,
                    'balance' => $acc->balance,
                    'entry_sum' => $sum,
                    'diff' => $acc->balance - $sum,
                ];
            }
        }

        if (!empty($issues)) {
            logger()->warning('balance discrepancies found', ['count' => count($issues), 'issues' => $issues]);

            DB::table('reconciliation_logs')->insert([
                'discrepancies' => json_encode($issues),
                'count' => count($issues),
                'created_at' => now(),
            ]);
        }
    }
}

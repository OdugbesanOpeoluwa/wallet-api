<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;  

    
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
        $this->onConnection('rabbitmq');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $events = DB::table('outbox')
            ->where('processed', false)
            ->orderBy('created_at')
            ->limit(100)
            ->get();
        foreach ($events as $event) {
            try {
                $this->dispatch($event);
                DB::table('outbox')
                    ->where('id', $event->id)
                    ->update([
                        'processed' => true,
                        'processed_at' => now(),
                    ]);
            } catch (\Exception $e) {
                // log and continue
                logger()->error("Outbox failed: {$event->id}", ['error' => $e->getMessage()]);
            }
        }
    }
    protected function dispatch($event): void
    {
        $payload = json_decode($event->payload, true);
        match ($event->event_type) {
            'bill_payment' => ProcessBillPayment::dispatch($payload['transaction_id']),
            'withdrawal' => ProcessWithdrawal::dispatch($payload['transaction_id']),
            default => null,
        };
    }
}

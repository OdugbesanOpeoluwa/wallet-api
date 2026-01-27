<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhook;
use Illuminate\Http\Request;
use App\Models\WebhookLog;

class WebhookController extends Controller
{
    //
    public function crypto(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-Signature');
        $eventId = $payload['event_id'] ?? null;
        // verify signature (mock)
        if (!$this->verifySignature($payload, $signature)) {
            return $this->error('Invalid signature', 401);
        }

        // replay check
        if ($eventId) {
            $existing = WebhookLog::where('event_id', $eventId)->first();
            if ($existing && $existing->processed) {
                return $this->success(['message' => 'Already processed']);
            }
            if ($existing) {
                return $this->success(['message' => 'Received']);
            }
        }

        // store and queue for processing
        $log = WebhookLog::create([
            'provider' => 'crypto',
            'payload' => $payload,
            'signature' => $signature,
            'event_id' => $eventId,
            'processed' => false,
        ]);

        ProcessWebhook::dispatch($log->id);

        return $this->success(['message' => 'Received']);
    }

    public function bank(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-Bank-Signature');
        $eventId = $payload['reference'] ?? null;

        if (!$this->verifyBankSignature($payload, $signature)) {
            return $this->error('Invalid signature', 401);
        }

        if ($eventId) {
            $existing = WebhookLog::where('event_id', $eventId)->where('provider', 'bank')->first();
            if ($existing) {
                return $this->success(['message' => 'Received']);
            }
        }

        $log = WebhookLog::create([
            'provider' => 'bank',
            'payload' => $payload,
            'signature' => $signature,
            'event_id' => $eventId,
            'processed' => false,
        ]);

        ProcessWebhook::dispatch($log->id);

        return $this->success(['message' => 'Received']);
    }

    protected function verifySignature(array $payload, ?string $signature): bool
    {
        if (!$signature) return false;
        
        $secret = config('services.crypto.webhook_secret');
        if (!$secret) return false;
        
        $computed = hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($computed, $signature);
    }

    protected function verifyBankSignature(array $payload, ?string $signature): bool
    {
        if (!$signature) return false;
        
        $secret = config('services.bank.webhook_secret');
        if (!$secret) return false;
        
        $computed = hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($computed, $signature);
    }
}

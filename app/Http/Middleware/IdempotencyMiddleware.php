<?php

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;

/**
 * Idempotency middleware
 * 
 * @author 
 */
class IdempotencyMiddleware
{
    public function __construct(protected IdempotencyService $service)
    {
        //
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        $key = $request->header('X-Idempotency-Key');
        if (!$key) {
            return $next($request);
        }

        $userId = $request->user()?->id;

        try {
            $cached = $this->service->check($key, $request->all(), $userId);
            if ($cached) {
                return response()->json($cached['body'], $cached['status'])
                    ->header('X-Idempotency-Replayed', 'true');
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            $this->service->store($key, $request->all(), [
                'body' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], $userId);
        }

        return $response;
    }
}

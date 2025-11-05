<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogApiRequests
{
    public function handle(Request $request, Closure $next)
    {
        // Log samo authenticated zahteve
        if ($request->bearerToken()) {
            Log::info('🌐 API Request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'has_bearer' => true,
                'bearer_preview' => substr($request->bearerToken(), 0, 20) . '...'
            ]);
        }

        return $next($request);
    }
}


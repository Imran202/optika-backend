<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogPushTokenRequests
{
    public function handle(Request $request, Closure $next)
    {
        // Log incoming request
        Log::channel('single')->info('🔵 PUSH TOKEN REQUEST INCOMING', [
            'timestamp' => now()->toDateTimeString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_auth_header' => $request->hasHeader('Authorization'),
            'content_type' => $request->header('Content-Type'),
            'body' => $request->all()
        ]);

        $response = $next($request);

        // Log outgoing response
        Log::channel('single')->info('🔵 PUSH TOKEN RESPONSE OUTGOING', [
            'timestamp' => now()->toDateTimeString(),
            'status' => $response->status(),
            'content' => $response->getContent()
        ]);

        return $response;
    }
}


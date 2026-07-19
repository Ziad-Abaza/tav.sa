<?php

namespace Modules\ApiWebhookManager\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Models\ApiToken;

class RateLimitApi
{
    public function handle(Request $request, Closure $next)
    {
        $apiToken = $request->attributes->get('api_token');

        if (! $apiToken instanceof ApiToken) {
            return $next($request);
        }

        // Check if IP is whitelisted - if so, bypass rate limiting entirely
        $clientIp = $request->ip();
        if (! empty($apiToken->ip_whitelist) && in_array($clientIp, $apiToken->ip_whitelist)) {
            $response = $next($request);

            // Still add rate limit headers for transparency
            return $response->withHeaders([
                'X-RateLimit-Limit' => 'unlimited',
                'X-RateLimit-Remaining' => 'unlimited',
                'X-RateLimit-Whitelisted' => 'true',
            ]);
        }

        $key = 'api_token:'.$apiToken->id;
        $maxAttempts = $apiToken->rate_limit_per_minute ?? 60;
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            $response = ApiResponse::rateLimitExceeded($retryAfter);

            return $response->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
                'Retry-After' => $retryAfter,
            ]);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        $remaining = RateLimiter::remaining($key, $maxAttempts);
        $resetAt = now()->addMinutes($decayMinutes)->timestamp;

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $resetAt,
            'X-RateLimit-Window' => $decayMinutes * 60,
        ]);
    }
}

<?php

namespace Modules\ApiWebhookManager\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Models\ApiToken;

class CheckApiScope
{
    public function handle(Request $request, Closure $next, string ...$scopes)
    {
        $apiToken = $request->attributes->get('api_token');

        if (! $apiToken instanceof ApiToken) {
            return ApiResponse::insufficientScope();
        }

        if (empty($scopes)) {
            return $next($request);
        }

        if ($apiToken->hasAnyScope($scopes)) {
            return $next($request);
        }

        $requiredScopes = implode(', ', $scopes);

        return ApiResponse::insufficientScope($requiredScopes);
    }
}

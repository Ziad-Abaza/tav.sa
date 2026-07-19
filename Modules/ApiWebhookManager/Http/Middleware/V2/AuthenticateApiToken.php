<?php

namespace Modules\ApiWebhookManager\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Models\ApiToken;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return ApiResponse::error(
                ErrorCode::AUTHENTICATION_REQUIRED,
                'API token is required. Please provide a valid token in the Authorization header.'
            );
        }

        $apiToken = ApiToken::where('token', $token)
            ->with('tenant')
            ->first();

        if (! $apiToken) {
            return ApiResponse::error(
                ErrorCode::INVALID_TOKEN,
                'The provided API token is invalid.'
            );
        }

        if (! $apiToken->is_active) {
            return ApiResponse::error(
                ErrorCode::TOKEN_REVOKED,
                'The API token has been revoked.'
            );
        }

        if ($apiToken->isExpired()) {
            return ApiResponse::error(
                ErrorCode::TOKEN_EXPIRED,
                'The API token has expired.'
            );
        }

        $clientIp = $request->ip();
        if (! $apiToken->isIpAllowed($clientIp)) {
            return ApiResponse::error(
                ErrorCode::IP_NOT_WHITELISTED,
                "Your IP address ({$clientIp}) is not whitelisted for this API token."
            );
        }

        if (! $apiToken->tenant) {
            return ApiResponse::error(
                ErrorCode::INVALID_TOKEN,
                'The API token is not associated with a valid tenant.'
            );
        }

        $apiToken->updateLastUsed($clientIp);

        $request->merge([
            'api_token' => $apiToken,
            'tenant_id' => $apiToken->tenant_id,
            'tenant_subdomain' => $apiToken->tenant->subdomain,
        ]);

        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('tenant_id', $apiToken->tenant_id);
        $request->attributes->set('tenant_subdomain', $apiToken->tenant->subdomain);

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            return $bearerToken;
        }

        return null;
    }
}

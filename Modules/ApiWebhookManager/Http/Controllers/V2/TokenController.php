<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\ApiWebhookManager\Enums\ApiScope;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Resources\V2\ApiTokenResource;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Models\ApiToken;
use Modules\ApiWebhookManager\Services\V2\QueryBuilderService;

class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $query = ApiToken::where('tenant_id', $tenantId);

            $tokens = QueryBuilderService::for($query, $request)
                ->allowedFilters(['is_active', 'created_at'])
                ->allowedSorts(['name', 'created_at', 'last_used_at', 'expires_at'])
                ->searchable(['name'])
                ->apply()
                ->paginate();

            return ApiResponse::paginated($tokens, function ($token) {
                return (new ApiTokenResource($token))->resolve();
            });
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch API tokens',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:api_tokens,name,NULL,id,tenant_id,'.$tenantId],
                'scopes' => ['required', 'array', 'min:1'],
                'scopes.*' => ['required', 'string'],
                'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
                'ip_whitelist' => ['nullable', 'array'],
                'ip_whitelist.*' => ['ip'],
                'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:10000'],
            ], [
                'name.required' => 'Token name is required.',
                'name.unique' => 'A token with this name already exists.',
                'scopes.required' => 'At least one scope is required.',
                'scopes.min' => 'At least one scope is required.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $scopes = $request->input('scopes');
            $validScopes = array_map(fn ($scope) => $scope->value, ApiScope::cases());

            foreach ($scopes as $scope) {
                if (! in_array($scope, $validScopes)) {
                    return ApiResponse::validationError([
                        'scopes' => ["Invalid scope: {$scope}. Valid scopes are: ".implode(', ', $validScopes)],
                    ]);
                }
            }

            $token = ApiToken::generate(
                $tenantId,
                $request->input('name'),
                $scopes,
                $request->input('expires_in_days'),
                $request->input('ip_whitelist'),
                $request->input('rate_limit_per_minute', 60)
            );

            return ApiResponse::created(
                [
                    'token' => (new ApiTokenResource($token))->resolve(),
                    'plain_token' => $token->token,
                    'warning' => 'This is the only time you will see the plain token. Please store it securely.',
                ],
                'API token created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to create API token',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $token = ApiToken::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $token) {
                return ApiResponse::notFound('API token not found', 'token');
            }

            return ApiResponse::resource(new ApiTokenResource($token));
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch API token',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $token = ApiToken::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $token) {
                return ApiResponse::notFound('API token not found', 'token');
            }

            $validator = Validator::make($request->all(), [
                'name' => ['sometimes', 'string', 'max:255', 'unique:api_tokens,name,'.$id.',id,tenant_id,'.$tenantId],
                'scopes' => ['sometimes', 'array', 'min:1'],
                'scopes.*' => ['required', 'string'],
                'ip_whitelist' => ['nullable', 'array'],
                'ip_whitelist.*' => ['ip'],
                'rate_limit_per_minute' => ['sometimes', 'integer', 'min:1', 'max:10000'],
                'is_active' => ['sometimes', 'boolean'],
            ], [
                'name.unique' => 'A token with this name already exists.',
                'scopes.min' => 'At least one scope is required.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            if ($request->has('scopes')) {
                $scopes = $request->input('scopes');
                $validScopes = array_map(fn ($scope) => $scope->value, ApiScope::cases());

                foreach ($scopes as $scope) {
                    if (! in_array($scope, $validScopes)) {
                        return ApiResponse::validationError([
                            'scopes' => ["Invalid scope: {$scope}. Valid scopes are: ".implode(', ', $validScopes)],
                        ]);
                    }
                }
            }

            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->input('name');
            }

            if ($request->has('scopes')) {
                $updateData['scopes'] = $request->input('scopes');
            }

            if ($request->has('ip_whitelist')) {
                $updateData['ip_whitelist'] = $request->input('ip_whitelist');
            }

            if ($request->has('rate_limit_per_minute')) {
                $updateData['rate_limit_per_minute'] = $request->input('rate_limit_per_minute');
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->input('is_active');
            }

            $token->update($updateData);

            return ApiResponse::updated(
                new ApiTokenResource($token->fresh()),
                'API token updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to update API token',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $token = ApiToken::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $token) {
                return ApiResponse::notFound('API token not found', 'token');
            }

            $revoked = $token->revoke();

            if (! $revoked) {
                return ApiResponse::error(
                    ErrorCode::INTERNAL_ERROR,
                    'Failed to revoke API token'
                );
            }

            return ApiResponse::deleted('API token revoked successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to revoke API token',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function rotate(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $token = ApiToken::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $token) {
                return ApiResponse::notFound('API token not found', 'token');
            }

            if (! $token->is_active) {
                return ApiResponse::error(
                    ErrorCode::VALIDATION_ERROR,
                    'Cannot rotate an inactive token'
                );
            }

            $newToken = $token->rotate();

            return ApiResponse::success(
                [
                    'token' => (new ApiTokenResource($newToken))->resolve(),
                    'plain_token' => $newToken->token,
                    'warning' => 'This is the only time you will see the new plain token. Please store it securely.',
                    'old_token_revoked' => true,
                ],
                'API token rotated successfully. Old token has been revoked.'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to rotate API token',
                ['error' => $e->getMessage()]
            );
        }
    }
}

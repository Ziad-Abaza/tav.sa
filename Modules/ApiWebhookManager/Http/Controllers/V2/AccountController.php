<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\FeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;

class AccountController extends Controller
{
    public function __construct(
        public FeatureService $featureService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $tenant = Tenant::with('activeSubscription.plan')->find($tenantId);

            if (! $tenant) {
                return ApiResponse::notFound('Account not found', 'account');
            }

            $accountData = [
                'id' => $tenant->id,
                'name' => $tenant->company_name ?? $tenant->subdomain,
                'subdomain' => $tenant->subdomain,
                'status' => $tenant->status ?? 'active',
                'created_at' => $tenant->created_at?->toIso8601String(),
                'subscription' => [
                    'plan_name' => $tenant->activeSubscription?->plan?->name ?? 'Free',
                    'plan_id' => $tenant->activeSubscription?->plan_id ?? null,
                    'status' => $tenant->activeSubscription?->status ?? null,
                    'expires_at' => $tenant->activeSubscription?->current_period_ends_at?->toIso8601String(),
                ],
            ];

            return ApiResponse::success($accountData, 'Account information retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch account information',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function usage(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $tenantSubdomain = $request->get('tenant_subdomain');

        try {
            // Initialize tenant context for the FeatureService
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                return ApiResponse::notFound('Tenant not found', 'tenant');
            }

            // Execute within tenant context
            $featureService = $this->featureService;
            /** @var Tenant $tenant */
            $usage = $tenant->execute(function () use ($featureService) {
                return [
                    'contacts' => [
                        'current' => $featureService->getCurrentUsage('contacts'),
                        'limit' => $featureService->getLimit('contacts'),
                        'remaining' => $featureService->getRemainingLimit('contacts'),
                    ],
                    'conversations' => [
                        'current' => $featureService->getCurrentUsage('conversations'),
                        'limit' => $featureService->getLimit('conversations'),
                        'remaining' => $featureService->getRemainingLimit('conversations'),
                    ],
                    'campaigns' => [
                        'current' => $featureService->getCurrentUsage('campaigns'),
                        'limit' => $featureService->getLimit('campaigns'),
                        'remaining' => $featureService->getRemainingLimit('campaigns'),
                    ],
                    'staff' => [
                        'current' => $featureService->getCurrentUsage('staff'),
                        'limit' => $featureService->getLimit('staff'),
                        'remaining' => $featureService->getRemainingLimit('staff'),
                    ],
                ];
            });

            // Add percentages
            foreach ($usage as $feature => &$data) {
                if ($data['limit'] !== null && $data['limit'] > 0) {
                    $data['percentage_used'] = round(($data['current'] / $data['limit']) * 100, 2);
                } elseif ($data['limit'] === -1) {
                    $data['percentage_used'] = 0;
                } else {
                    $data['percentage_used'] = 0;
                }
                $data['is_unlimited'] = $data['limit'] === -1;
            }

            return ApiResponse::success($usage, 'Usage statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch usage statistics',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function limits(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            // Initialize tenant context for the FeatureService
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                return ApiResponse::notFound('Tenant not found', 'tenant');
            }

            // Get API token rate limit
            $apiToken = $request->attributes->get('api_token');
            $apiRateLimit = $apiToken ? $apiToken->rate_limit_per_minute : 60;

            // Execute within tenant context
            $featureService = $this->featureService;
            /** @var Tenant $tenant */
            $result = $tenant->execute(function () use ($apiRateLimit, $featureService) {
                // Get all plan limits
                $limits = [
                    'contacts' => [
                        'limit' => $featureService->getLimit('contacts'),
                        'feature_name' => 'Total Contacts',
                        'is_unlimited' => $featureService->getLimit('contacts') === -1,
                    ],
                    'conversations' => [
                        'limit' => $featureService->getLimit('conversations'),
                        'feature_name' => 'Conversations per Month',
                        'is_unlimited' => $featureService->getLimit('conversations') === -1,
                    ],
                    'campaigns' => [
                        'limit' => $featureService->getLimit('campaigns'),
                        'feature_name' => 'Active Campaigns',
                        'is_unlimited' => $featureService->getLimit('campaigns') === -1,
                    ],
                    'staff' => [
                        'limit' => $featureService->getLimit('staff'),
                        'feature_name' => 'Team Members',
                        'is_unlimited' => $featureService->getLimit('staff') === -1,
                    ],
                    'api_calls_per_minute' => [
                        'limit' => $apiRateLimit,
                        'feature_name' => 'API Calls per Minute',
                        'is_unlimited' => false,
                    ],
                ];

                // Get feature access
                $features = [
                    'whatsapp_templates' => $featureService->hasAccess('whatsapp_templates'),
                    'bulk_messaging' => $featureService->hasAccess('bulk_messaging'),
                    'chatbot' => $featureService->hasAccess('chatbot'),
                    'advanced_analytics' => $featureService->hasAccess('advanced_analytics'),
                    'api_access' => $featureService->hasAccess('api_access'),
                    'custom_branding' => $featureService->hasAccess('custom_branding'),
                ];

                return [
                    'limits' => $limits,
                    'features' => $features,
                ];
            });

            return ApiResponse::success($result, 'Plan limits retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch plan limits',
                ['error' => $e->getMessage()]
            );
        }
    }
}

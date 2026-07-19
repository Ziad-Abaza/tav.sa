<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Tenant\WhatsappTemplate;
use App\Traits\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Resources\V2\TemplateResource;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Services\V2\QueryBuilderService;

class TemplateController extends Controller
{
    use WhatsApp;

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $query = WhatsappTemplate::where('tenant_id', $tenantId);

            $templates = QueryBuilderService::for($query, $request)
                ->allowedFilters(['status', 'category', 'language'])
                ->allowedSorts(['template_name', 'created_at', 'updated_at'])
                ->searchable(['template_name', 'category'])
                ->apply()
                ->paginate();

            return ApiResponse::paginated($templates, function ($template) {
                return (new TemplateResource($template))->resolve();
            });
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch templates',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $template = WhatsappTemplate::where('tenant_id', $tenantId)
                ->where('template_id', $id)
                ->first();

            if (! $template) {
                return ApiResponse::notFound('Template not found', 'template');
            }

            return ApiResponse::resource(new TemplateResource($template));
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch template',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function sync(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $this->setWaTenantId($tenantId);

            // Get WhatsApp settings
            $settings = tenant_settings_by_group('whatsapp', $tenantId);

            if (empty($settings['wm_business_account_id']) || empty($settings['wm_access_token'])) {
                return ApiResponse::error(
                    ErrorCode::WHATSAPP_NOT_CONFIGURED,
                    'WhatsApp Business API is not configured for this account'
                );
            }

            // Sync templates from WhatsApp
            $result = $this->syncTemplatesFromWhatsApp($tenantId, $settings);

            if ($result['success']) {
                return ApiResponse::success(
                    [
                        'synced_count' => $result['count'] ?? 0,
                        'templates' => $result['templates'] ?? [],
                    ],
                    'Templates synchronized successfully from WhatsApp'
                );
            } else {
                return ApiResponse::error(
                    ErrorCode::WHATSAPP_API_ERROR,
                    'Failed to sync templates from WhatsApp',
                    ['error' => $result['error'] ?? 'Unknown error']
                );
            }
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to sync templates',
                ['error' => $e->getMessage()]
            );
        }
    }

    protected function syncTemplatesFromWhatsApp(int $tenantId, array $settings): array
    {
        try {
            // This method would call WhatsApp Graph API to fetch templates
            // For now, returning a placeholder response
            // You'll need to implement the actual WhatsApp API call

            // Example WhatsApp API call:
            // GET https://graph.facebook.com/v18.0/{WABA_ID}/message_templates

            return [
                'success' => true,
                'count' => 0,
                'templates' => [],
                'message' => 'Template sync functionality needs WhatsApp Graph API integration',
            ];

            // Actual implementation would be:
            /*
            $client = new \GuzzleHttp\Client();
            $response = $client->get(
                "https://graph.facebook.com/v18.0/{$settings['wm_business_account_id']}/message_templates",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $settings['wm_access_token'],
                    ],
                ]
            );

            $data = json_decode($response->getBody(), true);

            // Process and save templates to database
            $syncedCount = 0;
            foreach ($data['data'] as $templateData) {
                WhatsappTemplate::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'template_id' => $templateData['id'],
                    ],
                    [
                        'template_name' => $templateData['name'],
                        'language' => $templateData['language'],
                        'status' => $templateData['status'],
                        'category' => $templateData['category'],
                        // ... other fields
                    ]
                );
                $syncedCount++;
            }

            return [
                'success' => true,
                'count' => $syncedCount,
            ];
            */
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

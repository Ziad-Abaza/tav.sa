<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Models\Tenant\WhatsappTemplate;
use App\Services\FeatureService;
use App\Traits\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Models\OtpVerification;

class AuthenticationController extends Controller
{
    use WhatsApp;

    public function __construct(
        public FeatureService $featureService
    ) {}

    /**
     * Send Authentication Template (OTP/Verification)
     *
     * Send WhatsApp authentication templates for OTP, verification codes, or two-factor authentication.
     * This endpoint is optimized for authentication use cases and handles contact auto-creation.
     *
     * **Authentication Templates:**
     * - Designed for OTP and verification codes
     * - Have higher delivery priority
     * - No 24-hour message window restriction
     * - Must use pre-approved AUTHENTICATION category templates
     *
     * **Use Cases:**
     * - Send OTP for login verification
     * - Two-factor authentication codes
     * - Account verification
     * - Password reset codes
     * - Phone number verification
     *
     * **Required Scope:** `messages:send`
     */
    public function sendAuthenticationTemplate(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string', 'min:10', 'max:20', 'regex:/^[\d+\-\s()]+$/'],
                'template_name' => ['required', 'string'],
                'language' => ['nullable', 'string', 'max:10'],
                'code' => ['required', 'string'],
                'expiry_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
                'contact_id' => ['nullable', 'integer', Rule::exists(Contact::fromTenant($subdomain)->getTable(), 'id')],
            ], [
                'phone.required' => 'Phone number is required.',
                'phone.min' => 'Phone number must be at least 10 digits.',
                'phone.regex' => 'Phone number can only contain digits, +, -, spaces, and parentheses.',
                'template_name.required' => 'Template name is required.',
                'code.required' => 'Authentication code is required.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $this->setWaTenantId($tenantId);

            $phoneNumber = $this->cleanPhoneNumber($request->input('phone'));
            $templateName = $request->input('template_name');
            $language = $request->input('language', 'en');
            $code = $request->input('code');
            $expiryMinutes = $request->input('expiry_minutes', 10);
            $contactId = $request->input('contact_id');

            // Verify template exists and is AUTHENTICATION category
            $template = WhatsappTemplate::where('tenant_id', $tenantId)
                ->where('template_name', $templateName)
                ->where('language', $language)
                ->where('status', 'APPROVED')
                ->first();

            if (! $template) {
                return ApiResponse::error(
                    ErrorCode::RESOURCE_NOT_FOUND,
                    "Authentication template '{$templateName}' not found or not approved",
                    ['template_name' => $templateName, 'language' => $language]
                );
            }

            if ($template->category !== 'AUTHENTICATION') {
                return ApiResponse::error(
                    ErrorCode::VALIDATION_ERROR,
                    'Template must be of type AUTHENTICATION to use this endpoint',
                    [
                        'template_name' => $templateName,
                        'actual_category' => $template->category,
                        'required_category' => 'AUTHENTICATION',
                    ]
                );
            }

            // Handle contact lookup or auto-creation
            $contact = null;

            if ($contactId) {
                $contact = Contact::fromTenant($subdomain)
                    ->where('id', $contactId)
                    ->first();

                if (! $contact) {
                    return ApiResponse::notFound('Contact not found', 'contact');
                }
            } else {
                $contact = Contact::fromTenant($subdomain)
                    ->where('phone', $phoneNumber)
                    ->first();

                if (! $contact) {
                    if ($this->featureService->hasReachedLimit('contacts', Contact::class)) {
                        $limit = $this->featureService->getLimit('contacts');
                        $current = $this->featureService->getCurrentUsage('contacts');

                        return ApiResponse::featureLimitExceeded('contacts', $current, $limit);
                    }

                    $defaultStatusId = get_tenant_setting_by_tenant_id('whats-mark', 'lead_status', null, $tenantId);
                    $defaultSourceId = get_tenant_setting_by_tenant_id('whats-mark', 'lead_source', null, $tenantId);

                    if (! $defaultStatusId || ! $defaultSourceId) {
                        return ApiResponse::error(
                            ErrorCode::VALIDATION_ERROR,
                            'Cannot auto-create contact. Please configure default lead status and source in WhatsApp settings, or provide contact_id.',
                            [
                                'missing_config' => array_filter([
                                    'lead_status' => ! $defaultStatusId ? 'Lead status not configured' : null,
                                    'lead_source' => ! $defaultSourceId ? 'Lead source not configured' : null,
                                ]),
                            ]
                        );
                    }

                    $contact = Contact::fromTenant($subdomain)->create([
                        'tenant_id' => $tenantId,
                        'firstname' => $phoneNumber,
                        'lastname' => $phoneNumber,
                        'phone' => $phoneNumber,
                        'type' => 'lead',
                        'status_id' => $defaultStatusId,
                        'source_id' => $defaultSourceId,
                        'addedfrom' => $tenantId,
                    ]);

                    $this->featureService->trackUsage('contacts');
                }
            }

            if ($contact->is_opted_out) {
                return ApiResponse::error(
                    ErrorCode::CONTACT_OPTED_OUT,
                    'Contact has opted out of receiving messages'
                );
            }

            $whatsappSettings = $this->getWhatsAppConnectionSettings($tenantId);

            if (! $whatsappSettings) {
                return ApiResponse::error(
                    ErrorCode::WHATSAPP_NOT_CONFIGURED,
                    'WhatsApp connection is not configured for this account'
                );
            }

            if (! $this->featureService->hasAccess('whatsapp_templates')) {
                return ApiResponse::error(
                    ErrorCode::FEATURE_NOT_AVAILABLE,
                    'WhatsApp templates feature is not available in your plan'
                );
            }

            if ($this->featureService->checkConversationLimit($contact->id, $tenantId, null, $contact->type)) {
                return ApiResponse::error(
                    ErrorCode::FEATURE_LIMIT_EXCEEDED,
                    'Conversation limit reached. Please upgrade your plan.'
                );
            }

            // Prepare template data for authentication
            $templateData = [
                'template_id' => $template->id,
                'template_name' => $template->template_name,
                'language' => $template->language,
                'category' => $template->category,
                'rel_type' => $contact->type,
                'rel_id' => $contact->id,
                'campaign_id' => 0,
                'header_data_format' => $template->header_data_format,
                'header_data_text' => $template->header_data_text,
                'header_params_count' => $template->header_params_count,
                'body_params_count' => $template->body_params_count,
                'footer_params_count' => $template->footer_params_count,
                'filename' => $template->filename ?? null,
                'body_data' => $template->body_data,
                'buttons_data' => $template->buttons_data,
                'tenant_id' => $tenantId,
            ];

            // Build body parameters based on template
            $bodyParams = [];
            if ($template->body_params_count > 0) {
                // First parameter is usually the OTP/code
                $bodyParams[] = $code;

                // Second parameter is usually expiry time (if template has 2+ params)
                if ($template->body_params_count >= 2) {
                    $bodyParams[] = $expiryMinutes;
                }

                // Add any additional parameters from request
                $additionalParams = $request->input('additional_params', []);
                if (is_array($additionalParams) && count($additionalParams) > 0) {
                    $bodyParams = array_merge($bodyParams, array_values($additionalParams));
                }

                // Ensure we have the exact number of parameters
                $bodyParams = array_slice($bodyParams, 0, $template->body_params_count);
            }

            // Set body_params as JSON string for parseText function
            $templateData['body_params'] = json_encode($bodyParams);

            // For authentication templates with URL buttons, we need to set footer_params (button params)
            // The button URL contains otp{{1}} which needs the OTP code
            $buttonsData = json_decode($template->buttons_data, true);
            if (! empty($buttonsData)) {
                foreach ($buttonsData as $button) {
                    if ($button['type'] === 'URL' && str_contains($button['url'], 'otp{{')) {
                        // Set footer params with the OTP code for the button
                        $templateData['footer_params'] = json_encode([$code]);
                        break;
                    }
                }
            }

            // Also set empty footer_params if not set to avoid parseText errors
            if (! isset($templateData['footer_params'])) {
                $templateData['footer_params'] = '[]';
            }

            // Store OTP in database for verification
            $purpose = $request->input('purpose', 'authentication');
            $otp = OtpVerification::createOtp(
                $tenantId,
                $phoneNumber,
                $code,
                $purpose,
                $expiryMinutes,
                $templateName,
                5, // max attempts
                ['contact_id' => $contact->id]
            );

            // Send authentication template
            $result = $this->sendTemplate(
                $phoneNumber,
                $templateData,
                'authentication',
                null,
                true
            );

            if (! $result['status']) {
                // Mark OTP as failed if message sending fails
                $otp->update(['is_verified' => true, 'verified_at' => now()]);

                return ApiResponse::error(
                    ErrorCode::MESSAGE_SEND_FAILED,
                    'Failed to send authentication template',
                    ['error' => $result['message'] ?? 'Unknown error']
                );
            }

            return ApiResponse::success([
                'message_id' => $result['data']->messages[0]->id ?? null,
                'contact_id' => $contact->id,
                'phone' => $phoneNumber,
                'template_name' => $templateName,
                'language' => $language,
                'code_sent' => true,
                'expiry_minutes' => $expiryMinutes,
                'expires_at' => $otp->expires_at->toIso8601String(),
                'status' => 'sent',
                'sent_at' => now()->toIso8601String(),
            ], 'Authentication code sent successfully. Please verify using /auth/verify endpoint.');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to send authentication template',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Verify OTP Code
     *
     * Verify an OTP code that was sent via WhatsApp authentication template.
     *
     * **Required Scope:** `messages:send`
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string'],
                'code' => ['required', 'string'],
                'purpose' => ['nullable', 'string', 'max:50'],
            ], [
                'phone.required' => 'Phone number is required.',
                'code.required' => 'Verification code is required.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $phoneNumber = $this->cleanPhoneNumber($request->input('phone'));
            $code = $request->input('code');
            $purpose = $request->input('purpose', 'authentication');

            // Rate limit verification attempts
            $rateLimitKey = "otp-verify:{$tenantId}:{$phoneNumber}";
            if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);

                return ApiResponse::error(
                    ErrorCode::RATE_LIMIT_EXCEEDED,
                    "Too many verification attempts. Please try again in {$seconds} seconds.",
                    ['retry_after' => $seconds]
                );
            }

            RateLimiter::hit($rateLimitKey, 60); // 10 attempts per minute

            // Verify OTP
            $result = OtpVerification::verify($tenantId, $phoneNumber, $code, $purpose);

            if (! $result['success']) {
                return ApiResponse::error(
                    ErrorCode::VALIDATION_ERROR,
                    $result['message'],
                    array_merge(['error_code' => $result['error_code']], $result)
                );
            }

            // Clear rate limiter on successful verification
            RateLimiter::clear($rateLimitKey);

            return ApiResponse::success(
                $result['data'],
                'OTP verified successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to verify OTP',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Resend OTP Code
     *
     * Resend an OTP code to the same phone number.
     *
     * **Required Scope:** `messages:send`
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string'],
                'template_name' => ['required', 'string'],
                'language' => ['nullable', 'string', 'max:10'],
                'purpose' => ['nullable', 'string', 'max:50'],
                'expiry_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            ], [
                'phone.required' => 'Phone number is required.',
                'template_name.required' => 'Template name is required.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $phoneNumber = $this->cleanPhoneNumber($request->input('phone'));

            // Rate limit resend attempts
            $rateLimitKey = "otp-resend:{$tenantId}:{$phoneNumber}";
            if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);

                return ApiResponse::error(
                    ErrorCode::RATE_LIMIT_EXCEEDED,
                    "Too many resend attempts. Please try again in {$seconds} seconds.",
                    ['retry_after' => $seconds]
                );
            }

            RateLimiter::hit($rateLimitKey, 300); // 3 attempts per 5 minutes

            // Generate new code
            $code = OtpVerification::generateCode(6);

            // Call sendAuthenticationTemplate with the new code
            $request->merge([
                'code' => $code,
                'phone' => $phoneNumber,
            ]);

            return $this->sendAuthenticationTemplate($request);
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to resend OTP',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Check OTP Status
     *
     * Check the status of an OTP without verifying it.
     *
     * **Required Scope:** `messages:send`
     */
    public function checkOtpStatus(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string'],
                'purpose' => ['nullable', 'string', 'max:50'],
            ], [
                'phone.required' => 'Phone number is required.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $phoneNumber = $this->cleanPhoneNumber($request->input('phone'));
            $purpose = $request->input('purpose', 'authentication');

            $otp = OtpVerification::activeFor($tenantId, $phoneNumber, $purpose)->first();

            if (! $otp) {
                return ApiResponse::success([
                    'has_active_otp' => false,
                    'message' => 'No active OTP found',
                ], 'OTP status checked');
            }

            return ApiResponse::success([
                'has_active_otp' => true,
                'expires_at' => $otp->expires_at->toIso8601String(),
                'expires_in_seconds' => now()->diffInSeconds($otp->expires_at, false),
                'is_expired' => $otp->isExpired(),
                'verification_attempts' => $otp->verification_attempts,
                'max_attempts' => $otp->max_attempts,
                'attempts_remaining' => max(0, $otp->max_attempts - $otp->verification_attempts),
                'created_at' => $otp->created_at->toIso8601String(),
            ], 'OTP status retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to check OTP status',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * List Authentication Templates
     *
     * Get all approved AUTHENTICATION category templates available for sending OTP and verification codes.
     *
     * **Required Scope:** `templates:read`
     */
    public function listAuthenticationTemplates(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $query = WhatsappTemplate::where('tenant_id', $tenantId)
                ->where('category', 'AUTHENTICATION')
                ->where('status', 'APPROVED')
                ->orderBy('template_name');

            $templates = \Modules\ApiWebhookManager\Services\V2\QueryBuilderService::for($query, $request)
                ->allowedFilters(['language', 'template_name'])
                ->allowedSorts(['template_name', 'created_at', 'language'])
                ->searchable(['template_name', 'body_data'])
                ->apply()
                ->paginate();

            return ApiResponse::paginated($templates, function ($template) {
                return [
                    'id' => $template->id,
                    'template_name' => $template->template_name,
                    'language' => $template->language,
                    'body_text' => $template->body_data,
                    'body_params_count' => $template->body_params_count,
                    'header_format' => $template->header_data_format,
                    'header_text' => $template->header_data_text,
                    'footer_text' => $template->footer_data,
                    'status' => $template->status,
                    'created_at' => $template->created_at?->toIso8601String(),
                ];
            });
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch authentication templates',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get WhatsApp Connection Settings
     *
     * Helper method to get WhatsApp settings for tenant
     */
    protected function getWhatsAppConnectionSettings($tenantId): ?array
    {
        $settings = tenant_settings_by_group('whatsapp', $tenantId);

        if (empty($settings['wm_access_token']) || empty($settings['wm_default_phone_number_id'])) {
            return null;
        }

        return $settings;
    }

    /**
     * Clean Phone Number
     *
     * Remove all non-numeric characters except + and convert to international format
     */
    private function cleanPhoneNumber($phone): string
    {
        // Remove all non-numeric characters except +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);

        // Remove + if present at the start
        if (str_starts_with($cleanPhone, '+')) {
            $cleanPhone = substr($cleanPhone, 1);
        }

        return $cleanPhone;
    }
}

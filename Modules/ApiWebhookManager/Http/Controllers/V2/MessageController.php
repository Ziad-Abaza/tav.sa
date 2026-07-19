<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Chat;
use App\Models\Tenant\ChatMessage;
use App\Models\Tenant\Contact;
use App\Services\FeatureService;
use App\Traits\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Requests\V2\Message\SendTextMessageRequest;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class MessageController extends Controller
{
    use WhatsApp {
        sendTemplate as sendTemplateFromTrait;
    }

    public function __construct(
        public FeatureService $featureService
    ) {}

    public function sendText(SendTextMessageRequest $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $this->setWaTenantId($tenantId);

            $phoneNumber = $this->cleanPhoneNumber($request->input('phone'));
            $message = $request->input('message');
            $contactId = $request->input('contact_id');

            // Use common method for contact handling
            $contactResult = $this->findOrCreateContact(
                $phoneNumber,
                $contactId,
                $tenantId,
                $subdomain
            );

            if ($contactResult instanceof JsonResponse) {
                return $contactResult;
            }

            $contact = $contactResult;

            $whatsappSettings = $this->getWhatsAppConnectionSettings($tenantId);

            if (! $whatsappSettings) {
                return ApiResponse::error(
                    ErrorCode::WHATSAPP_NOT_CONFIGURED,
                    'WhatsApp connection is not configured for this account'
                );
            }

            $parsedMessage = $this->parseMessageText([
                'rel_type' => $contact->type,
                'rel_id' => $contact->id,
                'reply_text' => $message,
                'tenant_id' => $tenantId,
            ]);

            $finalMessage = $parsedMessage['reply_text'] ?? $message;

            $messageResponse = $this->sendWhatsAppMessage(
                $phoneNumber,
                $finalMessage,
                $whatsappSettings
            );

            if (! $messageResponse['success']) {
                return ApiResponse::error(
                    ErrorCode::MESSAGE_SEND_FAILED,
                    'Failed to send message',
                    ['error' => $messageResponse['error'] ?? 'Unknown error']
                );
            }

            $chat = $this->createOrUpdateChatInteraction(
                $phoneNumber,
                $contact,
                $whatsappSettings,
                $finalMessage,
                $tenantId,
                $subdomain
            );

            $chatMessage = $this->saveChatMessage(
                $chat,
                $finalMessage,
                $messageResponse['message_id'] ?? null,
                'text',
                null,
                $tenantId,
                $subdomain
            );

            return ApiResponse::success([
                'message_id' => $messageResponse['message_id'] ?? null,
                'contact_id' => $contact->id,
                'phone' => $phoneNumber,
                'message' => $finalMessage,
                'status' => 'sent',
                'sent_at' => now()->toIso8601String(),
                'chat_id' => $chat->id ?? null,
                'chat_message_id' => $chatMessage->id ?? null,
            ], 'Message sent successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to send message',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function sendTemplate(Request $request): JsonResponse
    {
        $subdomain = $request->get('tenant_subdomain');
        $tenantId = $request->get('tenant_id');

        try {
            $contactTable = \App\Models\Tenant\Contact::fromTenant($subdomain)->getTable();

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'phone' => ['required', 'string'],
                'template_name' => ['required', 'string'],
                'language' => ['required', 'string', 'max:10'],
                'contact_id' => ['nullable', 'integer', "exists:{$contactTable},id"],

                // Header media files/URLs
                'header_image_url' => 'nullable|url',
                'header_image_file' => 'nullable|file|max:5120',
                'header_video_url' => 'nullable|url',
                'header_video_file' => 'nullable|file|max:16384',
                'header_document_url' => 'nullable|url',
                'header_document_file' => 'nullable|file|max:102400',
                'header_document_name' => 'nullable|string|max:255',
                'header_field_1' => 'nullable|string',

                // Body fields
                'field_1' => 'nullable|string',
                'field_2' => 'nullable|string',
                'field_3' => 'nullable|string',
                'field_4' => 'nullable|string',
                'field_5' => 'nullable|string',
                'field_6' => 'nullable|string',
                'field_7' => 'nullable|string',
                'field_8' => 'nullable|string',
                'field_9' => 'nullable|string',
                'field_10' => 'nullable|string',

                // Button parameters
                'button_0' => 'nullable|string',
                'button_1' => 'nullable|string',
                'button_2' => 'nullable|string',

                // OTP/Authentication specific fields
                'auto_generate_otp' => 'nullable|boolean',
                'otp_field_number' => 'nullable|integer|min:6|max:6',
            ], [
                'phone.required' => 'Phone number is required.',
                'template_name.required' => 'Template name is required.',
                'language.required' => 'Template language is required.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $this->setWaTenantId($tenantId);

            $phoneNumber = $this->cleanPhoneNumber($request->input('phone'));
            $templateName = $request->input('template_name');
            $language = $request->input('language');
            $contactId = $request->input('contact_id');

            // 1. FETCH AND VALIDATE TEMPLATE
            $template = \App\Models\Tenant\WhatsappTemplate::where('tenant_id', $tenantId)
                ->where('template_name', $templateName)
                ->where('language', $language)
                ->where('status', 'APPROVED')
                ->first();

            if (! $template) {
                return ApiResponse::notFound(
                    "Template '{$templateName}' with language '{$language}' not found or not approved",
                    'template'
                );
            }

            // 2. AUTO-GENERATE OTP IF REQUESTED (for AUTHENTICATION templates)
            if ($request->boolean('auto_generate_otp', false)) {
                $otpFieldNumber = $request->integer('otp_field_number', 1);
                $fieldKey = "field_{$otpFieldNumber}";

                // Only generate if field is not already provided
                if (! $request->filled($fieldKey)) {
                    $otpLength = $request->integer('otp_length', 6);
                    $generatedOtpCode = $this->generateOtpCode($otpLength ?? 6);

                    // Merge generated OTP into request
                    $request->merge([$fieldKey => $generatedOtpCode]);

                    // Log OTP generation for security audit
                    whatsapp_log('Auto-Generated OTP', 'info', [
                        'template_name' => $templateName,
                        'phone' => $phoneNumber,
                        'otp_length' => $otpLength ?? 6,
                        'field_number' => $otpFieldNumber,
                        'template_category' => $template->category,
                    ], null, $tenantId);
                }
            }

            // 3. VALIDATE TEMPLATE REQUIREMENTS
            $templateValidation = $this->validateTemplateRequirements($template, $request);
            if (! $templateValidation['valid']) {
                return ApiResponse::validationError($templateValidation['errors']);
            }

            // 4. HANDLE HEADER MEDIA (if template requires it and media is provided)
            $headerMediaResult = null;
            if (in_array($template->header_data_format, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
                // Only handle media upload if user provided media (template might already have media)
                $mediaType = strtolower($template->header_data_format);
                $hasMediaInput = $request->hasFile("header_{$mediaType}_file") || $request->filled("header_{$mediaType}_url");

                if ($hasMediaInput) {
                    $headerMediaResult = $this->handleTemplateHeaderMedia($template, $request, $tenantId);
                    if (! $headerMediaResult['success']) {
                        return ApiResponse::error(
                            ErrorCode::VALIDATION_ERROR,
                            $headerMediaResult['message'],
                            $headerMediaResult['errors'] ?? []
                        );
                    }
                }
            }

            // 5. FIND OR CREATE CONTACT
            $contactResult = $this->findOrCreateContact(
                $phoneNumber,
                $contactId,
                $tenantId,
                $subdomain
            );

            if ($contactResult instanceof JsonResponse) {
                return $contactResult;
            }

            $contact = $contactResult;

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

            // 6. EXTRACT AND PARSE TEMPLATE PARAMETERS
            $parsedParams = $this->extractAndParseTemplateParameters($request, $contact, $tenantId);

            // 7. PREPARE TEMPLATE DATA
            // Note: For templates with pre-uploaded media, we set header_data_format to empty
            // to prevent trait from building header component (WhatsApp uses pre-uploaded media)
            $hasNewMediaUpload = ! empty($headerMediaResult['filename']);
            $hasPreUploadedMedia = in_array($template->header_data_format, ['IMAGE', 'VIDEO', 'DOCUMENT']) && ! $hasNewMediaUpload;

            // Prepare whatsapp_media_ids for pre-uploaded media
            $whatsappMediaIds = null;
            if (! empty($headerMediaResult['whatsapp_media_id'])) {
                $whatsappMediaIds = ['header' => $headerMediaResult['whatsapp_media_id']];
            }

            $templateData = [
                'rel_type' => $contact->type,
                'rel_id' => $contact->id,
                'tenant_id' => $tenantId,
                'template_id' => $template->id,
                'template_name' => $template->template_name,
                'language' => $template->language,
                'category' => $template->category,
                'header_data_format' => $hasPreUploadedMedia ? '' : $template->header_data_format,
                'header_data_text' => $template->header_data_text,
                'body_data' => $template->body_data,
                'footer_data' => $template->footer_data,
                'buttons_data' => $template->buttons_data,
                'header_params_count' => $template->header_params_count ?? 0,
                'body_params_count' => $template->body_params_count ?? 0,
                'footer_params_count' => 0,
                'filename' => $headerMediaResult['filename'] ?? '',
                'filelink' => $headerMediaResult['filelink'] ?? '',
                'header_message' => $template->header_data_text,
                'body_message' => $template->body_data,
                'footer_message' => $template->footer_data,
                'whatsapp_media_ids' => $whatsappMediaIds,
            ];

            // Add parsed parameters in the format expected by parseText function
            if (! empty($parsedParams['header'])) {
                $templateData['header_params'] = json_encode($parsedParams['header']);
            }
            if (! empty($parsedParams['body'])) {
                $templateData['body_params'] = json_encode($parsedParams['body']);
            }
            if (! empty($parsedParams['footer'])) {
                $templateData['footer_params'] = json_encode($parsedParams['footer']);
            }

            // 8. SEND TEMPLATE MESSAGE USING WHATSAPP TRAIT
            $messageResponse = $this->sendTemplateViaWhatsApp(
                $phoneNumber,
                $templateData,
                $whatsappSettings
            );

            if (! $messageResponse['success']) {
                return ApiResponse::error(
                    ErrorCode::MESSAGE_SEND_FAILED,
                    'Failed to send template message',
                    ['error' => $messageResponse['error'] ?? 'Unknown error']
                );
            }

            $chat = $this->createOrUpdateChatInteraction(
                $phoneNumber,
                $contact,
                $whatsappSettings,
                "Template: {$templateName}",
                $tenantId,
                $subdomain
            );

            $this->storeTemplateMessageInChat(
                $templateData,
                $chat->id,
                $contact,
                'template_bot',
                $messageResponse['data'] ?? [],
                $tenantId,
                $subdomain,
                $headerMediaResult
            );

            return ApiResponse::success([
                'message_id' => $messageResponse['message_id'] ?? null,
                'contact_id' => $contact->id,
                'phone' => $phoneNumber,
                'template_name' => $templateName,
                'language' => $language,
                'status' => 'sent',
                'sent_at' => now()->toIso8601String(),
                'chat_id' => $chat->id ?? null,
                'chat_message_id' => $chatMessage->id ?? null,
            ], 'Template message sent successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to send template message',
                ['error' => $e->getMessage()]
            );
        }
    }

    // Storing template massage in chat
    private function storeTemplateMessageInChat($templateData, $interactionId, $contact, $type, $response, $tenant_id, $subdomain, $headerMediaResult = null)
    {
        try {
            // Parse template content with contact data - using the exact pattern you specified
            $header = parseText($templateData['rel_type'], 'header', $templateData);
            $body = parseText($templateData['rel_type'], 'body', $templateData);
            $footer = parseText($templateData['rel_type'], 'footer', $templateData);

            // Build buttons HTML
            $buttonHtml = '';
            if (! empty(json_decode($templateData['buttons_data']))) {
                $buttons = json_decode($templateData['buttons_data']);
                $buttonHtml = "<div class='flex flex-col mt-2 space-y-2'>";
                foreach ($buttons as $button) {
                    $buttonHtml .= "<button class='bg-gray-100 text-success-500 px-3 py-2 rounded-lg flex items-center justify-center text-xs space-x-2 w-full
                    dark:bg-gray-800 dark:text-success-400'>".e($button->text).'</button>';
                }
                $buttonHtml .= '</div>';
            }

            // Build header data based on format
            $headerData = '';

            // Use the filename from headerMediaResult if available, otherwise from templateData
            $filename = $headerMediaResult['filename'] ?? $templateData['filename'] ?? '';

            if (! empty($filename)) {
                $fileExtensions = get_meta_allowed_extension();
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $fileType = array_key_first(array_filter($fileExtensions, fn ($data) => in_array('.'.$extension, explode(', ', $data['extension']))));

                if ($templateData['header_data_format'] === 'IMAGE' && $fileType == 'image') {
                    $headerData = "<a href='".asset('storage/'.$filename)."' data-lightbox='image-group'>
                    <img src='".asset('storage/'.$filename)."' class='rounded-lg w-full mb-2'>
                </a>";
                } elseif ($templateData['header_data_format'] === 'DOCUMENT') {
                    $headerData = "<a href='".asset('storage/'.$filename)."' target='_blank' class='btn btn-secondary w-full'>".t('document').'</a>';
                } elseif ($templateData['header_data_format'] === 'VIDEO') {
                    $headerData = "<video src='".asset('storage/'.$filename)."' controls class='rounded-lg w-full'></video>";
                }
            }

            // Handle TEXT header format or empty format
            if ($templateData['header_data_format'] === 'TEXT' || $templateData['header_data_format'] === '' || empty($headerData)) {
                $headerData = "<span class='font-bold mb-3'>".nl2br(decodeWhatsAppSigns(e($header ?? ''))).'</span>';
            }

            // Build the complete chat message following your exact pattern
            $chat_message = [
                'interaction_id' => $interactionId,
                'sender_id' => get_tenant_setting_by_tenant_id('whatsapp', 'wm_default_phone_number', null, $tenant_id),
                'url' => $headerMediaResult['media_url'] ?? null,
                'message' => "
                $headerData
                <p>".nl2br(decodeWhatsAppSigns(e($body)))."</p>
                <span class='text-gray-500 text-sm'>".nl2br(decodeWhatsAppSigns(e($footer ?? '')))."</span>
                $buttonHtml
            ",
                'status' => 'sent',
                'time_sent' => now()->toDateTimeString(),
                'message_id' => $response->messages[0]->id ?? $response->messages[0]->id ?? null,
                'staff_id' => 0,
                'type' => 'text',
                'tenant_id' => $tenant_id,
                'is_read' => '1',
            ];

            $message_id = ChatMessage::fromTenant($subdomain)->insertGetId($chat_message);

            return $message_id;
        } catch (\Exception $e) {
            whatsapp_log('Error storing template message in chat', 'error', [
                'interaction_id' => $interactionId,
                'template_name' => $templateData['template_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ], $e, $tenant_id);

            return null;
        }
    }

    public function sendMedia(Request $request): JsonResponse
    {
        $subdomain = $request->get('tenant_subdomain');
        $tenantId = $request->get('tenant_id');

        try {
            $contactTable = \App\Models\Tenant\Contact::fromTenant($subdomain)->getTable();

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'phone' => 'required|string|min:10|max:15',
                'media_type' => 'required|string|in:image,document,video,audio',
                'media_url' => 'nullable|url|required_without:media_file',
                'media_file' => 'nullable|file|required_without:media_url',
                'caption' => 'nullable|string|max:1024',
                'filename' => 'nullable|string|max:255',
                'contact' => 'nullable',
                'contact.firstname' => 'nullable|string|max:255',
                'contact.lastname' => 'nullable|string|max:255',
                'contact.email' => 'nullable|email|max:191',
                'contact.country' => 'nullable|string|max:100',
                'contact.assigned_id' => 'nullable|integer',
                'contact.groups' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $this->setWaTenantId($tenantId);

            $phoneNumber = $this->cleanPhoneNumber($request->input('phone'));
            $mediaType = $request->input('media_type');
            $mediaUrl = $request->input('media_url');
            $caption = $request->input('caption');
            $filename = $request->input('filename');
            $contactId = $request->input('contact_id');

            // Image uploading
            $allowedMedia = get_meta_allowed_extension();

            $rules = $allowedMedia[$mediaType] ?? null;

            if (! $rules) {
                return ApiResponse::error(
                    ErrorCode::INVALID_MEDIA_TYPE,
                    'Invalid media type'
                );
            }

            // Convert allowed extensions string to array (remove dots & spaces)
            $allowedExtensions = array_map(function ($ext) {
                return strtolower(ltrim(trim($ext), '.'));
            }, explode(',', $rules['extension']));

            if ($request->hasFile('media_file')) {
                // Direct upload case
                $file = $request->file('media_file');
                $fileExt = strtolower($file->getClientOriginalExtension());
                $fileSizeMB = $file->getSize() / (1024 * 1024);

                if (! in_array($fileExt, $allowedExtensions)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Invalid file extension for {$mediaType}. Allowed: ".implode(', ', $allowedExtensions),
                    ], 422);
                }

                if ($fileSizeMB > $rules['size']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "File size exceeds limit for {$mediaType}. Max allowed is {$rules['size']} MB",
                    ], 422);
                }

                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                // Sanitize filename: remove special chars, replace spaces
                $cleanName = Str::slug($originalName, '_');

                // Append timestamp to ensure uniqueness
                $fileName = time().'_'.$cleanName.'.'.$fileExt;

                // Store the file
                $file->storeAs('whatsapp-attachments', $fileName, 'public');
                $mediaUrl = url('storage/whatsapp-attachments/'.$fileName);
            } else {
                $mediaUrl = $request->input('media_url');

                $fileContents = @file_get_contents($mediaUrl);
                if ($fileContents === false) {
                    whatsapp_log('Error downloading media file', 'error', [
                        'media_url' => $mediaUrl,
                    ], null, $tenantId);

                    return ApiResponse::error(
                        ErrorCode::INVALID_MEDIA_TYPE,
                        'Error downloading media file'
                    );
                }

                $pathInfo = pathinfo(parse_url($mediaUrl, PHP_URL_PATH));
                $originalName = $pathInfo['filename'] ?? 'whatsapp_file';
                $fileExt = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

                if (! in_array($fileExt, $allowedExtensions)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Invalid file extension for {$mediaType}. Allowed: ".implode(', ', $allowedExtensions),
                    ], 422);
                }

                $fileSizeBytes = 0;
                $headers = @get_headers($mediaUrl, 1);
                if ($headers && isset($headers['Content-Length'])) {
                    $fileSizeBytes = is_array($headers['Content-Length']) ? end($headers['Content-Length']) : $headers['Content-Length'];
                }
                $fileSizeMB = $fileSizeBytes / (1024 * 1024);

                if ($fileSizeMB > $rules['size']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "File size exceeds limit for {$mediaType}. Max allowed is {$rules['size']} MB",
                    ], 422);
                }

                // Sanitize filename
                $cleanName = \Illuminate\Support\Str::slug($originalName, '_');
                $fileName = time().'_'.$cleanName.($fileExt ? '.'.$fileExt : '');

                $storagePath = 'whatsapp-attachments/'.$fileName;
                \Illuminate\Support\Facades\Storage::disk('public')->put($storagePath, $fileContents);
            }

            // Use common method for contact handling
            $contactResult = $this->findOrCreateContact(
                $phoneNumber,
                $contactId,
                $tenantId,
                $subdomain
            );

            if ($contactResult instanceof JsonResponse) {
                return $contactResult;
            }

            $contact = $contactResult;

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

            if ($this->featureService->checkConversationLimit($contact->id, $tenantId, null, $contact->type)) {
                return ApiResponse::error(
                    ErrorCode::FEATURE_LIMIT_EXCEEDED,
                    'Conversation limit reached. Please upgrade your plan.'
                );
            }

            $messageResponse = $this->sendWhatsAppMediaMessage(
                $phoneNumber,
                $mediaType,
                $mediaUrl,
                $caption,
                $filename,
                $whatsappSettings
            );

            if (! $messageResponse['success']) {
                return ApiResponse::error(
                    ErrorCode::MESSAGE_SEND_FAILED,
                    'Failed to send media message',
                    ['error' => $messageResponse['error'] ?? 'Unknown error']
                );
            }

            $chat = $this->createOrUpdateChatInteraction(
                $phoneNumber,
                $contact,
                $whatsappSettings,
                $caption,
                $tenantId,
                $subdomain
            );

            $chatMessage = $this->saveMediaChatMessage(
                $chat,
                $mediaType,
                $fileName,
                $caption,
                $filename,
                $messageResponse['message_id'],
                $tenantId,
                $subdomain
            );

            return ApiResponse::success([
                'message_id' => $messageResponse['message_id'] ?? null,
                'contact_id' => $contact->id,
                'phone' => $phoneNumber,
                'media_type' => $mediaType,
                'media_url' => $mediaUrl,
                'caption' => $caption,
                'filename' => $filename,
                'status' => 'sent',
                'sent_at' => now()->toIso8601String(),
                'chat_id' => $chat->id ?? null,
                'chat_message_id' => $chatMessage->id ?? null,
            ], 'Media message sent successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to send media message',
                ['error' => $e->getMessage()]
            );
        }
    }

    private function saveMediaChatMessage($chatInteraction, $mediaType, $mediaUrl, $caption, $filename, $messageId, $tenant_id, $subdomain)
    {
        try {
            if (! $chatInteraction) {
                return null;
            }

            // Build formatted message for chat display
            // $formattedMessage = $this->buildFormattedMediaMessage($mediaType, $mediaUrl, $caption, $filename);

            $chatMessage = \App\Models\Tenant\ChatMessage::fromTenant($subdomain)->create([
                'tenant_id' => $tenant_id,
                'interaction_id' => $chatInteraction->id,
                'sender_id' => $chatInteraction->wa_no,
                'message' => $caption,
                'message_id' => $messageId,
                'type' => $mediaType,
                'url' => $mediaUrl,
                'staff_id' => null, // API messages don't have staff_id
                'status' => 'sent',
                'time_sent' => now(),
                'created_at' => now(),
                'updated_at' => now(),
                'is_read' => 1,
            ]);

            return $chatMessage;
        } catch (\Exception $e) {
            whatsapp_log('Error saving media chat message', 'error', [
                'chat_id' => $chatInteraction->id ?? null,
                'message_id' => $messageId,
                'media_type' => $mediaType,
                'media_url' => $mediaUrl,
                'error' => $e->getMessage(),
            ], $e, $tenant_id);

            return null;
        }
    }

    private function buildFormattedMediaMessage($mediaType, $mediaUrl, $caption, $filename)
    {
        $message = '';

        switch ($mediaType) {
            case 'image':
                $message = "<a href='".htmlspecialchars($mediaUrl)."' data-lightbox='image-group'>";
                $message .= "<img src='".htmlspecialchars($mediaUrl)."' class='rounded-lg w-full max-w-sm mb-2' alt='Shared Image'>";
                $message .= '</a>';
                break;

            case 'video':
                $message = "<video src='".htmlspecialchars($mediaUrl)."' controls class='rounded-lg w-full max-w-sm'>";
                $message .= 'Your browser does not support the video tag.';
                $message .= '</video>';
                break;

            case 'audio':
                $message = "<audio controls class='w-64'>";
                $message .= "<source src='".htmlspecialchars($mediaUrl)."' type='audio/mpeg'>";
                $message .= 'Your browser does not support the audio element.';
                $message .= '</audio>';
                break;

            case 'document':
                $displayName = $filename ?: 'Document';
                $message = "<a href='".htmlspecialchars($mediaUrl)."' target='_blank' class='bg-gray-100 text-success-500 px-3 py-2 rounded-lg flex items-center justify-center text-xs space-x-2 w-full dark:bg-gray-800 dark:text-success-400'>";
                $message .= "<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>";
                $message .= "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>";
                $message .= '</svg>';
                $message .= '<span>'.htmlspecialchars($displayName).'</span>';
                $message .= '</a>';
                break;

            default:
                $message = '<p>Media file: '.ucfirst($mediaType).'</p>';
                break;
        }

        // Add caption if provided
        if ($caption) {
            $message .= "\n<p class='mt-2 text-sm'>".nl2br(htmlspecialchars($caption)).'</p>';
        }

        return $message;
    }

    public function sendInteractive(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $contactTable = \App\Models\Tenant\Contact::fromTenant($subdomain)->getTable();

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'phone' => ['required', 'string'],
                'type' => ['required', 'string', 'in:button,list'],
                'body_text' => ['required', 'string', 'max:1024'],
                'header_text' => ['nullable', 'string', 'max:60'],
                'footer_text' => ['nullable', 'string', 'max:60'],
                'buttons' => ['required_if:type,button', 'array', 'max:3'],
                'buttons.*.id' => ['required', 'string'],
                'buttons.*.title' => ['required', 'string', 'max:20'],
                'list_button_text' => ['required_if:type,list', 'string', 'max:20'],
                'sections' => ['required_if:type,list', 'array', 'max:10'],
                'sections.*.title' => ['required', 'string'],
                'sections.*.rows' => ['required', 'array'],
                'sections.*.rows.*.id' => ['required', 'string'],
                'sections.*.rows.*.title' => ['required', 'string', 'max:24'],
                'sections.*.rows.*.description' => ['nullable', 'string', 'max:72'],
                'contact_id' => ['nullable', 'integer', "exists:{$contactTable},id"],
            ], [
                'phone.required' => 'Phone number is required.',
                'type.required' => 'Interactive message type is required.',
                'type.in' => 'Type must be either button or list.',
                'body_text.required' => 'Body text is required.',
                'buttons.required_if' => 'Buttons are required for button type messages.',
                'list_button_text.required_if' => 'List button text is required for list type messages.',
                'sections.required_if' => 'Sections are required for list type messages.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $this->setWaTenantId($tenantId);

            $phoneNumber = $this->cleanPhoneNumber($request->input('phone'));
            $type = $request->input('type');
            $contactId = $request->input('contact_id');

            // Use common method for contact handling
            $contactResult = $this->findOrCreateContact(
                $phoneNumber,
                $contactId,
                $tenantId,
                $subdomain
            );

            if ($contactResult instanceof JsonResponse) {
                return $contactResult;
            }

            $contact = $contactResult;

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

            if ($this->featureService->checkConversationLimit($contact->id, $tenantId, null, $contact->type)) {
                return ApiResponse::error(
                    ErrorCode::FEATURE_LIMIT_EXCEEDED,
                    'Conversation limit reached. Please upgrade your plan.'
                );
            }

            $interactiveData = [
                'type' => $type,
                'body_text' => $request->input('body_text'),
                'header_text' => $request->input('header_text'),
                'footer_text' => $request->input('footer_text'),
            ];

            if ($type === 'button') {
                $interactiveData['buttons'] = $request->input('buttons');
            } else {
                $interactiveData['list_button_text'] = $request->input('list_button_text');
                $interactiveData['sections'] = $request->input('sections');
            }

            $messageResponse = $this->sendWhatsAppInteractiveMessage(
                $phoneNumber,
                $interactiveData,
                $whatsappSettings
            );

            if (! $messageResponse['success']) {
                return ApiResponse::error(
                    ErrorCode::MESSAGE_SEND_FAILED,
                    'Failed to send interactive message',
                    ['error' => $messageResponse['error'] ?? 'Unknown error']
                );
            }

            $chat = $this->createOrUpdateChatInteraction(
                $phoneNumber,
                $contact,
                $whatsappSettings,
                $request->input('body_text'),
                $tenantId,
                $subdomain
            );

            $chatMessage = $this->saveChatMessage(
                $chat,
                $request->input('body_text'),
                $messageResponse['message_id'] ?? null,
                'interactive',
                null,
                $tenantId,
                $subdomain
            );

            return ApiResponse::success([
                'message_id' => $messageResponse['message_id'] ?? null,
                'contact_id' => $contact->id,
                'phone' => $phoneNumber,
                'type' => $type,
                'status' => 'sent',
                'sent_at' => now()->toIso8601String(),
                'chat_id' => $chat->id ?? null,
                'chat_message_id' => $chatMessage->id ?? null,
            ], 'Interactive message sent successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to send interactive message',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Upload media to WhatsApp Cloud API and get media ID
     *
     * @param  string  $filename  Relative path to file (e.g., 'whatsapp-templates/file.jpg')
     * @param  string  $mediaType  Media type (image, video, document)
     * @param  int  $tenantId  Tenant ID
     * @return string|null Returns media_id from WhatsApp or null on failure
     */
    protected function uploadMediaToWhatsApp(string $filename, string $mediaType, int $tenantId): ?string
    {
        try {
            $filePath = storage_path('app/public/'.$filename);

            if (! file_exists($filePath)) {
                whatsapp_log('Media file not found for WhatsApp upload', 'error', [
                    'filename' => $filename,
                    'filepath' => $filePath,
                ], null, $tenantId);

                return null;
            }

            $this->setWaTenantId($tenantId);
            $whatsappCloudApi = $this->loadConfig();

            $response = $whatsappCloudApi->uploadMedia($filePath);
            $data = json_decode($response->body(), true);

            if (isset($data['id'])) {
                whatsapp_log('Media uploaded to WhatsApp Cloud API successfully', 'info', [
                    'filename' => $filename,
                    'media_id' => $data['id'],
                    'media_type' => $mediaType,
                ], null, $tenantId);

                return $data['id'];
            }

            whatsapp_log('WhatsApp media upload failed - no ID returned', 'error', [
                'filename' => $filename,
                'response' => $data,
            ], null, $tenantId);

            return null;
        } catch (\Exception $e) {
            whatsapp_log('Error uploading media to WhatsApp Cloud API', 'error', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ], $e, $tenantId);

            return null;
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $query = \App\Models\Tenant\ChatMessage::fromTenant($subdomain)
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc');

            $messages = \Modules\ApiWebhookManager\Services\V2\QueryBuilderService::for($query, $request)
                ->allowedFilters(['type', 'status', 'interaction_id', 'direction'])
                ->allowedSorts(['created_at', 'updated_at'])
                ->searchable(['message'])
                ->apply()
                ->paginate();

            return ApiResponse::paginated($messages, function ($message) {
                return [
                    'id' => $message->id,
                    'interaction_id' => $message->interaction_id ?? null,
                    'sender_id' => $message->sender_id ?? null,
                    'type' => $message->type ?? null,
                    'message' => $message->message ?? $message->message_text ?? null,
                    'status' => $message->status ?? null,
                    'message_id' => $message->message_id ?? $message->whatsapp_message_id ?? null,
                    'sent_at' => $message->time_sent?->toIso8601String() ?? $message->created_at?->toIso8601String(),
                    'is_read' => $message->is_read ?? false,
                ];
            });
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch messages',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $message = \App\Models\Tenant\ChatMessage::fromTenant($subdomain)
                ->where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $message) {
                return ApiResponse::notFound('Message not found', 'message');
            }

            return ApiResponse::success([
                'id' => $message->id,
                'interaction_id' => $message->interaction_id ?? null,
                'sender_id' => $message->sender_id ?? null,
                'type' => $message->type ?? null,
                'message' => $message->message ?? null,
                'status' => $message->status ?? null,
                'status_message' => $message->status_message ?? null,
                'message_id' => $message->message_id ?? null,
                'ref_message_id' => $message->ref_message_id ?? null,
                'url' => $message->url ?? null,
                'sent_at' => $message->time_sent?->toIso8601String() ?? $message->created_at?->toIso8601String(),
                'is_read' => $message->is_read ?? false,
                'staff_id' => $message->staff_id ?? null,
            ], 'Message retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch message',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Find existing contact or create a new one
     *
     * @param  string  $phoneNumber  Cleaned phone number (without +)
     * @param  int|null  $contactId  Optional contact ID to find specific contact
     * @param  int  $tenantId  Tenant ID
     * @param  string  $subdomain  Tenant subdomain
     * @return Contact|JsonResponse Returns Contact on success, JsonResponse on error
     */
    private function findOrCreateContact(
        string $phoneNumber,
        ?int $contactId,
        int $tenantId,
        string $subdomain
    ): Contact|JsonResponse {
        $contact = null;

        // Try to find contact by ID first if provided
        if ($contactId) {
            $contact = Contact::fromTenant($subdomain)
                ->where('tenant_id', $tenantId)
                ->where('id', $contactId)
                ->where('phone', '+'.$phoneNumber)
                ->first();

            if (! $contact) {
                return ApiResponse::notFound('Contact not found', 'contact');
            }

            return $contact;
        }

        // Try to find contact by phone number
        $contact = Contact::fromTenant($subdomain)
            ->where('tenant_id', $tenantId)
            ->where('phone', '+'.$phoneNumber)
            ->first();

        // Return existing contact if found
        if ($contact) {
            return $contact;
        }

        // Contact doesn't exist, check if we can create a new one
        if ($this->featureService->hasReachedLimit('contacts', Contact::class)) {
            $limit = $this->featureService->getLimit('contacts');
            $current = $this->featureService->getCurrentUsage('contacts');

            return ApiResponse::featureLimitExceeded('contacts', $current, $limit);
        }

        // Get default settings for contact creation
        $defaultStatusId = get_tenant_setting_by_tenant_id('whats-mark', 'lead_status', null, $tenantId);
        $defaultSourceId = get_tenant_setting_by_tenant_id('whats-mark', 'lead_source', null, $tenantId);
        $defaultAssigneeId = get_tenant_setting_by_tenant_id('whats-mark', 'lead_assigned_to', null, $tenantId);

        // Validate required settings are configured
        if (! $defaultStatusId || ! $defaultSourceId) {
            return ApiResponse::error(
                ErrorCode::VALIDATION_ERROR,
                'Cannot auto-create contact. Please configure default lead status and source in Application settings, or provide contact_id.',
                [
                    'missing_config' => array_filter([
                        'lead_status' => ! $defaultStatusId ? 'Lead status not configured' : null,
                        'lead_source' => ! $defaultSourceId ? 'Lead source not configured' : null,
                    ]),
                ]
            );
        }

        // Create new contact
        $contact = Contact::fromTenant($subdomain)->create([
            'tenant_id' => $tenantId,
            'firstname' => $phoneNumber,
            'lastname' => '',
            'phone' => '+'.$phoneNumber,
            'type' => 'lead',
            'status_id' => $defaultStatusId,
            'source_id' => $defaultSourceId,
            'addedfrom' => $defaultAssigneeId,
            'assigned_id' => $defaultAssigneeId,
        ]);

        // Track usage for the new contact
        $this->featureService->trackUsage('contacts');

        return $contact;
    }

    private function cleanPhoneNumber($phone)
    {
        // Remove all non-numeric characters except +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);

        // Remove + if present at the start
        if (str_starts_with($cleanPhone, '+')) {
            $cleanPhone = substr($cleanPhone, 1);
        }

        return $cleanPhone;
    }

    private function createOrUpdateChatInteraction($phoneNumber, $contact, $whatsappSettings, $message, $tenantId, $subdomain)
    {
        try {
            $chat = \App\Models\Tenant\Chat::fromTenant($subdomain)
                ->where('receiver_id', $phoneNumber)
                ->where('type', $contact->type)
                ->where('type_id', $contact->id)
                ->first();

            if (! $chat) {
                $chat = \App\Models\Tenant\Chat::fromTenant($subdomain)->create([
                    'tenant_id' => $tenantId,
                    'receiver_id' => $phoneNumber,
                    'wa_no' => $whatsappSettings['wm_default_phone_number'] ?? $phoneNumber,
                    'wa_no_id' => $whatsappSettings['wm_default_phone_number_id'] ?? null,
                    'name' => trim(($contact->firstname ?? '').' '.($contact->lastname ?? '')),
                    'type' => $contact->type,
                    'type_id' => $contact->id,
                    'last_message' => $message,
                    'last_msg_time' => now(),
                    'time_sent' => now(),
                ]);
            } else {
                $chat->update([
                    'last_message' => $message,
                    'last_msg_time' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $chat;
        } catch (\Exception $e) {
            whatsapp_log('Error creating/updating chat interaction', 'error', [
                'phone' => $phoneNumber,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ], $e, $tenantId);

            return null;
        }
    }

    private function saveChatMessage($chat, $message, $messageId, $type, $url, $tenantId, $subdomain)
    {
        try {
            if (! $chat) {
                return null;
            }

            $chatMessage = \App\Models\Tenant\ChatMessage::fromTenant($subdomain)->create([
                'tenant_id' => $tenantId,
                'interaction_id' => $chat->id,
                'sender_id' => $chat->wa_no ?? $chat->receiver_id,
                'message' => $message,
                'message_id' => $messageId,
                'type' => $type,
                'url' => $url,
                'staff_id' => null,
                'status' => 'sent',
                'time_sent' => now(),
                'is_read' => true,
            ]);

            return $chatMessage;
        } catch (\Exception $e) {
            whatsapp_log('Error saving chat message', 'error', [
                'chat_id' => $chat->id ?? null,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ], $e, $tenantId);

            return null;
        }
    }

    private function getWhatsAppConnectionSettings($tenant_id)
    {
        try {
            // Get tenant-specific WhatsApp settings
            $settings = tenant_settings_by_group('whatsapp', $tenant_id);
            if (empty($settings['wm_business_account_id']) || empty($settings['wm_access_token'])) {
                return null;
            }

            return $settings;
        } catch (\Exception $e) {
            whatsapp_log('Error getting WhatsApp settings', 'error', [
                'tenant_id' => $tenant_id,
                'error' => $e->getMessage(),
            ], $e, $tenant_id);

            return null;
        }
    }

    private function parseMessageText($data)
    {
        // Use existing parseMessageText function if available
        if (function_exists('parseMessageText')) {
            return parseMessageText($data);
        }

        // Fallback basic parsing
        $message = $data['reply_text'] ?? '';

        return ['reply_text' => $message];
    }

    private function sendWhatsAppMessage($phoneNumber, $message, $whatsappSettings)
    {
        try {
            $whatsapp = new WhatsAppCloudApi([
                'from_phone_number_id' => $whatsappSettings['wm_default_phone_number_id'],
                'access_token' => $whatsappSettings['wm_access_token'],
            ]);

            $response = $whatsapp->sendTextMessage($phoneNumber, $message, true);
            $responseData = $response->decodedBody();

            if (isset($responseData['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'],
                    'response' => $responseData,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No message ID in response',
                    'response' => $responseData,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception' => $e,
            ];
        }
    }

    private function validateTemplateRequirements($template, Request $request)
    {
        $errors = [];
        $fieldMissing = [];

        // 1. HEADER VALIDATION
        if ($template->header_data_format) {
            switch ($template->header_data_format) {
                case 'TEXT':
                    if ($template->header_params_count > 0) {
                        if (! $request->filled('header_field_1')) {
                            $fieldMissing[] = 'header_field_1 is required for TEXT header template with parameters';
                        }
                    }
                    break;

                case 'IMAGE':
                    if (! $request->filled('header_image_url') && ! $request->hasFile('header_image_file')) {
                        $fieldMissing[] = 'header_image_url or header_image_file is required for IMAGE header template';
                    }
                    break;

                case 'VIDEO':
                    if (! $request->filled('header_video_url') && ! $request->hasFile('header_video_file')) {
                        $fieldMissing[] = 'header_video_url or header_video_file is required for VIDEO header template';
                    }
                    break;

                case 'DOCUMENT':
                    if (! $request->filled('header_document_url') && ! $request->hasFile('header_document_file')) {
                        $fieldMissing[] = 'header_document_url or header_document_file is required for DOCUMENT header template';
                    }
                    break;
            }
        }

        // 2. BODY VALIDATION - Check required parameters
        if ($template->body_params_count > 0) {
            for ($i = 1; $i <= $template->body_params_count; $i++) {
                if (! $request->filled("field_{$i}")) {
                    $fieldMissing[] = "field_{$i} is required (body parameter {$i})";
                }
            }
        }

        if (! empty($fieldMissing)) {
            $errors['template_fields'] = $fieldMissing;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function handleTemplateHeaderMedia($template, Request $request, $tenantId)
    {
        $mediaType = strtolower($template->header_data_format);
        $allowedMedia = get_meta_allowed_extension();

        $rules = $allowedMedia[$mediaType] ?? null;
        if (! $rules) {
            return [
                'success' => false,
                'message' => "Invalid header media type: {$mediaType}",
                'errors' => ['header_media' => ["Unsupported media type: {$mediaType}"]],
            ];
        }

        $allowedExtensions = array_map(function ($ext) {
            return strtolower(ltrim(trim($ext), '.'));
        }, explode(',', $rules['extension']));

        $mediaUrl = null;
        $fileName = null;
        $fileExt = null;

        try {
            $fileKey = "header_{$mediaType}_file";
            $urlKey = "header_{$mediaType}_url";

            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $fileExt = strtolower($file->getClientOriginalExtension());
                $fileSizeMB = $file->getSize() / (1024 * 1024);

                if (! in_array($fileExt, $allowedExtensions)) {
                    return [
                        'success' => false,
                        'message' => "Invalid file extension for {$mediaType}",
                        'errors' => ['header_media' => ['Allowed: '.implode(', ', $allowedExtensions)]],
                    ];
                }

                if ($fileSizeMB > $rules['size']) {
                    return [
                        'success' => false,
                        'message' => "File size exceeds {$rules['size']} MB limit",
                        'errors' => ['header_media' => ["Max size: {$rules['size']} MB"]],
                    ];
                }

                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $cleanName = \Illuminate\Support\Str::slug($originalName, '_');
                $fileName = 'template_header_'.time().'_'.$cleanName.'.'.$fileExt;

                $file->storeAs('whatsapp-templates', $fileName, 'public');
                $mediaUrl = url('storage/whatsapp-templates/'.$fileName);

                // Upload to WhatsApp Cloud API immediately
                $whatsappMediaId = $this->uploadMediaToWhatsApp('whatsapp-templates/'.$fileName, $mediaType, $tenantId);
            } elseif ($request->has($urlKey)) {
                $mediaUrl = $request->input($urlKey);

                $pathInfo = pathinfo(parse_url($mediaUrl, PHP_URL_PATH));
                $fileExt = strtolower($pathInfo['extension'] ?? '');

                if ($fileExt && ! in_array($fileExt, $allowedExtensions)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid file extension in URL',
                        'errors' => ['header_media' => ['Allowed: '.implode(', ', $allowedExtensions)]],
                    ];
                }

                try {
                    $fileContents = file_get_contents($mediaUrl);
                    if ($fileContents !== false) {
                        $originalName = $pathInfo['filename'] ?? 'template_media';
                        $cleanName = \Illuminate\Support\Str::slug($originalName, '_');
                        $fileName = 'template_header_'.time().'_'.$cleanName.($fileExt ? '.'.$fileExt : '');

                        \Illuminate\Support\Facades\Storage::disk('public')->put('whatsapp-templates/'.$fileName, $fileContents);
                        $mediaUrl = url('storage/whatsapp-templates/'.$fileName);

                        // Upload to WhatsApp Cloud API immediately
                        $whatsappMediaId = $this->uploadMediaToWhatsApp('whatsapp-templates/'.$fileName, $mediaType, $tenantId);
                    }
                } catch (\Exception $e) {
                    whatsapp_log('Error downloading header media from URL', 'warning', [
                        'url' => $mediaUrl,
                        'error' => $e->getMessage(),
                    ], $e, $tenantId);
                }
            }

            return [
                'success' => true,
                'filename' => 'whatsapp-templates/'.$fileName,
                'filelink' => 'whatsapp-templates/'.$fileName,
                'media_url' => $mediaUrl,
                'media_type' => $mediaType,
                'file_extension' => $fileExt,
                'whatsapp_media_id' => $whatsappMediaId ?? null,
            ];
        } catch (\Exception $e) {
            whatsapp_log('Error handling template header media', 'error', [
                'template_id' => $template->id,
                'media_type' => $mediaType,
                'error' => $e->getMessage(),
            ], $e, $tenantId);

            return [
                'success' => false,
                'message' => 'Error processing header media: '.$e->getMessage(),
                'errors' => ['header_media' => ['Error processing media file']],
            ];
        }
    }

    private function extractAndParseTemplateParameters(Request $request, $contact, $tenantId)
    {
        $params = [
            'header' => [],
            'body' => [],
            'footer' => [],
            'buttons' => [],
        ];

        // Header parameter
        if ($request->has('header_field_1')) {
            $headerText = $this->parseMessageText([
                'rel_type' => $contact->type,
                'rel_id' => $contact->id,
                'reply_text' => $request->input('header_field_1'),
                'tenant_id' => $tenantId,
            ]);
            $params['header'][] = $headerText['reply_text'];
        }

        // Body parameters (field_1 to field_10)
        for ($i = 1; $i <= 10; $i++) {
            if ($request->has("field_{$i}")) {
                $bodyText = $this->parseMessageText([
                    'rel_type' => $contact->type,
                    'rel_id' => $contact->id,
                    'reply_text' => $request->input("field_{$i}"),
                    'tenant_id' => $tenantId,
                ]);
                $params['body'][] = $bodyText['reply_text'];
            }
        }

        // Button parameters
        for ($i = 0; $i <= 2; $i++) {
            if ($request->has("button_{$i}")) {
                $buttonText = $this->parseMessageText([
                    'rel_type' => $contact->type,
                    'rel_id' => $contact->id,
                    'reply_text' => $request->input("button_{$i}"),
                    'tenant_id' => $tenantId,
                ]);
                $params['buttons'][] = $buttonText['reply_text'];
            }
        }

        return $params;
    }

    private function generateOtpCode($length = 6): string
    {
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;

        return (string) random_int($min, $max);
    }

    private function sendTemplateViaWhatsApp($phoneNumber, $templateData, $whatsappSettings)
    {
        try {
            // Call the WhatsApp trait's sendTemplate method using the aliased name
            $result = $this->sendTemplateFromTrait($phoneNumber, $templateData, 'api');

            if ($result['status']) {
                return [
                    'success' => true,
                    'message_id' => $result['data']->messages[0]->id ?? null,
                    'response' => $result,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['message'] ?? 'Failed to send template',
                    'response' => $result,
                ];
            }
        } catch (\Exception $e) {
            whatsapp_log('Error sending template message', 'error', [
                'phone' => $phoneNumber,
                'template_name' => $templateData['template_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ], $e, $templateData['tenant_id'] ?? null);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception' => $e,
            ];
        }
    }

    private function sendWhatsAppMediaMessage($phoneNumber, $mediaType, $mediaUrl, $caption, $filename, $whatsappSettings)
    {
        try {
            $whatsapp = new WhatsAppCloudApi([
                'from_phone_number_id' => $whatsappSettings['wm_default_phone_number_id'],
                'access_token' => $whatsappSettings['wm_access_token'],
            ]);

            $response = null;
            $link = new \Netflie\WhatsAppCloudApi\Message\Media\LinkID($mediaUrl);

            switch ($mediaType) {
                case 'image':
                    $response = $whatsapp->sendImage($phoneNumber, $link, $caption ?? '');
                    break;
                case 'document':
                    $response = $whatsapp->sendDocument($phoneNumber, $link, $filename, $caption ?? '');
                    break;
                case 'video':
                    $response = $whatsapp->sendVideo($phoneNumber, $link, $caption ?? '');
                    break;
                case 'audio':
                    $response = $whatsapp->sendAudio($phoneNumber, $link);
                    break;
                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported media type: '.$mediaType,
                    ];
            }

            $responseData = $response->decodedBody();

            if (isset($responseData['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'],
                    'response' => $responseData,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No message ID in response',
                    'response' => $responseData,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception' => $e,
            ];
        }
    }

    private function sendWhatsAppInteractiveMessage($phoneNumber, $interactiveData, $whatsappSettings)
    {
        try {
            $whatsapp = new WhatsAppCloudApi([
                'from_phone_number_id' => $whatsappSettings['wm_default_phone_number_id'],
                'access_token' => $whatsappSettings['wm_access_token'],
            ]);

            $type = $interactiveData['type'];
            $bodyText = $interactiveData['body_text'];
            $headerText = $interactiveData['header_text'] ?? null;
            $footerText = $interactiveData['footer_text'] ?? null;

            if ($type === 'button') {
                $buttons = [];
                foreach ($interactiveData['buttons'] as $btn) {
                    $buttons[] = new \Netflie\WhatsAppCloudApi\Message\ButtonReply\Button(
                        $btn['id'],
                        $btn['title']
                    );
                }

                $buttonAction = new \Netflie\WhatsAppCloudApi\Message\ButtonReply\ButtonAction($buttons);
                $response = $whatsapp->sendButton(
                    $phoneNumber,
                    $bodyText,
                    $buttonAction,
                    $headerText,
                    $footerText
                );
            } else {
                $sections = [];
                foreach ($interactiveData['sections'] as $section) {
                    $rows = [];
                    foreach ($section['rows'] as $row) {
                        $rows[] = new \Netflie\WhatsAppCloudApi\Message\OptionsList\Row(
                            $row['id'],
                            $row['title'],
                            $row['description'] ?? ''
                        );
                    }

                    $sections[] = new \Netflie\WhatsAppCloudApi\Message\OptionsList\Section(
                        $section['title'],
                        $rows
                    );
                }

                $action = new \Netflie\WhatsAppCloudApi\Message\OptionsList\Action(
                    $interactiveData['list_button_text'],
                    $sections
                );

                $response = $whatsapp->sendList(
                    $phoneNumber,
                    $headerText ?? 'Options',
                    $bodyText,
                    $footerText ?? '',
                    $action
                );
            }

            $responseData = $response->decodedBody();

            if (isset($responseData['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'],
                    'response' => $responseData,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No message ID in response',
                    'response' => $responseData,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception' => $e,
            ];
        }
    }
}

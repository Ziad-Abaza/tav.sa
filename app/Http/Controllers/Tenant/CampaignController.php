<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Campaign;
use App\Models\Tenant\CampaignDetail;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Group;
use App\Models\Tenant\Source;
use App\Models\Tenant\Status;
use App\Models\Tenant\WhatsappTemplate;
use App\Rules\PurifiedInput;
use App\Services\FeatureService;
use App\Traits\WhatsApp;
use Carbon\Carbon;
use Corbital\LaravelEmails\Services\MergeFieldsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CampaignController extends Controller
{
    use WhatsApp;
    // =================================================================
    // MAIN CRUD METHODS (Standard RESTful Routes)
    // =================================================================

    public $tenant_id;

    public $tenant_subdomain;

    protected $featureLimitChecker;

    public function __construct()
    {
        $this->tenant_id = tenant_id();
        $this->tenant_subdomain = tenant_subdomain_by_tenant_id($this->tenant_id);
        $this->featureLimitChecker = app(FeatureService::class);
    }

    /**
     * Display campaigns listing page
     */
    public function create($subdomain, $campaignId = null)
    {
        // Check permissions
        if (! checkPermission(['tenant.campaigns.view'])) {
            session()->flash('notification', ['type' => 'danger', 'message' => t('access_denied_note')]);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }
        // Fetch statuses
        $statuses = Status::select('id', 'name')->orderBy('name')->get();

        // Fetch sources
        $sources = Source::select('id', 'name')->orderBy('name')->get();

        $groups = Group::select('id', 'name')->orderBy('name')->get();

        $subdomain = $this->tenant_subdomain;
        $remainingLimit = $this->getRemainingLimitProperty();
        $hasReachedLimit = $this->getHasReachedLimitProperty();

        return view('tenant.campaign.campaign', ['subdomain' => $subdomain,
            'campaignId' => $campaignId,
            'hasReachedLimit' => $hasReachedLimit,
            'statuses' => $statuses,
            'sources' => $sources,
            'groups' => $groups,
        ]);
    }

    /**
     * Get initial data for the component
     */
    public function getData($subdomain, $campaignId = null)
    {
        if (! checkPermission(['tenant.campaigns.edit', 'tenant.campaigns.create'])) {
            return response()->json([
                'success' => false,
                'message' => t('access_denied_note'),
            ], 403);
        }

        $data = [
            'campaigns' => $this->getCampaign(),
            'mergeFields' => $this->getMergeFields(),
            'relationTypes' => \App\Enum\Tenant\WhatsAppTemplateRelationType::getRelationtype(),
            'systemSettings' => [
                'date_format' => get_tenant_setting_from_db('system', 'date_format', 'd-m-Y'),
                'time_format' => get_tenant_setting_from_db('system', 'time_format', '24'),
                'timezone' => get_tenant_setting_from_db('system', 'timezone', config('app.timezone')),
            ],
            'metaExtensions' => get_meta_allowed_extension(),
            'campaign' => null,
            'limitInfo' => $this->getLimitInfo(),
        ];

        // Load existing bot if editing
        if ($campaignId) {
            $campaign = Campaign::find($campaignId);

            if (! $campaign) {
                return response()->json([
                    'success' => false,
                    'message' => t('campaign_not_found'),
                ], 404);
            }

            $data['campaign'] = [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'rel_type' => $campaign->rel_type,
                'template_id' => $campaign->template_id,
                'select_all' => $campaign->select_all ?? false,
                'send_now' => $campaign->send_now ?? false,
                'scheduled_send_time' => $campaign->scheduled_send_time,
                'header_params' => json_decode($campaign->header_params ?? '[]', true),
                'body_params' => json_decode($campaign->body_params ?? '[]', true),
                'footer_params' => json_decode($campaign->footer_params ?? '[]', true),
                'filename' => $campaign->filename,
                'whatsapp_media_id' => $campaign->whatsapp_media_id,
                'file_url' => $campaign->filename ? asset('storage/'.$campaign->filename) : null,
                // cards_params is automatically cast to array by Laravel
                'cards_params' => $campaign->cards_params ?? [],
                'card_variables' => $this->extractCardVariables($campaign),
                'body_variables' => $this->extractBodyVariables($campaign),
                // Contact selection data
                'selected_contact_ids' => $this->getSelectedContactIds($campaign),
                'status_filters' => $this->getStatusFilters($campaign),
                'source_filters' => $this->getSourceFilters($campaign),
                'group_filters' => $this->getGroupFilters($campaign),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get available campaigns for the tenant and map to required format for frontend
     */
    protected function getCampaign()
    {
        return WhatsappTemplate::where('tenant_id', tenant_id())
            ->get()
            ->map(function ($template) {
                return [
                    'template_id' => $template->template_id,
                    'template_name' => $template->template_name,
                    'language' => $template->language,
                    'header_data_text' => $template->header_data_text,
                    'header_data_format' => $template->header_data_format,
                    'body_data' => $template->body_data,
                    'footer_data' => $template->footer_data,
                    'buttons_data' => $template->buttons_data,
                    'header_params_count' => $template->header_params_count,
                    'body_params_count' => $template->body_params_count,
                    'footer_params_count' => $template->footer_params_count,
                    'template_type' => strtolower(trim($template->template_type ?? 'header')),
                    'cards_json' => $template->cards_json,
                ];
            });
    }

    /**
     * Get merge fields for variable substitution
     */
    protected function getMergeFields()
    {
        $mergeFieldsService = app(MergeFieldsService::class);

        $fields = array_merge(
            $mergeFieldsService->getFieldsForTemplate('tenant-contact-group'),
            $mergeFieldsService->getFieldsForTemplate('tenant-other-group'),
        );

        // Add authentication-specific merge fields
        $authFields = [
            ['name' => 'OTP Code', 'key' => '{otp_code}'],
        ];

        $fields = array_merge($fields, $authFields);

        return array_map(fn ($value) => [
            'key' => ucfirst($value['name']),
            'value' => $value['key'],
        ], $fields);
    }

    /**
     * Get feature limit information
     */
    protected function getLimitInfo()
    {
        $limit = $this->featureLimitChecker->getLimit('campaigns');
        $remaining = $this->featureLimitChecker->getRemainingLimit('campaigns', Campaign::class);
        $hasReached = $this->featureLimitChecker->hasReachedLimit('campaigns', Campaign::class);

        return [
            'limit' => $limit,
            'remaining' => $remaining,
            'hasReached' => $hasReached,
            'isUnlimited' => $remaining === null,
        ];
    }

    /**
     * Check if the selected template requires a file upload
     */
    protected function requiresFileUpload($templateId)
    {
        if (! $templateId) {
            return false;
        }

        $template = WhatsappTemplate::find($templateId);
        if (! $template) {
            return false;
        }

        // Check if the template has a header format that requires a file
        $headerFormat = $template->header_format;

        return in_array($headerFormat, ['IMAGE', 'VIDEO', 'DOCUMENT']);
    }

    /**
     * Handle file upload with progress tracking
     */
    public function uploadFile(Request $request)
    {
        if (! checkPermission(['tenant.campaigns.create', 'tenant.campaigns.edit'])) {
            return response()->json([
                'success' => false,
                'message' => t('access_denied_note'),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => t('validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $filename = $file->store('tenant/'.tenant_id().'/campaign', 'public');

            return response()->json([
                'success' => true,
                'filename' => $filename,
                'file_url' => asset('storage/'.$filename),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => t('error_uploading_file').': '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store new campaign
     */
    public function store(Request $request)
    {

        // Check permissions
        if (! checkPermission(['tenant.campaigns.create'])) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => t('access_denied')], 403);
            }
            session()->flash('notification', [
                'type' => 'danger',
                'message' => t('access_denied_note'),
            ]);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }

        // Determine if we're creating or updating early to check limits appropriately
        $isNewCampaign = ! $request->id;

        // Only check campaign limit for NEW campaigns, not edits
        if ($isNewCampaign && $this->featureLimitChecker->hasReachedLimit('campaigns', Campaign::class)) {
            $this->featureLimitChecker->trackUsage('campaigns');

            return response()->json([
                'success' => false,
                'message' => t('campaign_limit_reached_upgrade_plan'),
                'redirect' => tenant_route('tenant.campaigns.list'),
            ]);
        }
        // Validation rules
        $rules = [
            'campaign_name' => ['required', 'string', 'min:3', 'max:100', new PurifiedInput(t('sql_injection_error'))],
            'rel_type' => 'required',
            'template_id' => ['required', 'integer'],
            'headerInputs' => 'nullable|array',
            'headerInputs.*' => [new PurifiedInput(t('dynamic_input_error'))],
            'bodyInputs' => 'nullable|array',
            'bodyInputs.*' => [new PurifiedInput(t('dynamic_input_error'))],
            'footerInputs' => 'nullable|array',
            'footerInputs.*' => [new PurifiedInput(t('dynamic_input_error'))],
            'file' => 'nullable|file',
            // Carousel validation
            'cards_params' => 'nullable|string', // JSON string of processed cards
            'card_variables' => 'nullable|string',
            'body_variables' => 'nullable|string',
            'carousel_media.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,avi,mov,wmv|max:16384', // 16MB max
            // Contact selection
            'select_all' => 'required|in:0,1',
            'send_now' => 'required|in:0,1',
            'scheduled_send_time' => 'nullable|string', // Date time string
            'relation_type_dynamic' => 'nullable|string', // JSON array of contact IDs
            'status_name' => 'nullable|string', // JSON array of status IDs
            'source_name' => 'nullable|string', // JSON array of source IDs
            'group_name' => 'nullable|string', // JSON array of group IDs
        ];
        // Custom validation for file uploads based on template type
        if ($this->requiresFileUpload($request->template_id)) {
            // Only require file if no existing filename and not updating
            if (! $request->id || ! $request->existing_filename) {
                $rules['file'] = 'required|file';
            }
        }

        $validator = Validator::make($request->all(), $rules, [
            'campaign_name.required' => t('campaign_name_required'),
            'rel_type.required' => t('relation_type_required'),
            'template_id.required' => t('template_required'),
            'file.required' => t('file_required'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => t('validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }
        try {
            // Determine if we're creating or updating
            $campaign = $request->id ? Campaign::findOrFail($request->id) : new Campaign;
            $isNewRecord = ! $campaign->exists;

            // For new records, check if creating one more would exceed the limit
            if ($isNewRecord) {
                $limit = $this->featureLimitChecker->getLimit('campaigns');

                // Skip limit check if unlimited (-1) or no limit set (null)
                if ($limit !== null && $limit !== -1) {
                    $currentCount = Campaign::where('tenant_id', tenant_id())->count();

                    if ($currentCount >= $limit) {
                        return response()->json([
                            'success' => false,
                            'message' => t('campaign_limit_reached_upgrade_plan'),
                        ], 403);
                    }
                }
            }

            // Handle file upload if present
            $filename = $campaign->filename;
            $whatsappMediaId = $campaign->whatsapp_media_id ?? null; // Preserve existing media ID by default

            if ($request->hasFile('file')) {
                // Delete old file if exists
                if (! empty($filename) && Storage::disk('public')->exists($filename)) {
                    Storage::disk('public')->delete($filename);
                }

                // Store new file and get path
                $file = $request->file('file');
                $filename = $file->store('tenant/'.tenant_id().'/campaign', 'public');

                // Upload media to WhatsApp immediately for templates with media headers
                $template = WhatsappTemplate::where('template_id', $request->template_id)
                    ->where('tenant_id', tenant_id())
                    ->first();

                if ($template && in_array($template->header_data_format, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
                    $whatsappMediaId = $this->uploadMediaToWhatsApp($filename, $template->header_data_format);
                }
            }

            // Handle carousel media uploads and existing media URLs
            $uploadedMediaUrls = [];

            // Process new file uploads
            if ($request->hasFile('carousel_media')) {
                foreach ($request->file('carousel_media') as $cardIndex => $mediaFile) {
                    if ($mediaFile) {
                        // Store with a more organized naming convention
                        $extension = $mediaFile->getClientOriginalExtension();
                        $filename = 'card_'.$cardIndex.'_'.time().'_'.uniqid().'.'.$extension;
                        $mediaPath = $mediaFile->storeAs('tenant/'.tenant_id().'/campaign/carousel', $filename, 'public');
                        $uploadedMediaUrls[$cardIndex] = url('storage/'.$mediaPath);
                    }
                }
            }

            // Handle existing media URLs (from frontend existing_carousel_media)
            $preservedUrls = [];
            if ($request->has('existing_carousel_media') && ! empty($request->existing_carousel_media)) {
                $existingMediaUrls = json_decode($request->existing_carousel_media, true);

                if (is_array($existingMediaUrls)) {
                    // Merge existing URLs with uploaded URLs (new uploads take priority)
                    foreach ($existingMediaUrls as $cardIndex => $mediaUrl) {
                        // Only use existing URL if no new upload for this card
                        if (! isset($uploadedMediaUrls[$cardIndex])) {
                            $uploadedMediaUrls[$cardIndex] = $mediaUrl;
                            $preservedUrls[] = $mediaUrl; // Track URLs we want to keep

                        }
                    }
                }
            }

            // Handle old carousel media cleanup when updating (AFTER determining what to preserve)
            if (! $isNewRecord) {
                $this->cleanupOldCarouselMedia($campaign, $preservedUrls);
            }

            // Set model attributes
            $campaign->tenant_id = tenant_id();
            $campaign->name = $request->campaign_name;
            $campaign->rel_type = $request->rel_type;
            $campaign->template_id = $request->template_id;
            $campaign->select_all = $request->select_all == 1;
            $campaign->send_now = $request->send_now == 1;

            // Process scheduled time
            $campaign->scheduled_send_time = $this->processScheduledTime([
                'send_now' => $request->send_now == 1,
                'scheduled_send_time' => $request->scheduled_send_time,
            ]);

            // Store contact selection and filter data
            $campaign->rel_data = json_encode([
                'status_ids' => $request->status_name ? json_decode($request->status_name, true) : [],
                'source_ids' => $request->source_name ? json_decode($request->source_name, true) : [],
                'group_ids' => $request->group_name ? json_decode($request->group_name, true) : [],
                'selected_contact_ids' => $request->relation_type_dynamic ? json_decode($request->relation_type_dynamic, true) : [],
            ]);

            $template = WhatsappTemplate::where('template_id', $request->template_id)
                ->where('tenant_id', tenant_id())
                ->first();

            $isCarouselTemplate = $template && strtolower($template->template_type) === 'carousel';

            if ($isCarouselTemplate) {
                // Handle carousel template data
                $cardsData = $this->processCarouselCards($request, $uploadedMediaUrls);

                // Store as proper JSON array, not double-encoded string
                $campaign->cards_params = $cardsData;

                // Handle body variables for carousel templates
                $bodyVariables = $request->body_variables ? json_decode($request->body_variables, true) : [];
                $campaign->body_params = json_encode(array_values(array_filter($bodyVariables)));

                // For carousel templates, header and footer params are usually empty
                $campaign->header_params = json_encode([]);
                $campaign->footer_params = json_encode([]);

                // Upload all carousel card media to WhatsApp at creation time
                $carouselMediaIds = $this->uploadCarouselMediaToWhatsApp($uploadedMediaUrls);
                $campaign->whatsapp_media_ids = $carouselMediaIds;
                $campaign->filename = null; // Carousel doesn't use single filename
            } else {
                // Handle regular template data
                $campaign->header_params = json_encode(array_values(array_filter($request->headerInputs ?? [])));
                $campaign->body_params = json_encode(array_values(array_filter($request->bodyInputs ?? [])));
                $campaign->footer_params = json_encode(array_values(array_filter($request->footerInputs ?? [])));
                $campaign->cards_params = null;
                $campaign->filename = $filename;

                // Store regular template media ID in JSON format
                if ($whatsappMediaId) {
                    $campaign->whatsapp_media_ids = ['header' => $whatsappMediaId];
                } else {
                    $campaign->whatsapp_media_ids = null;
                }
            }

            // Calculate and set sending count
            $sendingCountData = [
                'select_all' => $request->select_all == 1,
                'relation_type_dynamic' => $request->relation_type_dynamic ? json_decode($request->relation_type_dynamic, true) : [],
                'rel_type' => $request->rel_type,
                'status_ids' => $request->status_name ? json_decode($request->status_name, true) : [],
                'source_ids' => $request->source_name ? json_decode($request->source_name, true) : [],
                'group_ids' => $request->group_name ? json_decode($request->group_name, true) : [],
            ];
            $campaign->sending_count = $this->calculateSendingCount($sendingCountData);

            // Save to database
            $campaign->save();

            if ($isNewRecord) {
                $this->featureLimitChecker->trackUsage('campaigns');
            }

            // Create campaign details for selected contacts
            $validatedData = [
                'campaign_name' => $request->campaign_name,
                'rel_type' => $request->rel_type,
                'template_id' => $request->template_id,
                'select_all' => $request->select_all == 1,
                'relation_type_dynamic' => $request->relation_type_dynamic ? json_decode($request->relation_type_dynamic, true) : [],
                'status_name' => $request->status_name ? json_decode($request->status_name, true) : null,
                'source_name' => $request->source_name ? json_decode($request->source_name, true) : null,
                'group_name' => $request->group_name ? json_decode($request->group_name, true) : null,
                'headerInputs' => $request->headerInputs ?? [],
                'bodyInputs' => $request->bodyInputs ?? [],
                'footerInputs' => $request->footerInputs ?? [],
            ];

            // Delete existing campaign details if updating
            if (! $isNewRecord) {
                $campaign->campaign_details()->delete();
            }

            // Create campaign details for contacts
            $this->createCampaignDetails($campaign, $validatedData);

            return response()->json([
                'success' => true,
                'message' => $isNewRecord
                    ? t('campaign_created_successfully')
                    : t('campaign_updated_successfully'),
                'campaign_id' => $campaign->id,
                'redirect_url' => tenant_route('tenant.campaigns.list'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => t('error_saving_campaign').': '.$e->getMessage(),
            ], 500);
        }

    }

    /**
     * Extract card variables from saved carousel template data
     */
    private function extractCardVariables($campaign)
    {
        if (! $campaign->cards_params || ! is_array($campaign->cards_params)) {
            return [];
        }

        // cards_params is automatically cast to array by Laravel model
        $cardsData = $campaign->cards_params;
        $cardVariables = [];

        foreach ($cardsData as $cardIndex => $card) {
            if (! isset($card['components'])) {
                continue;
            }

            foreach ($card['components'] as $component) {
                if ($component['type'] === 'BODY' && isset($component['text'])) {
                    // Extract variables from the saved text
                    preg_match_all('/\{\{(\d+)\}\}/', $component['text'], $matches);

                    if (! empty($matches[1])) {
                        $cardVariables[$cardIndex] = [];

                        // Get the actual values from example.body_text
                        $exampleValues = $component['example']['body_text'][0] ?? [];

                        foreach ($matches[1] as $varIndex => $variableKey) {
                            // Map the variable key to its actual value from example
                            $cardVariables[$cardIndex][(int) $variableKey] = $exampleValues[$varIndex] ?? '';
                        }
                    }
                }
            }
        }

        return $cardVariables;
    }

    /**
     * Extract body variables from saved template data
     */
    private function extractBodyVariables($campaign)
    {
        $bodyParams = json_decode($campaign->body_params ?? '[]', true);
        $bodyVariables = [];

        // Convert array of values back to key-value pairs
        foreach ($bodyParams as $index => $value) {
            $bodyVariables[(string) ($index + 1)] = $value;
        }

        return $bodyVariables;
    }

    /**
     * Merge uploaded media URLs into processed cards data
     */
    private function mergeMediaUrlsIntoCards($cards, $uploadedMediaUrls)
    {
        if (empty($uploadedMediaUrls) || ! is_array($cards)) {
            return $cards;
        }

        foreach ($cards as $cardIndex => &$card) {
            if (! isset($card['components']) || ! is_array($card['components'])) {
                continue;
            }

            foreach ($card['components'] as &$component) {
                // Handle HEADER component with media
                if (
                    $component['type'] === 'HEADER' &&
                    in_array($component['format'] ?? '', ['IMAGE', 'VIDEO']) &&
                    isset($uploadedMediaUrls[$cardIndex])
                ) {
                    $component['example'] = [
                        'header_handle' => [$uploadedMediaUrls[$cardIndex]],
                    ];
                }
            }
        }

        return $cards;
    }

    /**
     * Clean up old carousel media files when updating a template bot
     * Only deletes files that are NOT in the preservedUrls array.
     */
    private function cleanupOldCarouselMedia($campaign, $preservedUrls = [])
    {
        if (! $campaign->cards_params || ! is_array($campaign->cards_params)) {
            return;
        }

        $oldCards = $campaign->cards_params;

        foreach ($oldCards as $card) {
            if (! empty($card['components']) && is_array($card['components'])) {
                foreach ($card['components'] as $component) {
                    // Handle HEADER components with media
                    if ($component['type'] === 'HEADER' &&
                        isset($component['example']['header_handle']) &&
                        is_array($component['example']['header_handle'])) {

                        foreach ($component['example']['header_handle'] as $mediaUrl) {
                            if (! empty($mediaUrl)) {
                                // Skip deletion if this URL is being preserved
                                if (in_array($mediaUrl, $preservedUrls)) {

                                    continue;
                                }

                                // Convert full URL to storage path
                                $relativePath = str_replace([
                                    url('storage').'/',
                                    url('/storage/'),
                                    config('app.url').'/storage/',
                                ], '', $mediaUrl);

                                if (Storage::disk('public')->exists($relativePath)) {

                                    Storage::disk('public')->delete($relativePath);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Process carousel cards data and merge with uploaded media URLs
     */
    private function processCarouselCards($request, $uploadedMediaUrls)
    {
        // Check if we have the processed cards data from frontend
        if ($request->has('cards_params') && ! empty($request->cards_params)) {
            // Frontend is sending the complete processed cards data
            $frontendCards = is_string($request->cards_params)
                ? json_decode($request->cards_params, true)
                : $request->cards_params;

            if (is_array($frontendCards)) {
                // Merge uploaded media URLs into the frontend cards data
                return $this->mergeMediaUrlsIntoCards($frontendCards, $uploadedMediaUrls);
            }
        }

        // Fallback: Process from template + variables + uploaded media
        $template = WhatsappTemplate::where('template_id', $request->template_id)
            ->where('tenant_id', tenant_id())
            ->first();

        if (! $template || ! $template->cards_json) {
            return [];
        }

        // Parse the original template cards
        $originalCards = is_string($template->cards_json)
            ? json_decode($template->cards_json, true)
            : $template->cards_json;

        if (! is_array($originalCards)) {
            return [];
        }

        // Get card variables if present
        $cardVariables = $request->card_variables ? json_decode($request->card_variables, true) : [];

        $processedCards = [];

        foreach ($originalCards as $cardIndex => $card) {
            if (! isset($card['components']) || ! is_array($card['components'])) {
                continue;
            }

            $processedCard = ['components' => []];

            foreach ($card['components'] as $component) {
                $processedComponent = $component;

                // Handle HEADER component with media
                if (
                    $component['type'] === 'HEADER' &&
                    in_array($component['format'] ?? '', ['IMAGE', 'VIDEO'])
                ) {
                    // Use uploaded media URL if available, otherwise keep original
                    if (isset($uploadedMediaUrls[$cardIndex])) {
                        $processedComponent['example'] = [
                            'header_handle' => [$uploadedMediaUrls[$cardIndex]],
                        ];
                    }
                }

                // Handle BODY component with variables
                if ($component['type'] === 'BODY' && isset($component['text'])) {
                    $bodyText = $component['text'];

                    // Replace variables in body text with actual values
                    if (isset($cardVariables[$cardIndex])) {
                        foreach ($cardVariables[$cardIndex] as $variableKey => $variableValue) {
                            // Replace both {{1}}, {{2}} format and {{variableKey}} format
                            $bodyText = str_replace([
                                '{{'.$variableKey.'}}',
                                '{{'.($variableKey + 1).'}}',
                            ], $variableValue, $bodyText);
                        }
                    }

                    $processedComponent['text'] = $bodyText;
                }

                $processedCard['components'][] = $processedComponent;
            }

            $processedCards[] = $processedCard;
        }

        return $processedCards;
    }

    /**
     * Delete campaign
     */
    public function destroy($subdomain, int $id)
    {
        // Check permissions
        if (! checkPermission(['tenant.campaigns.delete'])) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => t('access_denied')], 403);
            }
            session()->flash('notification', [
                'type' => 'danger',
                'message' => t('access_denied_note'),
            ]);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }

        try {
            $campaign = Campaign::where('tenant_id', $this->tenant_id)
                ->findOrFail($id);

            // Delete associated file if exists
            if ($campaign->filename) {
                Storage::disk('public')->delete($campaign->filename);
            }

            // Delete carousel media files if they exist
            if ($campaign->cards_params && is_array($campaign->cards_params)) {
                foreach ($campaign->cards_params as $card) {
                    if (! empty($card['components']) && is_array($card['components'])) {
                        foreach ($card['components'] as $component) {
                            if ($component['type'] === 'HEADER' &&
                                isset($component['example']['header_handle']) &&
                                is_array($component['example']['header_handle'])) {

                                foreach ($component['example']['header_handle'] as $mediaUrl) {
                                    if (! empty($mediaUrl)) {
                                        $relativePath = str_replace([
                                            url('storage').'/',
                                            url('/storage/'),
                                            config('app.url').'/storage/',
                                        ], '', $mediaUrl);

                                        if (Storage::disk('public')->exists($relativePath)) {
                                            Storage::disk('public')->delete($relativePath);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Delete campaign (cascade will handle campaign_details)
            $campaign->delete();

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => t('campaign_deleted_successfully'),
                ]);
            }

            session()->flash('notification', ['type' => 'success', 'message' => t('campaign_deleted_successfully')]);

            return redirect()->to(tenant_route('tenant.campaigns.list'))
                ->with('success', t('campaign_deleted_successfully'));
        } catch (\Exception $e) {
            app_log('Campaign deletion failed', 'error', $e, [
                'campaign_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $this->tenant_id,
            ]);

            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => t('campaign_delete_failed').': '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', t('campaign_delete_failed').': '.$e->getMessage());
        }
    }

    public function getRemainingLimitProperty()
    {
        return $this->featureLimitChecker->getRemainingLimit('campaigns', Campaign::class);
    }

    public function getHasReachedLimitProperty()
    {
        return $this->featureLimitChecker->hasReachedLimit('campaigns', Campaign::class);
    }

    /**
     * Get selected contact IDs from campaign rel_data
     */
    private function getSelectedContactIds($campaign)
    {
        $relData = json_decode($campaign->rel_data ?? '{}', true);

        return $relData['selected_contact_ids'] ?? [];
    }

    /**
     * Get status filters from campaign rel_data
     */
    private function getStatusFilters($campaign)
    {
        $relData = json_decode($campaign->rel_data ?? '{}', true);

        return $relData['status_ids'] ?? [];
    }

    /**
     * Get source filters from campaign rel_data
     */
    private function getSourceFilters($campaign)
    {
        $relData = json_decode($campaign->rel_data ?? '{}', true);

        return $relData['source_ids'] ?? [];
    }

    /**
     * Get group filters from campaign rel_data
     */
    private function getGroupFilters($campaign)
    {
        $relData = json_decode($campaign->rel_data ?? '{}', true);

        return $relData['group_ids'] ?? [];
    }

    // =================================================================
    // CONTACT SELECTION API METHODS
    // =================================================================

    /**
     * Get contacts based on filters with pagination (AJAX)
     */
    public function getContactsPaginated(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $settings = tenant_settings_by_group('miscellaneous');
            $perPage = (int) ($settings['campaign_contacts_per_page'] ?? 100);
            $offset = ($page - 1) * $perPage;
            $contacts = $this->loadFilteredContacts($request->all(), $offset, $perPage);
            $totalCount = $this->calculateContactCount($request->all());
            $hasMore = ($offset + $perPage) < $totalCount;

            return response()->json([
                'success' => true,
                'data' => $contacts,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'has_more' => $hasMore,
            ]);
        } catch (\Exception $e) {
            app_log('Failed to load paginated contacts', 'error', $e, [
                'error' => $e->getMessage(),
                'filters' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => t('failed_to_load_contacts ').$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search contacts (AJAX)
     */
    public function searchContacts(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $relType = $request->input('rel_type');
            $statusIds = $request->input('status_ids', []);
            $sourceIds = $request->input('source_ids', []);
            $groupIds = $request->input('group_ids', []);

            if (empty($search) || empty($relType)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                ]);
            }

            $query = Contact::fromTenant($this->tenant_subdomain)->where('type', $relType)
                ->where('is_enabled', 1)
                ->where(function ($q) {
                    $q->whereNull('is_opted_out')
                        ->orWhere('is_opted_out', 0);
                });

            // Apply permission-based filtering
            if (! Auth::user()->is_admin && checkPermission('tenant.contact.view_own')) {
                $query->where('assigned_id', auth()->id());
            }

            // Apply filters
            if (! empty($statusIds)) {
                $query->whereIn('status_id', $statusIds);
            }

            if (! empty($sourceIds)) {
                $query->whereIn('source_id', $sourceIds);
            }

            if (! empty($groupIds)) {
                $query->where(function ($q) use ($groupIds) {
                    foreach ($groupIds as $groupId) {
                        $q->orWhereRaw('JSON_CONTAINS(group_id, ?)', [json_encode([$groupId])]);
                    }
                });
            }

            // Apply search filter
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('lastname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ['%'.$search.'%']);
            });

            $contacts = $query->select('id', 'firstname', 'lastname', 'email', 'phone')
                ->orderBy('firstname')
                ->limit(500)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $contacts,
                'total' => $contacts->count(),
            ]);
        } catch (\Exception $e) {
            app_log('Failed to search contacts', 'error', $e, [
                'error' => $e->getMessage(),
                'search' => $request->input('search'),
            ]);

            return response()->json([
                'success' => false,
                'message' => t('failed_to_search_contacts').$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Count contacts based on filters (AJAX)
     */
    public function countContacts(Request $request)
    {
        try {
            $count = $this->calculateContactCount($request->all());

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => t('failed_to_count_contacts').$e->getMessage(),
            ], 500);
        }
    }

    // =================================================================
    // CAMPAIGN DETAILS CREATION
    // =================================================================

    /**
     * Create campaign details for selected contacts
     */
    private function createCampaignDetails(Campaign $campaign, array $validatedData): void
    {
        $template = WhatsappTemplate::where('template_id', $campaign->template_id)->firstOrFail();
        $contacts = $this->getSelectedContacts($validatedData);

        if ($contacts->isEmpty()) {
            throw new \Exception(t('no_contacts_selected_campaign'));
        }

        $headerInputs = $validatedData['headerInputs'] ?? [];
        $bodyInputs = $validatedData['bodyInputs'] ?? [];
        $footerInputs = $validatedData['footerInputs'] ?? [];

        $campaignDetails = [];

        foreach ($contacts as $contact) {
            $campaignDetails[] = [
                'campaign_id' => $campaign->id,
                'tenant_id' => $this->tenant_id,
                'rel_id' => $contact->id,
                'rel_type' => $campaign->rel_type,
                'header_message' => $this->parseMessageVariables($template->header_data_text, $headerInputs, $contact),
                'body_message' => $this->parseMessageVariables($template->body_data, $bodyInputs, $contact),
                'footer_message' => $this->parseMessageVariables($template->footer_data, $footerInputs, $contact),
                'status' => 1,
                'message_status' => 'pending',
                'whatsapp_id' => null,
                'response_message' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $chunks = array_chunk($campaignDetails, 1000);

        foreach ($chunks as $chunk) {
            CampaignDetail::insert($chunk);
        }
    }

    /**
     * Get selected contacts for campaign
     */
    private function getSelectedContacts(array $validatedData): Collection
    {
        if ($validatedData['select_all'] ?? false) {
            return $this->getAllContactsForSelectAll($validatedData);
        }

        $contactIds = $validatedData['relation_type_dynamic'] ?? [];

        if (empty($contactIds)) {
            return collect([]);
        }

        return Contact::fromTenant($this->tenant_subdomain)->whereIn('id', $contactIds)
            ->where('type', $validatedData['rel_type'])
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('is_opted_out')
                    ->orWhere('is_opted_out', 0);
            })
            ->get();
    }

    /**
     * Get all contacts for select_all campaigns
     */
    private function getAllContactsForSelectAll(array $validatedData): Collection
    {
        $relType = $validatedData['rel_type'];
        $statusIds = is_array($validatedData['status_name'] ?? null) ? $validatedData['status_name'] : [];
        $sourceIds = is_array($validatedData['source_name'] ?? null) ? $validatedData['source_name'] : [];
        $groupIds = is_array($validatedData['group_name'] ?? null) ? $validatedData['group_name'] : [];

        $query = Contact::fromTenant($this->tenant_subdomain)->where('type', $relType)
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('is_opted_out')
                    ->orWhere('is_opted_out', 0);
            });

        // Apply permission-based filtering
        if (! Auth::user()->is_admin && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', auth()->id());
        }

        // Apply filters
        if (! empty($statusIds)) {
            $query->whereIn('status_id', $statusIds);
        }

        if (! empty($sourceIds)) {
            $query->whereIn('source_id', $sourceIds);
        }

        if (! empty($groupIds)) {
            $query->where(function ($q) use ($groupIds) {
                foreach ($groupIds as $groupId) {
                    $q->orWhereRaw('JSON_CONTAINS(group_id, ?)', [json_encode([$groupId])]);
                }
            });
        }

        return $query->select('id', 'firstname', 'lastname', 'email', 'phone', 'company')
            ->orderBy('id')
            ->get();
    }

    /**
     * Parse message variables with contact data
     */
    private function parseMessageVariables(?string $message, array $params, $contact): ?string
    {
        if (empty($message)) {
            return null;
        }

        $parsedMessage = $message;

        // Replace numbered parameters {{1}}, {{2}}, etc.
        foreach ($params as $index => $param) {
            $placeholder = '{{'.($index + 1).'}}';
            $value = $param ?? '';
            $parsedMessage = str_replace($placeholder, $value, $parsedMessage);
        }

        // Replace contact merge fields
        $parsedMessage = $this->replaceMergeFields($parsedMessage, $contact);

        return $parsedMessage;
    }

    /**
     * Replace merge fields with contact data
     */
    private function replaceMergeFields(string $message, $contact): string
    {
        $settings = get_batch_settings([
            'system.site_name',
        ]);
        $mergeFields = [
            '{{contact_first_name}}' => $contact->firstname ?? '',
            '{{contact_last_name}}' => $contact->lastname ?? '',
            '{{contact_full_name}}' => trim(($contact->firstname ?? '').' '.($contact->lastname ?? '')),
            '{{contact_email}}' => $contact->email ?? '',
            '{{contact_phone}}' => $contact->phone ?? '',
            '{{contact_company}}' => $contact->company ?? '',
            '{{business_name}}' => $settings['system.site_name'] ?? 'Business',
            '{{current_date}}' => now()->format('Y-m-d'),
            '{{current_time}}' => now()->format('H:i:s'),
        ];

        return str_replace(array_keys($mergeFields), array_values($mergeFields), $message);
    }

    /**
     * Load filtered contacts based on request parameters
     */
    private function loadFilteredContacts(array $filters, int $offset, int $limit): Collection
    {
        $relType = $filters['rel_type'] ?? null;
        $statusIds = $filters['status_ids'] ?? [];
        $sourceIds = $filters['source_ids'] ?? [];
        $groupIds = $filters['group_ids'] ?? [];

        if (! $relType) {
            return collect([]);
        }

        $query = Contact::fromTenant($this->tenant_subdomain)->where('type', $relType)
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('is_opted_out')
                    ->orWhere('is_opted_out', 0);
            });

        // Apply permission-based filtering
        if (! Auth::user()->is_admin && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', auth()->id());
        }

        // Apply filters
        if (! empty($statusIds)) {
            $query->whereIn('status_id', $statusIds);
        }

        if (! empty($sourceIds)) {
            $query->whereIn('source_id', $sourceIds);
        }

        if (! empty($groupIds)) {
            $query->where(function ($q) use ($groupIds) {
                foreach ($groupIds as $groupId) {
                    $q->orWhereRaw('JSON_CONTAINS(group_id, ?)', [json_encode([$groupId])]);
                }
            });
        }

        return $query->select('id', 'firstname', 'lastname', 'email', 'phone')
            ->orderBy('firstname')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate contact count based on filters
     */
    private function calculateContactCount(array $filters): int
    {
        $relType = $filters['rel_type'] ?? null;
        $statusIds = $filters['status_ids'] ?? [];
        $sourceIds = $filters['source_ids'] ?? [];
        $groupIds = $filters['group_ids'] ?? [];

        $selectAll = $filters['select_all'] ?? false;
        $selectedContacts = $filters['selected_contacts'] ?? [];

        if (! $relType) {
            return 0;
        }

        // If specific contacts are selected, return their count
        if (! $selectAll && ! empty($selectedContacts)) {
            return count($selectedContacts);
        }

        // If select all is enabled, count all matching contacts
        $query = Contact::fromTenant($this->tenant_subdomain)->where('type', $relType)
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('is_opted_out')
                    ->orWhere('is_opted_out', 0);
            });

        // Apply permission-based filtering
        if (! Auth::user()->is_admin && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', auth()->id());
        }

        // Apply filters
        if (! empty($statusIds)) {
            $query->whereIn('status_id', $statusIds);
        }

        if (! empty($sourceIds)) {
            $query->whereIn('source_id', $sourceIds);
        }

        if (! empty($groupIds)) {
            $query->where(function ($q) use ($groupIds) {
                foreach ($groupIds as $groupId) {
                    $q->orWhereRaw('JSON_CONTAINS(group_id, ?)', [json_encode([$groupId])]);
                }
            });
        }

        return $query->count();
    }

    /**
     * Calculate sending count
     */
    private function calculateSendingCount(array $validatedData): int
    {
        if ($validatedData['select_all'] ?? false) {
            return $this->calculateContactCount($validatedData);
        }

        return count($validatedData['relation_type_dynamic'] ?? []);
    }

    // =================================================================
    // SCHEDULING METHODS
    // =================================================================

    /**
     * Process scheduled time
     */
    private function processScheduledTime(array $validatedData): ?string
    {
        if ($validatedData['send_now'] ?? false) {
            return now()->utc()->toDateTimeString();
        }

        if (empty($validatedData['scheduled_send_time'])) {
            return null;
        }

        try {
            $scheduledDate = $this->parseScheduledDateTime($validatedData['scheduled_send_time']);

            // Convert to UTC for database storage
            return $scheduledDate->setTimezone('UTC')->toDateTimeString();
        } catch (\Exception $e) {
            app_log('Date parsing failed in processScheduledTime', 'error', $e, [
                'input_date' => $validatedData['scheduled_send_time'],
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Invalid date/time format: '.$e->getMessage());
        }
    }

    /**
     * Parse scheduled date time from user input using configured formats
     */
    private function parseScheduledDateTime(string $dateTimeString): Carbon
    {
        // Try parsing as ISO 8601 format first (from JavaScript Date)
        try {
            $date = Carbon::parse($dateTimeString);
            if ($date !== false) {
                return $date;
            }
        } catch (\Exception $e) {
            // Continue to other parsing methods
        }

        // Get the configured date and time format from tenant settings
        $dateFormat = get_tenant_setting_from_db('system', 'date_format', 'd-m-Y');
        $timeFormat = get_tenant_setting_from_db('system', 'time_format') == '12' ? 'h:i A' : 'H:i';
        $userTimezone = get_tenant_setting_from_db('system', 'timezone', config('app.timezone'));

        // Primary format based on settings
        $primaryFormat = $dateFormat.' '.$timeFormat;

        // Fallback formats to handle various input scenarios
        $fallbackFormats = [
            $dateFormat.' '.($timeFormat === 'h:i A' ? 'H:i' : 'h:i A'),
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd-m-Y H:i',
            'd.m.Y H:i',
            'm/d/Y h:i A',
            'm/d/Y H:i',
        ];

        $allFormats = array_merge([$primaryFormat], $fallbackFormats);

        foreach ($allFormats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateTimeString, $userTimezone);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        throw new \Exception("Unable to parse date '{$dateTimeString}'. Expected format: {$primaryFormat}");
    }

    /**
     * Upload campaign media to WhatsApp and return media_id
     * Called during campaign creation/update for immediate upload and validation
     */
    private function uploadMediaToWhatsApp(string $filename, string $mediaType): ?string
    {
        try {
            $filePath = storage_path('app/public/'.$filename);

            if (! file_exists($filePath)) {
                whatsapp_log('Campaign media file not found for upload', 'warning', [
                    'filename' => $filename,
                    'tenant_id' => $this->tenant_id,
                ], null, $this->tenant_id);

                return null;
            }

            // Use WhatsApp trait's loadConfig to upload media
            $this->setWaTenantId($this->tenant_id);
            $whatsappCloudApi = $this->loadConfig();
            $response = $whatsappCloudApi->uploadMedia($filePath);
            $data = json_decode($response->body(), true);
            $mediaId = $data['id'] ?? null;

            if ($mediaId) {
                whatsapp_log('Campaign media uploaded successfully at creation', 'info', [
                    'filename' => $filename,
                    'media_id' => $mediaId,
                    'media_type' => $mediaType,
                    'tenant_id' => $this->tenant_id,
                ], null, $this->tenant_id);
            } else {
                whatsapp_log('Campaign media upload returned no ID', 'warning', [
                    'filename' => $filename,
                    'response' => $response->body(),
                    'tenant_id' => $this->tenant_id,
                ], null, $this->tenant_id);
            }

            return $mediaId;
        } catch (\Exception $e) {
            whatsapp_log('Campaign media upload failed at creation', 'error', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'tenant_id' => $this->tenant_id,
            ], $e, $this->tenant_id);

            // Don't throw - allow campaign creation with fallback to URL
            return null;
        }
    }

    /**
     * Upload carousel media files to WhatsApp Cloud API
     * Returns array mapping card index to media_id: {"0": "media_id_1", "1": "media_id_2"}
     */
    private function uploadCarouselMediaToWhatsApp(array $uploadedMediaUrls): array
    {
        $mediaIds = [];

        foreach ($uploadedMediaUrls as $cardIndex => $mediaUrl) {
            try {
                // Extract filename from URL for local file path
                $urlPath = parse_url($mediaUrl, PHP_URL_PATH);
                $relativePath = str_replace('/storage/', '', $urlPath);
                $filePath = storage_path('app/public/'.$relativePath);

                if (! file_exists($filePath)) {
                    whatsapp_log('Carousel card media file not found', 'warning', [
                        'card_index' => $cardIndex,
                        'media_url' => $mediaUrl,
                        'file_path' => $filePath,
                        'tenant_id' => $this->tenant_id,
                    ], null, $this->tenant_id);

                    continue;
                }

                // Upload to WhatsApp
                $this->setWaTenantId($this->tenant_id);
                $whatsappCloudApi = $this->loadConfig();
                $response = $whatsappCloudApi->uploadMedia($filePath);
                $data = json_decode($response->body(), true);
                $mediaId = $data['id'] ?? null;

                if ($mediaId) {
                    $mediaIds[(string) $cardIndex] = $mediaId;
                    whatsapp_log('Carousel card media uploaded successfully', 'info', [
                        'card_index' => $cardIndex,
                        'media_id' => $mediaId,
                        'tenant_id' => $this->tenant_id,
                    ], null, $this->tenant_id);
                } else {
                    whatsapp_log('Carousel card media upload returned no ID', 'warning', [
                        'card_index' => $cardIndex,
                        'response' => $response->body(),
                        'tenant_id' => $this->tenant_id,
                    ], null, $this->tenant_id);
                }
            } catch (\Exception $e) {
                whatsapp_log('Carousel card media upload failed', 'error', [
                    'card_index' => $cardIndex,
                    'media_url' => $mediaUrl,
                    'error' => $e->getMessage(),
                    'tenant_id' => $this->tenant_id,
                ], $e, $this->tenant_id);
            }
        }

        return $mediaIds;
    }
}

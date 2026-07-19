<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Models\Tenant\FacebookMessengerTemplate;
use App\Models\Tenant\FbMessengerCampaign;
use App\Models\Tenant\FbMessengerCampaignDetail;
use App\Models\Tenant\Group;
use App\Models\Tenant\Source;
use App\Models\Tenant\Status;
use App\Rules\PurifiedInput;
use App\Services\FeatureService;
use Carbon\Carbon;
use Corbital\LaravelEmails\Services\MergeFieldsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FBMessengerCampaignController extends Controller
{
    protected int $tenantId;

    protected string $tenantSubdomain;

    protected FeatureService $featureLimitChecker;

    public function __construct()
    {
        $this->tenantId = tenant_id();
        $this->tenantSubdomain = tenant_subdomain_by_tenant_id($this->tenantId);
        $this->featureLimitChecker = app(FeatureService::class);
    }

    /**
     * Display campaign create/edit page
     */
    public function create(string $subdomain, ?int $campaignId = null)
    {
        if (! checkPermission(['tenant.facebook_messenger.campaigns.view'])) {
            session()->flash('notification', ['type' => 'danger', 'message' => t('access_denied_note')]);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }

        $statuses = Status::select('id', 'name')->orderBy('name')->get();
        $sources = Source::select('id', 'name')->orderBy('name')->get();
        $groups = Group::select('id', 'name')->orderBy('name')->get();

        return view('tenant.facebook-messenger.campaign', [
            'subdomain' => $this->tenantSubdomain,
            'campaignId' => $campaignId,
            'statuses' => $statuses,
            'sources' => $sources,
            'groups' => $groups,
        ]);
    }

    /**
     * Display campaign edit page
     */
    public function edit(string $subdomain, int $id)
    {
        return $this->create($subdomain, $id);
    }

    /**
     * Get initial data for the Vue component
     */
    public function getData(string $subdomain, ?int $campaignId = null): JsonResponse
    {
        if (! checkPermission(['tenant.facebook_messenger.campaigns.create', 'tenant.facebook_messenger.campaigns.view'])) {
            return response()->json([
                'success' => false,
                'message' => t('access_denied_note'),
            ], 403);
        }

        $data = [
            'templates' => $this->getTemplates(),
            'mergeFields' => $this->getMergeFields(),
            'relationTypes' => \App\Enum\Tenant\WhatsAppTemplateRelationType::getRelationtype(),
            'systemSettings' => [
                'date_format' => get_tenant_setting_from_db('system', 'date_format', 'd-m-Y'),
                'time_format' => get_tenant_setting_from_db('system', 'time_format', '24'),
                'timezone' => get_tenant_setting_from_db('system', 'timezone', config('app.timezone')),
            ],
            'campaign' => null,
        ];

        if ($campaignId) {
            $campaign = FbMessengerCampaign::with('fbTemplate')->find($campaignId);

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
                'fb_template_id' => $campaign->fb_template_id,
                'template' => $campaign->fbTemplate ? [
                    'id' => $campaign->fbTemplate->id,
                    'name' => $campaign->fbTemplate->name,
                    'content_type' => $campaign->fbTemplate->content_type,
                    'message_text' => $campaign->fbTemplate->message_text,
                    'buttons' => $campaign->fbTemplate->buttons,
                ] : null,
                'select_all' => $campaign->select_all ?? false,
                'send_now' => $campaign->send_now ?? false,
                'scheduled_send_time' => $campaign->scheduled_send_time?->toISOString(),
                'rel_data' => $campaign->rel_data,
                'selected_contact_ids' => $campaign->rel_data['selected_contact_ids'] ?? [],
                'status_filters' => $campaign->rel_data['status_ids'] ?? [],
                'source_filters' => $campaign->rel_data['source_ids'] ?? [],
                'group_filters' => $campaign->rel_data['group_ids'] ?? [],
                'body_params' => $campaign->body_params,
                'variables_json' => $campaign->variables_json ?? [],
                'media_url' => $campaign->media_url ? url('storage/'.$campaign->media_url) : null,
                'media_filename' => $campaign->media_filename,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Store or update a campaign
     */
    public function store(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.facebook_messenger.campaigns.create'])) {
            return response()->json(['success' => false, 'message' => t('access_denied')], 403);
        }

        $isNewCampaign = ! $request->id;

        // Validation rules
        $rules = [
            'campaign_name' => ['required', 'string', 'min:3', 'max:100', new PurifiedInput(t('sql_injection_error'))],
            'rel_type' => ['required', 'string'],
            'fb_template_id' => ['required', 'integer', 'exists:facebook_messenger_templates,id'],
            'select_all' => 'required|in:0,1',
            'send_now' => 'required|in:0,1',
            'scheduled_send_time' => 'nullable|string',
            'contact_ids' => 'nullable|string', // JSON array of contact IDs
            'status_ids' => 'nullable|string', // JSON array of status IDs
            'source_ids' => 'nullable|string', // JSON array of source IDs
            'group_ids' => 'nullable|string', // JSON array of group IDs
            'variable_inputs' => 'nullable|string', // JSON array of variable inputs with merge fields
        ];

        $validator = Validator::make($request->all(), $rules, [
            'campaign_name.required' => t('campaign_name_required'),
            'fb_template_id.required' => t('template_required'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => t('validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $campaign = $request->id ? FbMessengerCampaign::findOrFail($request->id) : new FbMessengerCampaign;
            $isNewRecord = ! $campaign->exists;

            // Set model attributes
            $campaign->tenant_id = $this->tenantId;
            $campaign->name = $request->campaign_name;
            $campaign->rel_type = $request->rel_type;
            $campaign->fb_template_id = $request->fb_template_id;
            $campaign->select_all = $request->select_all == 1;
            $campaign->send_now = $request->send_now == 1;

            // Process scheduled time
            $campaign->scheduled_send_time = $this->processScheduledTime([
                'send_now' => $request->send_now == 1,
                'scheduled_send_time' => $request->scheduled_send_time,
            ]);

            // Store contact selection and filter data
            $campaign->rel_data = [
                'status_ids' => $request->status_ids ? json_decode($request->status_ids, true) : [],
                'source_ids' => $request->source_ids ? json_decode($request->source_ids, true) : [],
                'group_ids' => $request->group_ids ? json_decode($request->group_ids, true) : [],
                'selected_contact_ids' => $request->contact_ids ? json_decode($request->contact_ids, true) : [],
            ];

            // Store variable inputs (merge field mappings for template variables)
            $campaign->body_params = $request->variable_inputs ?: '[]';
            $campaign->variables_json = $request->variable_inputs
                ? json_decode($request->variable_inputs, true)
                : [];

            // Handle file upload if present (for image/video/document templates)
            if ($request->hasFile('file')) {
                // Delete old file if exists
                if (! empty($campaign->media_url) && Storage::disk('public')->exists($campaign->media_url)) {
                    Storage::disk('public')->delete($campaign->media_url);
                }

                // Store new file and get path
                $file = $request->file('file');
                $campaign->media_filename = $file->getClientOriginalName();
                $campaign->media_url = $file->store('tenant/'.$this->tenantId.'/fb-campaign', 'public');
            }

            // Calculate sending count
            $validatedData = [
                'select_all' => $request->select_all == 1,
                'contact_ids' => $request->contact_ids ? json_decode($request->contact_ids, true) : [],
                'status_ids' => $request->status_ids ? json_decode($request->status_ids, true) : [],
                'source_ids' => $request->source_ids ? json_decode($request->source_ids, true) : [],
                'group_ids' => $request->group_ids ? json_decode($request->group_ids, true) : [],
            ];

            $contacts = $this->getSelectedContacts($validatedData);
            $campaign->total_count = $contacts->count();

            // Save campaign
            $campaign->save();

            // Delete existing campaign details if updating
            if (! $isNewRecord) {
                $campaign->details()->delete();
            }

            // Create campaign details for contacts
            $this->createCampaignDetails($campaign, $contacts);

            return response()->json([
                'success' => true,
                'message' => $isNewRecord
                    ? t('campaign_created_successfully')
                    : t('campaign_updated_successfully'),
                'campaign_id' => $campaign->id,
                'redirect_url' => tenant_route('tenant.facebook-messenger.campaigns'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => t('error_saving_campaign').': '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get campaign details page
     */
    public function details(string $subdomain, int $id)
    {
        if (! checkPermission(['tenant.facebook_messenger.campaigns.view'])) {
            session()->flash('notification', ['type' => 'danger', 'message' => t('access_denied_note')]);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }

        $campaign = FbMessengerCampaign::with('fbTemplate')->findOrFail($id);

        return view('tenant.facebook-messenger.campaign-details', [
            'subdomain' => $this->tenantSubdomain,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Get contacts with Facebook PSID (paginated)
     */
    public function getContacts(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 25);
        $search = $request->string('search')->toString();
        $statusIds = $request->input('status_ids', []);
        $sourceIds = $request->input('source_ids', []);
        $groupIds = $request->input('group_ids', []);

        $query = Contact::fromTenant($this->tenantSubdomain)
            ->whereNotNull('facebook_psid')
            ->where('facebook_psid', '!=', '')
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('is_opted_out')
                    ->orWhere('is_opted_out', 0);
            });

        // Apply permission-based filtering
        if (! Auth::user()->is_admin && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', auth()->id());
        }

        // Apply search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
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

        $paginator = $query->select(['id', 'firstname', 'lastname', 'email', 'phone', 'facebook_psid'])
            ->orderBy('firstname')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => trim("{$contact->firstname} {$contact->lastname}"),
                'email' => $contact->email,
                'phone' => $contact->phone,
                'facebook_psid' => $contact->facebook_psid,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Count contacts matching filters (with Facebook PSID only)
     */
    public function countContacts(Request $request): JsonResponse
    {
        $selectAll = $request->boolean('select_all');
        $contactIds = $request->input('contact_ids', []);
        $statusIds = $request->input('status_ids', []);
        $sourceIds = $request->input('source_ids', []);
        $groupIds = $request->input('group_ids', []);

        if (! $selectAll && ! empty($contactIds)) {
            return response()->json(['count' => count($contactIds)]);
        }

        $query = Contact::fromTenant($this->tenantSubdomain)
            ->whereNotNull('facebook_psid')
            ->where('facebook_psid', '!=', '')
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('is_opted_out')
                    ->orWhere('is_opted_out', 0);
            });

        // Apply permission-based filtering
        if (! Auth::user()->is_admin && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', auth()->id());
        }

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

        return response()->json(['count' => $query->count()]);
    }

    /**
     * Get contacts with Facebook PSID (for ContactSelection component)
     */
    public function getContactsPaginated(Request $request): JsonResponse
    {
        $page = $request->integer('page', 1);
        $perPage = 50;
        $statusIds = $request->input('status_ids', []);
        $sourceIds = $request->input('source_ids', []);
        $groupIds = $request->input('group_ids', []);

        $query = Contact::fromTenant($this->tenantSubdomain)
            ->whereNotNull('facebook_psid')
            ->where('facebook_psid', '!=', '')
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

        $total = $query->count();
        $results = $query->select(['id', 'firstname', 'lastname', 'phone', 'facebook_psid'])
            ->orderBy('firstname')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => trim("{$contact->firstname} {$contact->lastname}"),
                'phone' => $contact->phone,
                'facebook_psid' => $contact->facebook_psid,
            ]);

        return response()->json([
            'success' => true,
            'data' => $results,
            'total' => $total,
            'has_more' => ($page * $perPage) < $total,
            'current_page' => $page,
        ]);
    }

    /**
     * Search contacts with Facebook PSID
     */
    public function searchContacts(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();
        $statusIds = $request->input('status_ids', []);
        $sourceIds = $request->input('source_ids', []);
        $groupIds = $request->input('group_ids', []);

        $query = Contact::fromTenant($this->tenantSubdomain)
            ->whereNotNull('facebook_psid')
            ->where('facebook_psid', '!=', '')
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('is_opted_out')
                    ->orWhere('is_opted_out', 0);
            });

        // Apply permission-based filtering
        if (! Auth::user()->is_admin && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', auth()->id());
        }

        // Apply search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('facebook_psid', 'like', "%{$search}%");
            });
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

        $total = $query->count();
        $results = $query->select(['id', 'firstname', 'lastname', 'phone', 'facebook_psid'])
            ->orderBy('firstname')
            ->limit(100)
            ->get()
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => trim("{$contact->firstname} {$contact->lastname}"),
                'phone' => $contact->phone,
                'facebook_psid' => $contact->facebook_psid,
            ]);

        return response()->json([
            'success' => true,
            'data' => $results,
            'total' => $total,
        ]);
    }

    /**
     * Get available FB Messenger templates
     */
    protected function getTemplates(): array
    {
        return FacebookMessengerTemplate::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->get()
            ->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'content_type' => $template->content_type,
                'message_text' => $template->message_text,
                'media_url' => $template->media_url,
                'buttons' => $template->buttons,
                'button_count' => $template->getButtonCount(),
            ])
            ->toArray();
    }

    /**
     * Get merge fields for variable substitution
     */
    protected function getMergeFields(): array
    {
        $mergeFieldsService = app(MergeFieldsService::class);

        $fields = array_merge(
            $mergeFieldsService->getFieldsForTemplate('tenant-contact-group'),
            $mergeFieldsService->getFieldsForTemplate('tenant-other-group'),
        );

        return array_map(fn ($value) => [
            'key' => ucfirst($value['name']),
            'value' => str_replace(['{', '}'], '', $value['key']),
        ], $fields);
    }

    /**
     * Process scheduled time
     */
    protected function processScheduledTime(array $data): ?Carbon
    {
        if ($data['send_now'] ?? false) {
            return null;
        }

        if (empty($data['scheduled_send_time'])) {
            return null;
        }

        try {
            $timezone = get_tenant_setting_from_db('system', 'timezone', config('app.timezone'));

            return Carbon::parse($data['scheduled_send_time'], $timezone)->utc();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get selected contacts for campaign
     */
    protected function getSelectedContacts(array $data): Collection
    {
        $query = Contact::fromTenant($this->tenantSubdomain)
            ->whereNotNull('facebook_psid')
            ->where('facebook_psid', '!=', '')
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('is_opted_out')
                    ->orWhere('is_opted_out', 0);
            });

        // Apply permission-based filtering
        if (! Auth::user()->is_admin && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', auth()->id());
        }

        if ($data['select_all'] ?? false) {
            // Apply filters for select all
            if (! empty($data['status_ids'])) {
                $query->whereIn('status_id', $data['status_ids']);
            }

            if (! empty($data['source_ids'])) {
                $query->whereIn('source_id', $data['source_ids']);
            }

            if (! empty($data['group_ids'])) {
                $query->where(function ($q) use ($data) {
                    foreach ($data['group_ids'] as $groupId) {
                        $q->orWhereRaw('JSON_CONTAINS(group_id, ?)', [json_encode([$groupId])]);
                    }
                });
            }
        } else {
            // Use selected contact IDs
            $contactIds = $data['contact_ids'] ?? [];

            if (empty($contactIds)) {
                return collect([]);
            }

            $query->whereIn('id', $contactIds);
        }

        return $query->get();
    }

    /**
     * Create campaign details for selected contacts
     */
    protected function createCampaignDetails(FbMessengerCampaign $campaign, Collection $contacts): void
    {
        if ($contacts->isEmpty()) {
            return;
        }

        $template = $campaign->fbTemplate;
        $campaignDetails = [];

        foreach ($contacts as $contact) {
            $messageText = $template ? $template->getMergedMessage($contact) : '';

            $campaignDetails[] = [
                'campaign_id' => $campaign->id,
                'tenant_id' => $this->tenantId,
                'contact_id' => $contact->id,
                'facebook_psid' => $contact->facebook_psid,
                'message_text' => $messageText,
                'status' => FbMessengerCampaignDetail::STATUS_PENDING,
                'message_status' => 'pending',
                'fb_message_id' => null,
                'response_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks of 1000
        $chunks = array_chunk($campaignDetails, 1000);

        foreach ($chunks as $chunk) {
            FbMessengerCampaignDetail::insert($chunk);
        }
    }
}

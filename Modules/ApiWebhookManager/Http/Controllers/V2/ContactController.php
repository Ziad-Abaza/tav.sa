<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Services\FeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Requests\V2\Contact\StoreContactRequest;
use Modules\ApiWebhookManager\Http\Requests\V2\Contact\UpdateContactRequest;
use Modules\ApiWebhookManager\Http\Resources\V2\ContactCollection;
use Modules\ApiWebhookManager\Http\Resources\V2\ContactResource;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Services\V2\BatchOperationService;
use Modules\ApiWebhookManager\Services\V2\QueryBuilderService;

class ContactController extends Controller
{
    public function __construct(
        public FeatureService $featureService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subdomain = $request->get('tenant_subdomain');
        $tenantId = $request->get('tenant_id');

        try {
            $query = Contact::fromTenant($subdomain)->newQuery();

            // Handle group_id filter separately since it uses JSON contains
            if ($request->has('filter.group_id')) {
                $groupId = $request->input('filter.group_id');
                $query->inGroup($groupId);
            }

            $queryBuilder = QueryBuilderService::for($query, $request)
                ->allowedFilters(['type', 'status_id', 'source_id', 'assigned_id', 'created_at'])
                ->allowedSorts(['id', 'firstname', 'lastname', 'created_at', 'updated_at'])
                ->searchable(['firstname', 'lastname', 'email', 'phone', 'company'])
                ->apply();

            $contacts = $queryBuilder->paginate($request->input('per_page', 15));

            // Handle includes manually due to dynamic table names
            $includes = $request->input('include');
            if ($includes) {
                $allowedIncludes = ['groups', 'source', 'status'];
                $includes = array_filter(
                    is_array($includes) ? $includes : explode(',', $includes),
                    fn ($include) => in_array($include, $allowedIncludes)
                );

                if (! empty($includes)) {
                    foreach ($contacts as $contact) {
                        foreach ($includes as $include) {
                            $this->loadInclude($contact, $include, $subdomain, $tenantId);
                        }
                    }
                }
            }

            return ApiResponse::collection(new ContactCollection($contacts));
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch contacts',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            if ($this->featureService->hasReachedLimit('contacts', Contact::class)) {
                $limit = $this->featureService->getLimit('contacts');
                $current = $this->featureService->getCurrentUsage('contacts');

                return ApiResponse::featureLimitExceeded('contacts', $current, $limit);
            }

            $contactData = $request->validated();
            $contactData['tenant_id'] = $tenantId;
            $contactData['addedfrom'] = $tenantId;

            $groups = $contactData['groups'] ?? null;
            unset($contactData['groups']);

            $contact = Contact::fromTenant($subdomain)->create($contactData);

            if ($groups) {
                $contact->setGroups($groups);
            }

            $this->featureService->trackUsage('contacts');

            $this->loadInclude($contact, 'groups', $subdomain, $tenantId);

            return ApiResponse::created(
                new ContactResource($contact),
                'Contact created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to create contact',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $subdomain = $request->get('tenant_subdomain');
        $tenantId = $request->get('tenant_id');

        try {
            $contact = Contact::fromTenant($subdomain)
                ->where('id', $id)
                ->first();

            if (! $contact) {
                return ApiResponse::notFound('Contact not found', 'contact');
            }

            $includes = $request->input('include');
            if ($includes) {
                $allowedIncludes = ['groups', 'source', 'status'];
                $includes = array_filter(
                    is_array($includes) ? $includes : explode(',', $includes),
                    fn ($include) => in_array($include, $allowedIncludes)
                );

                foreach ($includes as $include) {
                    $this->loadInclude($contact, $include, $subdomain, $tenantId);
                }
            }

            return ApiResponse::resource(new ContactResource($contact));
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch contact',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function update(UpdateContactRequest $request, int $id): JsonResponse
    {
        $subdomain = $request->get('tenant_subdomain');
        $tenantId = $request->get('tenant_id');

        try {
            $contact = Contact::fromTenant($subdomain)
                ->where('id', $id)
                ->first();

            if (! $contact) {
                return ApiResponse::notFound('Contact not found', 'contact');
            }

            $contactData = $request->validated();
            $groups = $contactData['groups'] ?? null;
            unset($contactData['groups']);

            $contact->update($contactData);

            if ($groups !== null) {
                $contact->setGroups($groups);
            }

            $contact = $contact->fresh();
            $this->loadInclude($contact, 'groups', $subdomain, $tenantId);

            return ApiResponse::updated(
                new ContactResource($contact),
                'Contact updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to update contact',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $subdomain = $request->get('tenant_subdomain');

        try {
            $contact = Contact::fromTenant($subdomain)
                ->where('id', $id)
                ->first();

            if (! $contact) {
                return ApiResponse::notFound('Contact not found', 'contact');
            }

            $contact->delete();

            return ApiResponse::deleted('Contact deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to delete contact',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function batch(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        $validationRules = [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'type' => ['required', 'in:lead,customer,guest'],
            'source_id' => ['required', 'integer'],
            'status_id' => ['required', 'integer'],
            'company' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'country_id' => ['nullable', 'integer'],
            'assigned_id' => ['nullable', 'integer'],
        ];

        try {
            $contacts = $request->input('contacts', []);
            $options = $request->input('options', []);

            // TODO: BatchOperationService needs to be updated to support dynamic tables via fromTenant()
            $result = BatchOperationService::for(Contact::class, $tenantId, $subdomain)
                ->batchCreate(
                    $contacts,
                    $validationRules,
                    $options['continue_on_error'] ?? true,
                    $options['skip_duplicates'] ?? false,
                    'phone'
                );

            if (! $result['success']) {
                return ApiResponse::error(
                    ErrorCode::VALIDATION_ERROR,
                    'Batch operation failed',
                    $result
                );
            }

            $this->featureService->syncModelCount('contacts', Contact::class);

            return ApiResponse::success($result, 'Batch operation completed');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Batch operation failed',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $ids = $request->input('ids', []);

            // TODO: BatchOperationService needs to be updated to support dynamic tables via fromTenant()
            $result = BatchOperationService::for(Contact::class, $tenantId, $subdomain)
                ->batchDelete($ids);

            if (! $result['success']) {
                return ApiResponse::error(
                    ErrorCode::INTERNAL_ERROR,
                    'Batch delete failed',
                    $result
                );
            }

            return ApiResponse::success($result, 'Contacts deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Batch delete failed',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Load relationship data for a contact with dynamic table support
     */
    protected function loadInclude(Contact $contact, string $include, string $subdomain, int $tenantId): void
    {
        switch ($include) {
            case 'groups':
                $contact->setRelation('groups', $contact->groups());
                break;

            case 'source':
                if ($contact->source_id) {
                    $source = \App\Models\Tenant\Source::where('id', $contact->source_id)
                        ->where('tenant_id', $tenantId)
                        ->first();

                    $contact->setRelation('source', $source);
                }
                break;

            case 'status':
                if ($contact->status_id) {
                    $status = \App\Models\Tenant\Status::where('id', $contact->status_id)
                        ->where('tenant_id', $tenantId)
                        ->first();

                    $contact->setRelation('status', $status);
                }
                break;
        }
    }
}

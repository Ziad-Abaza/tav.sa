<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Resources\V2\GroupResource;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Services\V2\QueryBuilderService;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $query = Group::where('tenant_id', $tenantId);

            $groups = QueryBuilderService::for($query, $request)
                ->allowedFilters(['created_at'])
                ->allowedSorts(['name', 'created_at', 'updated_at'])
                ->searchable(['name'])
                ->apply()
                ->paginate();

            return ApiResponse::paginated($groups, function ($group) {
                return (new GroupResource($group))->resolve();
            });
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch groups',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:groups,name,NULL,id,tenant_id,'.$tenantId],
            ], [
                'name.required' => 'Group name is required.',
                'name.unique' => 'A group with this name already exists.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $group = Group::create([
                'tenant_id' => $tenantId,
                'name' => $request->input('name'),
            ]);

            return ApiResponse::created(
                new GroupResource($group),
                'Group created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to create group',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $group = Group::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $group) {
                return ApiResponse::notFound('Group not found', 'group');
            }

            return ApiResponse::resource(new GroupResource($group));
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch group',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $group = Group::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $group) {
                return ApiResponse::notFound('Group not found', 'group');
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:groups,name,'.$id.',id,tenant_id,'.$tenantId],
            ], [
                'name.required' => 'Group name is required.',
                'name.unique' => 'A group with this name already exists.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $group->update([
                'name' => $request->input('name'),
            ]);

            return ApiResponse::updated(
                new GroupResource($group->fresh()),
                'Group updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to update group',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $group = Group::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $group) {
                return ApiResponse::notFound('Group not found', 'group');
            }

            $group->delete();

            return ApiResponse::deleted('Group deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to delete group',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function addContacts(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $group = Group::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $group) {
                return ApiResponse::notFound('Group not found', 'group');
            }

            // Get tenant-specific table name for validation
            $contactTable = \App\Models\Tenant\Contact::fromTenant($subdomain)->getTable();

            $validator = Validator::make($request->all(), [
                'contact_ids' => ['required', 'array', 'min:1'],
                'contact_ids.*' => ['required', 'integer', "exists:{$contactTable},id"],
            ], [
                'contact_ids.required' => 'Contact IDs are required.',
                'contact_ids.array' => 'Contact IDs must be an array.',
                'contact_ids.*.exists' => 'One or more contact IDs do not exist.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $contactIds = $request->input('contact_ids');

            // Verify all contacts belong to the same tenant
            $contactsCount = \App\Models\Tenant\Contact::fromTenant($subdomain)
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $contactIds)
                ->count();

            if ($contactsCount !== count($contactIds)) {
                return ApiResponse::error(
                    ErrorCode::VALIDATION_ERROR,
                    'One or more contacts do not belong to this tenant'
                );
            }

            // Add contacts to group
            $addedCount = 0;
            foreach ($contactIds as $contactId) {
                $contact = \App\Models\Tenant\Contact::fromTenant($subdomain)
                    ->where('id', $contactId)
                    ->first();

                if ($contact) {
                    $existingGroups = $contact->group_id ?? [];
                    if (! in_array($id, $existingGroups)) {
                        $existingGroups[] = $id;
                        $contact->setGroups($existingGroups);
                        $addedCount++;
                    }
                }
            }

            return ApiResponse::success(
                [
                    'group_id' => $id,
                    'contacts_added' => $addedCount,
                ],
                'Contacts added to group successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to add contacts to group',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function removeContacts(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $group = Group::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $group) {
                return ApiResponse::notFound('Group not found', 'group');
            }

            $validator = Validator::make($request->all(), [
                'contact_ids' => ['required', 'array', 'min:1'],
                'contact_ids.*' => ['required', 'integer'],
            ], [
                'contact_ids.required' => 'Contact IDs are required.',
                'contact_ids.array' => 'Contact IDs must be an array.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $contactIds = $request->input('contact_ids');

            // Remove contacts from group
            $removedCount = 0;
            foreach ($contactIds as $contactId) {
                $contact = \App\Models\Tenant\Contact::fromTenant($subdomain)
                    ->where('tenant_id', $tenantId)
                    ->where('id', $contactId)
                    ->first();

                if ($contact) {
                    $existingGroups = $contact->group_id ?? [];
                    if (in_array($id, $existingGroups)) {
                        $existingGroups = array_values(array_diff($existingGroups, [$id]));
                        $contact->setGroups($existingGroups);
                        $removedCount++;
                    }
                }
            }

            return ApiResponse::success(
                [
                    'group_id' => $id,
                    'contacts_removed' => $removedCount,
                ],
                'Contacts removed from group successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to remove contacts from group',
                ['error' => $e->getMessage()]
            );
        }
    }
}

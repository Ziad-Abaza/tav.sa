<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Resources\V2\StatusResource;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Services\V2\QueryBuilderService;

class StatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $query = Status::where('tenant_id', $tenantId);

            $statuses = QueryBuilderService::for($query, $request)
                ->allowedFilters(['created_at'])
                ->allowedSorts(['name', 'created_at', 'updated_at'])
                ->searchable(['name'])
                ->apply()
                ->paginate();

            return ApiResponse::paginated($statuses, function ($status) {
                return (new StatusResource($status))->resolve();
            });
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch statuses',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:statuses,name,NULL,id,tenant_id,'.$tenantId],
                'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ], [
                'name.required' => 'Status name is required.',
                'name.unique' => 'A status with this name already exists.',
                'color.regex' => 'Color must be a valid hex color code (e.g., #FF5733).',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $status = Status::create([
                'tenant_id' => $tenantId,
                'name' => $request->input('name'),
                'color' => $request->input('color'),
            ]);

            return ApiResponse::created(
                new StatusResource($status),
                'Status created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to create status',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $status = Status::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $status) {
                return ApiResponse::notFound('Status not found', 'status');
            }

            return ApiResponse::resource(new StatusResource($status));
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch status',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $status = Status::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $status) {
                return ApiResponse::notFound('Status not found', 'status');
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:statuses,name,'.$id.',id,tenant_id,'.$tenantId],
                'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ], [
                'name.required' => 'Status name is required.',
                'name.unique' => 'A status with this name already exists.',
                'color.regex' => 'Color must be a valid hex color code (e.g., #FF5733).',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $status->update([
                'name' => $request->input('name'),
                'color' => $request->input('color'),
            ]);

            return ApiResponse::updated(
                new StatusResource($status->fresh()),
                'Status updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to update status',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $status = Status::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $status) {
                return ApiResponse::notFound('Status not found', 'status');
            }

            // Check if status is being used by any contacts
            $subdomain = $request->get('tenant_subdomain');
            $contactsCount = \App\Models\Tenant\Contact::fromTenant($subdomain)
                ->where('tenant_id', $tenantId)
                ->where('status_id', $id)
                ->count();

            if ($contactsCount > 0) {
                return ApiResponse::error(
                    ErrorCode::RESOURCE_CONFLICT,
                    "Cannot delete status. It is being used by {$contactsCount} contact(s)."
                );
            }

            $status->delete();

            return ApiResponse::deleted('Status deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to delete status',
                ['error' => $e->getMessage()]
            );
        }
    }
}

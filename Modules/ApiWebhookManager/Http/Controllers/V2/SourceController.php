<?php

namespace Modules\ApiWebhookManager\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Resources\V2\SourceResource;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;
use Modules\ApiWebhookManager\Services\V2\QueryBuilderService;

class SourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $query = Source::where('tenant_id', $tenantId);

            $sources = QueryBuilderService::for($query, $request)
                ->allowedFilters(['created_at'])
                ->allowedSorts(['name', 'created_at', 'updated_at'])
                ->searchable(['name'])
                ->apply()
                ->paginate();

            return ApiResponse::paginated($sources, function ($source) {
                return (new SourceResource($source))->resolve();
            });
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch sources',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:sources,name,NULL,id,tenant_id,'.$tenantId],
            ], [
                'name.required' => 'Source name is required.',
                'name.unique' => 'A source with this name already exists.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $source = Source::create([
                'tenant_id' => $tenantId,
                'name' => $request->input('name'),
            ]);

            return ApiResponse::created(
                new SourceResource($source),
                'Source created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to create source',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $source = Source::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $source) {
                return ApiResponse::notFound('Source not found', 'source');
            }

            return ApiResponse::resource(new SourceResource($source));
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to fetch source',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $source = Source::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $source) {
                return ApiResponse::notFound('Source not found', 'source');
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:sources,name,'.$id.',id,tenant_id,'.$tenantId],
            ], [
                'name.required' => 'Source name is required.',
                'name.unique' => 'A source with this name already exists.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $source->update([
                'name' => $request->input('name'),
            ]);

            return ApiResponse::updated(
                new SourceResource($source->fresh()),
                'Source updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to update source',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $subdomain = $request->get('tenant_subdomain');

        try {
            $source = Source::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();

            if (! $source) {
                return ApiResponse::notFound('Source not found', 'source');
            }

            // Check if source is being used by any contacts
            $contactsCount = \App\Models\Tenant\Contact::fromTenant($subdomain)
                ->where('tenant_id', $tenantId)
                ->where('source_id', $id)
                ->count();

            if ($contactsCount > 0) {
                return ApiResponse::error(
                    ErrorCode::RESOURCE_CONFLICT,
                    "Cannot delete source. It is being used by {$contactsCount} contact(s)."
                );
            }

            $source->delete();

            return ApiResponse::deleted('Source deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                ErrorCode::INTERNAL_ERROR,
                'Failed to delete source',
                ['error' => $e->getMessage()]
            );
        }
    }
}

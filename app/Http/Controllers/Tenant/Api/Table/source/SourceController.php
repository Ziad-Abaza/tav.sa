<?php

namespace App\Http\Controllers\Tenant\Api\Table\source;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Source;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SourceController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = [
        'name',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'name',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.source.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $query = Source::query();

        // Filters + search
        $this->applyFilters($query, $request);

        // Sorting
        $this->applySorting($query, $request);

        // Pagination
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function (Source $source, int $index) use ($from) {
            return [
                'id' => $source->id,
                'sr_no' => $from + $index,
                'name' => $source->name,
                'can_delete' => checkPermission(['tenant.source.delete']),
                'can_edit' => checkPermission(['tenant.source.edit']),

            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Delete a status (blocked if in use)
     */
    public function destroy(string $id): JsonResponse
    {
        if (! checkPermission('tenant.source.delete')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();
        $subdomain = tenant_subdomain();

        $source = source::where('tenant_id', $tenantId)->findOrFail($id);

        $isUsed = DB::table($subdomain.'_contacts')
            ->where('source_id', $source->id)
            ->exists();

        if ($isUsed) {
            return response()->json([
                'message' => t('source_delete_in_use_notify'),
                'type' => 'warning',
            ], 422);
        }

        $source->delete();

        return response()->json([
            'message' => t('source_deleted_successfully'),
        ]);
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        // Global search
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search) {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }
    }

    /**
     * Apply sorting to the query
     */
    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, $this->sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}

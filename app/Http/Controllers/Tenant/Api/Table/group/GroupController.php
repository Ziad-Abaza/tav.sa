<?php

namespace App\Http\Controllers\Tenant\Api\Table\group;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
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
        if (! checkPermission(['tenant.group.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $query = Group::query();

        // Filters + search
        $this->applyFilters($query, $request);

        // Sorting
        $this->applySorting($query, $request);

        // Pagination
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function (Group $group, int $index) use ($from) {
            return [
                'id' => $group->id,
                'sr_no' => $from + $index,
                'name' => $group->name,
                'can_edit' => checkPermission(['tenant.group.edit']),
                'can_delete' => checkPermission(['tenant.group.delete']),

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
     * Delete a group (blocked if in use)
     */
    public function destroy(string $id): JsonResponse
    {
        if (! checkPermission('tenant.group.delete')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();
        $subdomain = tenant_subdomain();

        $group = Group::where('tenant_id', $tenantId)->findOrFail($id);

        $isUsed = DB::table($subdomain.'_contacts')
            ->where('group_id', $group->id)
            ->exists();

        if ($isUsed) {
            return response()->json([
                'message' => t('group_delete_in_use_notify'),
                'type' => 'warning',
            ], 422);
        }

        $group->delete();

        return response()->json([
            'message' => t('group_deleted_successfully'),
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

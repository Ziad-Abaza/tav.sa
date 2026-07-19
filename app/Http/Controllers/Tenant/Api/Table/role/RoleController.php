<?php

namespace App\Http\Controllers\Tenant\Api\Table\role;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = [
        'id',
        'name',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'name',
    ];

    /**
     * Main data endpoint - returns paginated contact data
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.role.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }
        $tenantId = tenant_id();
        $query = Role::query()
            ->selectRaw('*, (SELECT COUNT(*) FROM `roles` i2 WHERE i2.id <= roles.id AND i2.tenant_id = ?) as row_num', [$tenantId])
            ->where('tenant_id', $tenantId);

        // Apply same filters as the table view
        $this->applyFilters($query, $request);

        // Sort (whitelist enforced)
        $this->applySorting($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each contact row for the frontend
        $from = $paginator->firstItem() ?? 0;
        $items = collect($paginator->items())->map(function (Role $role, int $index) use ($from): array {
            return [
                'id' => $role->id,
                'sr_no' => $from + $index,
                'name' => $role->name,
                'created_at' => $role->created_at?->toISOString(),
                'can_edit' => checkPermission('tenant.role.edit'),
                'can_delete' => checkPermission('tenant.role.delete'),
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
     * Apply filters to the query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        // Global search
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        // Filters
        if ($name = $request->string('name')->toString()) {
            $query->where('name', $name);
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

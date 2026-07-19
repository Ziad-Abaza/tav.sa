<?php

namespace App\Http\Controllers\Admin\Api\Table\department;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tickets\Models\Department;

class DepartmentController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = [
        'id',
        'name',
        'description',
        'tickets_count',
        'status',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'name',
        'description',
    ];

    public function assignees()
    {
        $ids = json_decode($this->assignee_id ?? '[]', true) ?: [];

        return User::whereIn('id', $ids)
            ->select(['id', 'firstname', 'lastname'])
            ->get();
    }

    /**
     * Main data endpoint - returns paginated contact data
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('admin.department.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        // $query = Department::query();

        $query = Department::withCount('tickets');

        // Apply filters and search
        $this->applyFilters($query, $request);

        // Sort (whitelist enforced)
        $this->applySorting($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each contact row for the frontend
        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function (Department $department, int $index): array {
            $userIds = json_decode(
                is_array($department->assignee_id)
                    ? json_encode($department->assignee_id)
                    : $department->assignee_id,
                true
            ) ?: [];

            if (empty($userIds)) {
                $assignees = [];
            } else {
                $assignees = User::whereIn('id', array_slice($userIds, 0, 3))
                    ->select(['id', 'firstname', 'lastname'])
                    ->get()
                    ->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->firstname.' '.$u->lastname,
                    ])
                    ->toArray();
            }

            return [
                'id' => $department->id,
                'name' => $department->name,
                'description' => $department->description,
                'tickets_count' => $department->tickets_count ?? 0,
                // 'assignee_id' => $assignee_id ?? null,
                'assignees' => [
                    'list' => $assignees,
                    'total' => count($userIds),
                    'more' => max(count($userIds) - 3, 0),
                ],

                'status' => (bool) $department->status,
                'can_edit' => checkPermission(['admin.department.edit']),
                'can_delete' => checkPermission(['admin.department.delete']),
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
     * Get filter options for the department table
     */
    public function filters(Request $request): JsonResponse
    {
        if (! checkPermission('admin.department.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        return response()->json([
            'status' => [
                ['value' => 1, 'label' => 'Active'],
                ['value' => 0, 'label' => 'Inactive'],
            ],
        ]);
    }

    public function toggleStatus(string $id): JsonResponse
    {
        if (! checkPermission('admin.department.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $department = Department::findOrFail($id);

        $department->status = ! $department->status;
        $department->save();

        return response()->json([
            'value' => (bool) $department->status,
            'message' => $department->status
                ? t('department_activated')
                : t('department_deactivated'),
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

        $status = $request->input('filters.status', $request->input('status'));

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
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

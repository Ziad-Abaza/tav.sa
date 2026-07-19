<?php

namespace App\Http\Controllers\Admin\Api\Table\user;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserTableController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = [
        'id',
        'firstname',
        'phone',
        'email',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'firstname',
        'lastname',
        'phone',
        'email',
    ];

    /**
     * Main data endpoint - returns paginated contact data
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['admin.users.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $query = User::query()
            ->where('id', '!=', auth()->id())
            ->where('user_type', '=', 'admin');

        // Apply filters and search
        $this->applyFilters($query, $request);

        // Sort (whitelist enforced)
        $this->applySorting($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each contact row for the frontend
        $from = $paginator->firstItem() ?? 0;
        $items = collect($paginator->items())->map(function (User $user, int $index) use ($from): array {
            return [
                'id' => $user->id,
                'sr_no' => $from + $index,
                'firstname' => trim($user->firstname.' '.$user->lastname),
                'phone' => $user->phone,
                'email' => $user->email,
                'active' => (bool) $user->active,
                'created_at' => $user->created_at?->toISOString(),
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
     * Toggle the is_active flag on a user.
     */
    public function toggleActive(string $id): JsonResponse
    {
        if (! checkPermission('admin.users.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $user = User::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $user->update(['active' => ! $user->active]);

        return response()->json([
            'value' => (bool) $user->active,
            'message' => t('status_update_successfully'),
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

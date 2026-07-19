<?php

namespace App\Http\Controllers\Admin\Api\Table\roles;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRoleAssigneeTableController extends Controller
{
    private array $sortable = ['firstname', 'created_at'];

    private array $searchable = ['firstname', 'lastname'];

    public function index(Request $request): JsonResponse
    {
        $roleId = $request->integer('role_id');

        $query = $roleId
            ? User::where('role_id', $roleId)->where('user_type', 'admin')
            : User::whereRaw('1 = 0');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(
                fn (User $user, int $index) => [
                    'id' => $user->id,
                    'sr_no' => $from + $index,
                    'name' => trim($user->firstname.' '.$user->lastname),
                    'created_at' => $user->created_at?->toISOString(),
                ]
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                foreach ($this->searchable as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $dir = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy(
            in_array($sort, $this->sortable, true) ? $sort : 'created_at',
            $dir
        );
    }
}

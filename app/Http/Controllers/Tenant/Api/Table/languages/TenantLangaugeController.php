<?php

namespace App\Http\Controllers\Tenant\Api\Table\languages;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantLangaugeController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = ['name', 'code', 'created_at'];

    /** @var string[] Columns included in global search */
    private array $searchable = ['name', 'code'];

    /**
     * Main data endpoint — returns paginated bot flow data.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Language::query()
            ->where('tenant_id', tenant_id());

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(
                fn (Language $row, int $index) => [
                    'id' => $row->id,
                    'sr_no' => $from + $index,
                    'name' => $row->name,
                    'code' => $row->code,
                    'created_at' => $row->created_at?->toISOString(),
                ]
            ),
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
        $query->orderBy(in_array($sort, $this->sortable, true) ? $sort : 'created_at', $dir);
    }
}

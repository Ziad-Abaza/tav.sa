<?php

namespace App\Http\Controllers\Admin\Api\Table\languages;

use App\Http\Controllers\Controller;
use App\Models\TenantLanguage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTenantLanguageController extends Controller
{
    /** @var string[] Whitelisted sortable columns */
    private array $sortable = ['id', 'name', 'code', 'created_at'];

    /** @var string[] Columns included in global search */
    private array $searchable = ['name', 'code'];

    /**
     * Main data endpoint — returns paginated tenant data.
     */
    public function index(Request $request): JsonResponse
    {

        $query = TenantLanguage::query();

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($row, int $index) => [
                'id' => $row->id,
                'sr_no' => $from + $index,
                'name' => $row->name,
                'code' => $row->code,
                'created_at' => $row->created_at?->toISOString(),
            ]),
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
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, $this->sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}

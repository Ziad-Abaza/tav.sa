<?php

namespace App\Http\Controllers\Admin\Api\Table\credit;

use App\Http\Controllers\Controller;
use App\Models\TenantCreditBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditTableController extends Controller
{
    private array $sortable = ['id', 'balance', 'updated_at'];

    private array $searchable = [
        'tenant.company_name',
    ];

    public function index(Request $request): JsonResponse
    {
        // Eager load the 'tenant' relationship here to prevent lazy loading errors
        $query = TenantCreditBalance::query()->with('tenant');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(function ($row) {
                // Now $row->tenant is already loaded and won't throw an error
                $customer = $row->tenant?->company_name ?? 'N/A';

                $balance = $row->balance
                    ? get_base_currency()->format($row->balance)
                    : '-';

                return [
                    'id' => $row->id,
                    'customer' => $customer,
                    'balance' => $balance,
                    'updated_at' => $row->updated_at?->diffForHumans(),
                    'updated_at_full' => $row->updated_at?->format('Y-m-d H:i:s'),
                    'tenant_id' => $row->tenant_id,
                ];
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
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

                foreach ($this->searchable as $column) {

                    if (str_contains($column, '.')) {
                        [$relation, $relColumn] = explode('.', $column);

                        $q->orWhereHas($relation, function (Builder $relQuery) use ($relColumn, $search) {
                            $relQuery->where($relColumn, 'like', "%{$search}%");
                        });

                    } else {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
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

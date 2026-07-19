<?php

namespace App\Http\Controllers\Admin\Api\Table\coupon;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = [
        'code',
        'name',
        'type',
        'value',
        'is_active',
        'usage_count',
        'expires_at',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'code',
        'name',
        'is_active',
    ];

    /**
     * Main data endpoint - returns paginated contact data
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('admin.coupon.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $query = Coupon::query();

        // Apply filters and search
        $this->applyFilters($query, $request);

        // Sort (whitelist enforced)
        $this->applySorting($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each contact row for the frontend
        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function (Coupon $coupon, int $index): array {
            return [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'value' => $coupon->type === 'percentage'
                    ? rtrim(rtrim(number_format((float) $coupon->value, 2, '.', ''), '2'), '.').'%'
                    : get_base_currency()->format($coupon->value),

                'is_active' => $coupon->is_active ? 'Active' : 'Inactive',

                'usage_count' => $coupon->usage_limit
                    ? $coupon->usage_count.' / '.$coupon->usage_limit
                    : $coupon->usage_count.' / ∞',
                'expires_at' => $coupon->expires_at
                    ? $coupon->expires_at->format('Y-m-d')
                    : '-',
                'can_edit' => checkPermission(['admin.coupon.edit']),
                'can_delete' => checkPermission(['admin.coupon.delete']),
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

    public function filters(Request $request): JsonResponse
    {
        if (! checkPermission('admin.coupon.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        return response()->json([
            'is_active' => [
                ['value' => 1, 'label' => 'Active'],
                ['value' => 0, 'label' => 'Inactive'],
            ],
            'type' => [
                ['value' => 'fixed_amount', 'label' => 'Fixed Amount'],
                ['value' => 'percentage', 'label' => 'Percentage'],
            ],
            'expires_at' => [
                ['value' => 'expired', 'label' => 'Expired'],
                ['value' => 'active', 'label' => 'Not Expired'],
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

        $is_active = $request->input('filters.is_active', $request->input('is_active'));

        if ($is_active !== null && $is_active !== '') {
            $query->where('is_active', (int) $is_active);
        }

        $type = $request->input('filters.type', $request->input('type'));
        if (! empty($type)) {
            $query->where('type', $type);
        }

        $expires = $request->input('filters.expires_at', $request->input('expires_at'));

        if ($expires === 'expired') {
            $query->whereNotNull('expires_at')
                ->where('expires_at', '<', now());
        }

        if ($expires === 'active') {
            $query->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
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

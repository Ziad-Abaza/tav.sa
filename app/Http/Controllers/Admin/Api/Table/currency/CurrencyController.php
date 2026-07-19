<?php

namespace App\Http\Controllers\Admin\Api\Table\currency;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\CurrencyCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = [
        'id',
        'name',
        'symbol',
        'is_default',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'name',
        'symbol',
    ];

    /**
     * Main data endpoint - returns paginated contact data
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('admin.currency.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $query = Currency::query();

        // Apply filters and search
        $this->applyFilters($query, $request);

        // Sort (whitelist enforced)
        $this->applySorting($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each contact row for the frontend
        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function (Currency $currency, int $index): array {
            return [
                'id' => $currency->id,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'is_default' => $currency->is_default,
                'can_edit' => checkPermission(['admin.currency.edit']),
                'can_delete' => checkPermission(['admin.currency.delete']),
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

    public function toggleStatus(string $id): JsonResponse
    {
        if (! checkPermission('admin.department.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $currency = Currency::findOrFail($id);

        $existingCurrency = Currency::query()
            ->where('is_default', 1)
            ->first();

        if ($existingCurrency) {
            $plans = Plan::where('currency_id', $existingCurrency->id)->get();

            if ($plans->isNotEmpty()) {
                $hasSubscription = Subscription::whereIn('plan_id', $plans->pluck('id'))->exists();

                if ($hasSubscription) {
                    return response()->json([
                        'value' => (bool) $existingCurrency->is_default,
                        'message' => t('cannot_change_base_currency_subscription_exists'),
                    ], 422);
                }
            }
        }

        // Turning ON base currency
        if (! $currency->is_default) {
            Currency::query()->update(['is_default' => 0]);

            $oldCurrencyId = $existingCurrency?->id;

            $currency->is_default = 1;
            $currency->save();

            if ($oldCurrencyId) {
                Plan::where('currency_id', $oldCurrencyId)
                    ->update(['currency_id' => $currency->id]);
            }

            CurrencyCache::clearCache();

            return response()->json([
                'value' => true,
                'message' => t('update_base_currency'),
            ]);
        }

        // Trying to turn OFF last base currency
        $currentDefaults = Currency::query()->where('is_default', 1)->count();

        if ($currentDefaults <= 1) {
            return response()->json([
                'value' => true,
                'message' => t('must_one_base_currency'),
            ], 422);
        }

        // Otherwise allow disabling
        $currency->is_default = 0;
        $currency->save();

        CurrencyCache::clearCache();

        return response()->json([
            'value' => false,
            'message' => t('currency_deactivated'),
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

<?php

namespace App\Http\Controllers\Admin\Api\Table\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionTableController extends Controller
{
    /** @var string[] Columns included in global search */
    private array $searchable = ['tenant.company_name', 'plan.name', 'status'];

    /**
     * Main data endpoint — returns paginated tenant data.
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('admin.tenants.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $query = Subscription::query()->with(['plan', 'tenant']);

        $this->applyFilters($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($row, int $index) => [
                'id' => $row->id,
                'sr_no' => $from + $index,
                'company_name' => $row->tenant->company_name,
                'plan' => $row->plan->name,
                'status' => $row->status,
                'current_period_ends_at' => $row->current_period_ends_at,
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
        // Global search
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {

                // Search in subscription table column
                $q->orWhere('status', 'like', "%{$search}%");

                // Search in tenant relation
                $q->orWhereHas('tenant', function (Builder $tenant) use ($search) {
                    $tenant->where('company_name', 'like', "%{$search}%");
                });

                // Search in plan relation
                $q->orWhereHas('plan', function (Builder $plan) use ($search) {
                    $plan->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }
    }
}

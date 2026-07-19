<?php

namespace App\Http\Controllers\Admin\Api\Table\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicesTableController extends Controller
{
    /**
     * Main data endpoint — returns paginated tenant data.
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['admin.invoices.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $subscriptionId = $request->integer('subscription_id');

        // Fetch specific subscription
        $subscription = Subscription::with(['invoices'])
            ->where('id', $subscriptionId)
            ->firstOrFail();

        $subscription = apply_filters('before_rendar_subscription_list', $subscription);

        // Paginate invoices of this subscription
        $perPage = min($request->integer('per_page', 25), 1000);

        $paginator = $subscription->invoices()
            ->latest()
            ->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function ($invoice, $index) use ($from) {
            return [
                'id' => $invoice->id,
                'sr_no' => $from + $index,
                'invoice_number' => $invoice->invoice_number,
                'date' => $invoice->created_at?->format('M d, Y'),
                'amount' => $invoice->formatted_total,
                'status' => $invoice->status,
                'created_at' => $invoice->created_at?->toISOString(),
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
}

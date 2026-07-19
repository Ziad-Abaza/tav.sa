<?php

namespace App\Http\Controllers\Tenant\Api\Table\invoices;

use App\Http\Controllers\Controller;
use App\Models\Invoice\Invoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Main data endpoint - returns paginated contact data
     */
    private array $sortable = [
        'created_at',
    ];

    private array $searchable = [
        'invoice_number',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.subscription.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $subscriptionId = $request->integer('subscription_id');

        // Fetch specific subscription
        $subscription = Subscription::with(['invoices'])
            ->where('tenant_id', tenant_id())
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

    public function maininvoices(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.subscription.view'])) {
            return response()->json([
                'message' => t('access_denied_note'),
            ], 403);
        }

        $tenantId = tenant_id();

        $query = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->with(['items', 'taxes'])
            ->withSum('items as total_amount', 'amount')
            ->latest();

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 100);

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function ($invoice, $index) use ($paginator) {

            return [
                'id' => $invoice->id,
                'sr_no' => $paginator->firstItem() + $index,
                'invoice_number' => $invoice->invoice_number ?? format_draft_invoice_number(),
                'title' => $invoice->title,
                'description' => $invoice->description,
                'date' => $invoice->created_at?->format('M d, Y'),
                'created_at' => $invoice->created_at?->toISOString(),
                'total' => $invoice->formatted_total,
                'total_with_tax' => $this->totalAmount($invoice),
                'status' => $invoice->status,
            ];
        });

        return response()->json([
            'data' => $items,
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
        // Global search
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if ($status = $request->status) {
            $query->where('status', $status);
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

    public function totalAmount($invoice)
    {
        // Ensure we calculate and display the correct total with tax

        $subtotal = $invoice->subTotal();
        $taxDetails = $invoice->getTaxDetails();

        $taxAmount = 0;

        // Calculate actual tax amount if needed
        foreach ($taxDetails as $tax) {
            $amount = $tax['amount'];
            if ($amount <= 0 && $tax['rate'] > 0) {
                $amount = $subtotal * ($tax['rate'] / 100);
            }
            $taxAmount += $amount;
        }

        $fee = $invoice->fee ?: 0;
        $calculatedTotal = $subtotal + $taxAmount + $fee;

        // Use calculated total if different from invoice total
        if (abs($calculatedTotal - $invoice->total()) > 0.01) {
            $totalDisplay = $invoice->formatAmount($calculatedTotal);
        } else {
            $totalDisplay = $invoice->formattedTotal();
        }

        return $totalDisplay;
    }
}

<?php

namespace App\Http\Controllers\Admin\Api\Table\invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmininvoiceTableController extends Controller
{
    private array $sortable = [
        'invoice_number',
        'status',
        'created_at',
    ];

    private array $searchable = [
        'invoice_number',
        'tenant.company_name',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['items', 'tenant', 'taxes'])
            ->withSum('items as total_amount', 'amount');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(function ($invoice) {

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? 'INV-DRAFT',
                    'tenant' => $invoice->tenant?->company_name ?? '-',
                    'status' => $invoice->status,
                    'amount' => $invoice->formatAmount($invoice->total_amount),
                    'total_with_tax' => $this->calculateTotalWithTax($invoice),
                    'created_at' => $invoice->created_at?->diffForHumans(),
                    'created_at_full' => $invoice->created_at?->format('Y-m-d H:i:s'),
                    'view_url' => route('admin.invoices.show', [$invoice->id]),
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

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
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

    private function calculateTotalWithTax($invoice): string
    {
        $subtotal = $invoice->subTotal();
        $taxDetails = $invoice->getTaxDetails();

        $taxAmount = 0;

        foreach ($taxDetails as $tax) {
            $amount = $tax['amount'];
            if ($amount <= 0 && $tax['rate'] > 0) {
                $amount = $subtotal * ($tax['rate'] / 100);
            }
            $taxAmount += $amount;
        }

        $fee = $invoice->fee ?: 0;
        $calculatedTotal = $subtotal + $taxAmount + $fee;

        if (abs($calculatedTotal - $invoice->total()) > 0.01) {
            return $invoice->formatAmount($calculatedTotal);
        }

        return $invoice->formattedTotal();
    }
}

<?php

namespace App\Http\Controllers\Admin\Api\Table\transaction;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionTableController extends Controller
{
    private array $sortable = ['id', 'type', 'status', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Transaction::query()
            ->with(['invoice.tenant.users', 'currency'])
            ->orderByRaw("
                CASE
                    WHEN status = 'pending' THEN 0
                    WHEN status = 'success' THEN 1
                    WHEN status = 'failed' THEN 2
                    ELSE 3
                END
            ");

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(function ($row) {

                $user = $row->invoice?->tenant?->users?->first();
                $customer = $user ? trim($user->firstname.' '.$user->lastname) : 'N/A';

                return [
                    'id' => $row->id,
                    'customer_name' => $customer,
                    'type' => $row->type,
                    'status' => $row->status,
                    'amount' => $row->invoice?->subTotal()
                        ? get_base_currency()->format($row->invoice->subTotal())
                        : '-',
                    'amount_with_tax' => $this->getInvoiceTotalWithTax($row),
                    'created_at' => $row->created_at?->diffForHumans(),
                    'created_at_full' => $row->created_at?->format('Y-m-d H:i:s'),
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
        if ($customer = $request->string('q')->toString()) {
            $query->whereHas('invoice.tenant.users', function (Builder $q) use ($customer) {
                $q->whereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ["%{$customer}%"])
                    ->orWhere('firstname', 'like', "%{$customer}%")
                    ->orWhere('lastname', 'like', "%{$customer}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        // Created From
        if ($createdFrom = $request->created_from) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        // Created Until
        if ($createdUntil = $request->created_until) {
            $query->whereDate('created_at', '<=', $createdUntil);
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

    private function getInvoiceTotalWithTax($transaction): string
    {
        $invoice = $transaction->invoice;

        if (! $invoice) {
            return get_base_currency()->format($transaction->amount);
        }

        return $invoice->formattedTotal();
    }
}

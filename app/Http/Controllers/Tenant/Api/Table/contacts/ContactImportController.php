<?php

namespace App\Http\Controllers\Tenant\Api\Table\contacts;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ContactImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactImportController extends Controller
{
    /**
     * Main data endpoint - returns paginated contact data
     */
    private array $searchable = [
        'file_path',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.contact.view', 'tenant.contact.view_own'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();

        $query = ContactImport::query()
            ->select([
                'id',
                'tenant_id',
                'status',
                'file_path',
                'processed_records',
                'total_records',
                'valid_records',
                'invalid_records',
                'skipped_records',
                'created_at',
            ])
            ->where('tenant_id', $tenantId);

        // Restrict to own contacts if user only has view_own permission
        if (! checkPermission('tenant.contact.view') && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', Auth::id());
        }

        // Apply filters and search
        $this->applyFilters($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each contact row for the frontend
        $from = $paginator->firstItem() ?? 0;
        $items = collect($paginator->items())->map(function (ContactImport $contact, int $index) use ($from): array {
            return [
                'id' => $contact->id,
                'sr_no' => $from + $index,
                'status' => $contact->status,
                'file' => $contact->file_path,
                'total_records' => $contact->total_records,
                'processed_records' => $contact->processed_records,
                'valid_records' => $contact->valid_records,
                'invalid_records' => $contact->invalid_records,
                'skipped_records' => $contact->skipped_records,
                'created_at' => $contact->created_at,
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
}

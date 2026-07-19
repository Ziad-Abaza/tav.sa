<?php

namespace App\Http\Controllers\Admin\Api\Table\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TenantTableController extends Controller
{
    /** @var string[] Whitelisted sortable columns */
    private array $sortable = ['id', 'name', 'email', 'company_name', 'status', 'created_at'];

    /** @var string[] Columns included in global search */
    private array $searchable = ['users.firstname', 'users.lastname', 'users.email', 'tenants.company_name'];

    /**
     * Main data endpoint — returns paginated tenant data.
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('admin.tenants.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $query = Tenant::query()
            ->join('users', 'tenants.id', '=', 'users.tenant_id')
            ->join(DB::raw('(
                SELECT MIN(id) as id
                FROM users
                WHERE is_admin = 1
                GROUP BY tenant_id
            ) as oldest_admins'), 'users.id', '=', 'oldest_admins.id')
            ->select([
                'tenants.id',
                'tenants.company_name',
                'tenants.status',
                'tenants.subdomain',
                'tenants.created_at',
                'tenants.deleted_date',
                'users.id as user_id',
                'users.firstname',
                'users.lastname',
                'users.email',
                'users.email_verified_at',
            ]);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($row, int $index) => [
                'id' => $row->id,
                'sr_no' => $from + $index,
                'name' => trim($row->firstname.' '.$row->lastname),
                'email' => $row->email,
                'company_name' => $row->company_name,
                'status' => $row->deleted_date ? 'pending_deletion' : $row->status,
                'domain' => $row->domain,
                'user_id' => $row->user_id,
                'email_verified_at' => $row->email_verified_at,
                'deleted_date' => $row->deleted_date,
                'created_at' => $row->created_at?->toISOString(),
                'can_login' => checkPermission('admin.tenants.login'),
                'can_edit' => checkPermission(['admin.tenants.edit']),
                'can_delete' => checkPermission(['admin.tenants.delete']),
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
        $dir = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $columnMap = [
            'id' => 'tenants.id',
            'name' => 'users.firstname',
            'email' => 'users.email',
            'company_name' => 'tenants.company_name',
            'status' => 'tenants.status',
            'created_at' => 'tenants.created_at',
        ];

        $column = $columnMap[$sort] ?? 'tenants.created_at';

        $query->orderBy($column, $dir);
    }

    public function updateStatus(Request $request, Tenant $tenant): JsonResponse
    {
        if (! checkPermission('admin.tenants.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $request->validate([
            'status' => 'required|string|in:active,deactive,suspended,expired',
        ]);

        $originalStatus = $tenant->status;
        $tenant->status = $request->status;
        $tenant->save();

        Cache::forget("tenant_{$tenant->id}");

        event(new \App\Events\Tenant\TenantStatusChanged(
            $tenant,
            $originalStatus,
            $tenant->status
        ));

        return response()->json([
            'message' => t('tenant_status_updated'),
            'status' => $tenant->status,
        ]);
    }

    public function statusOptions(): JsonResponse
    {
        return response()->json([
            'statuses' => \App\Enum\TenantStatus::labels(),
        ]);
    }
}

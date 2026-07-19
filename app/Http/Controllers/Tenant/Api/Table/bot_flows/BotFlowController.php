<?php

namespace App\Http\Controllers\Tenant\Api\Table\bot_flows;

use App\Http\Controllers\Controller;
use App\Models\Tenant\BotFlow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BotFlowController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = ['name', 'description', 'is_active', 'created_at'];

    /** @var string[] Columns included in global search */
    private array $searchable = ['name', 'description'];

    /**
     * Main data endpoint — returns paginated bot flow data.
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('tenant.bot_flow.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $query = BotFlow::query()
            ->where('tenant_id', tenant_id()); // ✅ direct tenant filtering

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (BotFlow $row, int $index) => [
                'id' => $row->id,
                'sr_no' => $from + $index,
                'name' => $row->name,
                'description' => $row->description,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at?->toISOString(),
                'can_create' => checkPermission('tenant.bot_flow.create'),
                'can_view' => checkPermission('tenant.bot_flow.view'),
                'can_edit' => checkPermission('tenant.bot_flow.edit'),
                'can_delete' => checkPermission('tenant.bot_flow.delete'),
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

    /**
     * Export bot flows as CSV — same filters/page as the table view.
     */
    public function export(Request $request): StreamedResponse
    {
        if (! checkPermission('tenant.bot_flow.view')) {
            abort(403, t('access_denied_note'));
        }

        $query = BotFlow::query()
            ->where('tenant_id', tenant_id());

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $page = max($request->integer('page', 1), 1);
        $rows = $query->forPage($page, $perPage)->get();

        $csv = Writer::createFromString();
        $csv->insertOne(['Name', 'Description', 'Active', 'Created At']);

        foreach ($rows as $row) {
            $csv->insertOne([
                $row->name,
                $row->description,
                $row->is_active ? 'Yes' : 'No',
                $row->created_at?->format('Y-m-d H:i:s'),
            ]);
        }

        $filename = 'bot-flows-page'.$page.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv->toString()),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * Toggle the is_active flag on a bot flow.
     */
    public function toggleActive(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.bot_flow.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $botFlow = BotFlow::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $botFlow->update(['is_active' => ! $botFlow->is_active]);

        return response()->json([
            'value' => (bool) $botFlow->is_active,
            'message' => $botFlow->is_active
                ? t('bot_flow_activate')
                : t('bot_flow_deactivate'),
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
        $query->orderBy(in_array($sort, $this->sortable, true) ? $sort : 'created_at', $dir);
    }
}

<?php

namespace Modules\Tickets\Http\Controllers\Api\Table;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use League\Csv\Writer;
use Modules\Tickets\Models\Ticket;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantTicketController extends Controller
{
    /** @var string[] Whitelisted sortable columns */
    private array $sortable = [
        'ticket_id',
        'subject',
        'department_name',
        'priority',
        'status',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'ticket_id',
        'subject',
        'departments.name',
        'priority',
        'status',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Ticket::query()
            ->select([
                'tickets.*',
                'departments.name as department_name',
            ])
            ->leftJoin('departments', 'tickets.department_id', '=', 'departments.id')
            ->where('tickets.tenant_id', Auth::user()->tenant_id)
            ->with(['assignedUsers', 'department', 'tenant'])
            ->withCount('replies');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(
                fn (Ticket $row, int $index) => [
                    'id' => $row->id,
                    'sr_no' => $from + $index,

                    'ticket_id' => $row->ticket_id,
                    'subject' => $row->subject,
                    'department_name' => $row->department_name ?? 'N/A',

                    'priority' => $row->priority,
                    'status' => $row->status,

                    'created_at' => $row->created_at?->toISOString(),
                    'created_at_human' => $row->created_at?->diffForHumans(),

                    'replies_count' => $row->replies_count,
                ]
            ),
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
     * Export tickets as CSV — same filters/page as table view
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Ticket::query()
            ->select([
                'tickets.*',
                'departments.name as department_name',
            ])
            ->leftJoin('departments', 'tickets.department_id', '=', 'departments.id')
            ->where('tickets.tenant_id', Auth::user()->tenant_id)
            ->with(['department'])
            ->withCount('replies');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $page = max($request->integer('page', 1), 1);

        $rows = $query->forPage($page, $perPage)->get();

        $csv = Writer::createFromString();

        // ✅ Header row
        $csv->insertOne([
            'Ticket ID',
            'Subject',
            'Department',
            'Priority',
            'Status',
            'Created At',
        ]);

        foreach ($rows as $row) {
            $csv->insertOne([
                $row->ticket_id,
                $row->subject,
                $row->department_name ?? 'N/A',
                ucfirst($row->priority),
                ucfirst($row->status),
                $row->created_at?->format('Y-m-d H:i:s'),
            ]);
        }

        $filename = 'tickets-page-'.$page.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csv) {
            // ✅ UTF-8 BOM for Excel
            echo "\xEF\xBB\xBF";
            echo $csv->toString();
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Apply search + filters
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        // 🔎 Global Search
        if ($request->filled('q') && $request->q !== 'undefined') {
            $search = $request->q;

            $query->where(function (Builder $q) use ($search) {
                foreach ($this->searchable as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        // ✅ Status filter
        if ($request->filled('status') && $request->status !== 'undefined') {
            $query->where('tickets.status', $request->status);
        }

        // ✅ Priority filter
        if ($request->filled('priority') && $request->priority !== 'undefined') {
            $query->where('tickets.priority', $request->priority);
        }

        // ✅ Created From
        if ($request->filled('created_from') && $request->created_from !== 'undefined') {
            $query->where('tickets.created_at', '>=', $request->created_from);
        }

        // ✅ Created Until
        if ($request->filled('created_until') && $request->created_until !== 'undefined') {
            $query->where('tickets.created_at', '<=', $request->created_until);
        }
    }

    /**
     * Apply safe sorting
     */
    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $dir = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy(
            in_array($sort, $this->sortable, true) ? $sort : 'created_at',
            $dir
        );
    }

    /**
     * Filters endpoint for Vue dropdowns
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'statuses' => [
                ['id' => 'open', 'name' => 'Open'],
                ['id' => 'answered', 'name' => 'Answered'],
                ['id' => 'on_hold', 'name' => 'On Hold'],
                ['id' => 'closed', 'name' => 'Closed'],
            ],
            'priorities' => [
                ['id' => 'low', 'name' => 'Low'],
                ['id' => 'medium', 'name' => 'Medium'],
                ['id' => 'high', 'name' => 'High'],
                ['id' => 'urgent', 'name' => 'Urgent'],
            ],
        ]);
    }
}

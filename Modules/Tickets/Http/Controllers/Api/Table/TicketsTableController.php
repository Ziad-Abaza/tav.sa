<?php

namespace Modules\Tickets\Http\Controllers\Api\Table;

use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Csv\Writer;
use Modules\Tickets\Models\Ticket;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketsTableController extends Controller
{
    /**
     * Main data endpoint — returns paginated tenant data.
     */
    private array $searchable = [
        'ticket_id',
        'subject',
        'priority',
        'status',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['admin.tickets.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $user = Auth::user();
        $userId = (string) $user->id;

        $query = Ticket::query()
            ->with([
                'tenant',
                'department',
                'replies' => fn ($q) => $q->latest()->limit(1),
            ])
            ->select('tickets.*');

        if (! $user->is_admin) {
            $query->where(function ($subQuery) use ($userId) {

                $subQuery->where(function ($q) use ($userId) {
                    $q->whereNotNull('tickets.assignee_id')
                        ->where('tickets.assignee_id', '!=', '')
                        ->whereJsonContains('tickets.assignee_id', $userId);
                });

                $subQuery->orWhereHas('department', function ($q) use ($userId) {
                    $q->whereNotNull('assignee_id')
                        ->where('assignee_id', '!=', '')
                        ->whereRaw('assignee_id REGEXP ?', [
                            '\\['.$userId.'\\]'
                            .'|\\['.$userId.','
                            .'|,'.$userId.','
                            .'|,'.$userId.'\\]',
                        ]);
                });
            });
        }

        $this->applyFilters($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);

        $paginator = $query
            ->latest()
            ->paginate($perPage);
        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function ($ticket, $index) {
            return [
                'id' => $ticket->id,
                'ticket_id' => $ticket->ticket_id,
                'subject' => $ticket->subject,
                'tenant' => $ticket->tenant->company_name,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at,
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

    /* -------------------- FILTER LOGIC -------------------- */

    private function applyFilters(Builder $query, Request $request): void
    {
        // Status filter
        if ($status = $request->status) {
            $query->where('status', $status);
        }

        // Priority filter
        if ($priority = $request->priority) {
            $query->where('priority', $priority);
        }

        // Created From
        if ($createdFrom = $request->created_from) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        // Created Until
        if ($createdUntil = $request->created_until) {
            $query->whereDate('created_at', '<=', $createdUntil);
        }

        // Global search
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                foreach ($this->searchable as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }
    }

    public function export(Request $request): StreamedResponse
    {
        if (! checkPermission(['admin.tickets.view', 'admin.tickets.view_own'])) {
            abort(403, t('access_denied_note'));
        }

        $user = Auth::user();
        $userId = (string) $user->id;

        $query = Ticket::query()
            ->with([
                'tenant',
                'department',
                'replies' => fn ($q) => $q->latest()->limit(1),
            ])
            ->select('tickets.*');

        if (! $user->is_admin) {
            $query->where(function ($subQuery) use ($userId) {

                $subQuery->where(function ($q) use ($userId) {
                    $q->whereNotNull('tickets.assignee_id')
                        ->where('tickets.assignee_id', '!=', '')
                        ->whereJsonContains('tickets.assignee_id', $userId);
                });

                $subQuery->orWhereHas('department', function ($q) use ($userId) {
                    $q->whereNotNull('assignee_id')
                        ->where('assignee_id', '!=', '')
                        ->whereRaw('assignee_id REGEXP ?', [
                            '\\['.$userId.'\\]'
                            .'|\\['.$userId.','
                            .'|,'.$userId.','
                            .'|,'.$userId.'\\]',
                        ]);
                });
            });
        }

        $this->applyFilters($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $page = max($request->integer('page', 1), 1);
        $tickets = $query->forPage($page, $perPage)->get();

        // Build CSV in memory — small dataset, no streaming needed
        $csv = Writer::createFromString();
        $csv->insertOne([
            'id',
            'ticket_id',
            'subject',
            'tenant',
            'priority',
            'status',
            'created_at',
        ]);

        foreach ($tickets as $ticket) {
            $csv->insertOne([
                $ticket->id,
                $ticket->ticket_id,
                $ticket->subject,
                $ticket->tenant->company_name,
                $ticket->priority,
                $ticket->status,
                $ticket->created_at?->format('Y-m-d H:i:s'),
            ]);
        }

        $filename = 'tickets-page'.$page.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv->toString()),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }
}

<?php

namespace App\Http\Controllers\Tenant\Api\Table\facebookmessenger;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FbMessengerCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacebookMessengerCampaignController extends Controller
{
    /** @var string[] Whitelisted sortable columns */
    private array $sortable = [
        'name',
        'is_sent',
        'sending_count',
        'total_count',
        'scheduled_send_time',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'name',
    ];

    /**
     * Main data endpoint - returns paginated campaign data
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = tenant_id();

        $query = FbMessengerCampaign::query()
            ->with('fbTemplate:id,name,content_type')
            ->where('tenant_id', $tenantId);

        // Apply filters and search
        $this->applyFilters($query, $request);

        // Sort (whitelist enforced)
        $this->applySorting($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each campaign row for the frontend
        $from = $paginator->firstItem() ?? 0;
        $items = collect($paginator->items())->map(function (FbMessengerCampaign $campaign, int $index) use ($from): array {
            return [
                'id' => $campaign->id,
                'sr_no' => $from + $index,
                'name' => $campaign->name,
                'template_name' => $campaign->fbTemplate?->name ?? 'N/A',
                'template_type' => $campaign->fbTemplate?->content_type ?? 'text',
                'status' => $campaign->getStatusLabel(),
                'status_color' => $campaign->getStatusColor(),
                'is_sent' => (bool) $campaign->is_sent,
                'pause_campaign' => (bool) $campaign->pause_campaign,
                'total_count' => $campaign->total_count,
                'sending_count' => $campaign->sending_count,
                'pending_count' => $campaign->getPendingCount(),
                'sent_count' => $campaign->getSentCount(),
                'failed_count' => $campaign->getFailedCount(),
                'scheduled_send_time' => $campaign->scheduled_send_time?->toISOString(),
                'send_now' => (bool) $campaign->send_now,
                'created_at' => $campaign->created_at?->toISOString(),
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

    /**
     * Get filter options for the campaign table
     */
    public function filters(Request $request): JsonResponse
    {
        return response()->json([
            'status' => [
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'in_progress', 'label' => 'In Progress'],
                ['value' => 'paused', 'label' => 'Paused'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'queued', 'label' => 'Queued'],
            ],
        ]);
    }

    /**
     * Delete a campaign
     */
    public function destroy(string $subdomain, int $id): JsonResponse
    {
        $campaign = FbMessengerCampaign::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $campaign->delete();

        return response()->json([
            'message' => 'Campaign deleted successfully',
        ]);
    }

    /**
     * Toggle the pause flag
     */
    public function togglePause(string $subdomain, int $id): JsonResponse
    {
        $campaign = FbMessengerCampaign::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $campaign->update(['pause_campaign' => ! $campaign->pause_campaign]);

        return response()->json([
            'value' => (bool) $campaign->pause_campaign,
            'message' => $campaign->pause_campaign
                ? 'Campaign paused'
                : 'Campaign resumed',
        ]);
    }

    /**
     * Apply filters to the query
     */
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

        // Status filter
        if ($status = $request->string('status')->toString()) {
            match ($status) {
                'completed' => $query->where('is_sent', true)->whereDoesntHave('details', fn ($q) => $q->pending()),
                'in_progress' => $query->where('is_sent', true)->whereHas('details', fn ($q) => $q->pending()),
                'paused' => $query->where('pause_campaign', true),
                'scheduled' => $query->where('is_sent', false)->whereNotNull('scheduled_send_time'),
                'queued' => $query->where('is_sent', false)->where('send_now', true),
                default => null,
            };
        }
    }

    /**
     * Apply sorting to the query
     */
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
}

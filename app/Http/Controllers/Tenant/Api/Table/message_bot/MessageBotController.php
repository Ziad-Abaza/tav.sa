<?php

namespace App\Http\Controllers\Tenant\Api\Table\message_bot;

use App\Enum\Tenant\WhatsAppTemplateRelationType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MessageBot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageBotController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = ['name', 'reply_type', 'trigger', 'rel_type', 'is_bot_active', 'created_at'];

    /** @var string[] Columns included in global search */
    private array $searchable = ['name', 'reply_type', 'trigger', 'rel_type'];

    /**
     * Main data endpoint — returns paginated bot flow data.
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('tenant.message_bot.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();
        $query = MessageBot::query()
            ->selectRaw('message_bots.*, (SELECT COUNT(*) FROM message_bots i2 WHERE i2.id <= message_bots.id AND i2.tenant_id = ?) as row_num', [$tenantId])
            ->where('tenant_id', $tenantId);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(
                fn (MessageBot $row, int $index) => [
                    'id' => $row->id,
                    'sr_no' => $from + $index,
                    'name' => $row->name,
                    'reply_type' => ucfirst(
                        WhatsAppTemplateRelationType::getReplyType((int) $row->reply_type) ?? ''
                    ),
                    'trigger' => $row->trigger,
                    'rel_type' => $row->rel_type,
                    'is_bot_active' => (bool) $row->is_bot_active,
                    'created_at' => $row->created_at?->toISOString(),
                    'can_edit' => checkPermission(['tenant.message_bot.edit']),
                    'can_delete' => checkPermission(['tenant.message_bot.delete']),
                    'can_clone' => checkPermission(['tenant.message_bot.clone']),
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
     * Toggle the is_bot_apiactive flag on a bot flow.
     */
    public function toggleActive(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.message_bot.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $message_bot = MessageBot::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $message_bot->update(['is_bot_active' => ! $message_bot->is_bot_active]);

        return response()->json([
            'value' => (bool) $message_bot->is_bot_active,
            'message' => t('status_updated_successfully'),
        ]);
    }

    public function filters(Request $request): JsonResponse
    {
        if (! checkPermission('tenant.message_bot.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        return response()->json([
            'reply_type' => [
                ['value' => '1', 'label' => 'On Exact Match'],
                ['value' => '2', 'label' => 'When Message Contains'],
                ['value' => '3', 'label' => 'When Lead or Client Sends First Message'],
                ['value' => '4', 'label' => 'Default Reply'],
            ],

            'rel_type' => [
                ['value' => 'guest', 'label' => 'Guest'],
                ['value' => 'customer', 'label' => 'Customer'],
                ['value' => 'lead', 'label' => 'Lead'],
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

        // Reply type filter
        $replyType = $request->input('filters.reply_type', $request->input('reply_type'));

        if ($replyType !== null && $replyType !== '') {
            $query->where('message_bots.reply_type', $replyType);
        }

        // Relation type filter
        $relType = $request->input('filters.rel_type', $request->input('rel_type'));

        if ($relType !== null && $relType !== '') {
            $query->where('message_bots.rel_type', $relType);
        }

        if ($request->filled('created_from') && $request->created_from !== 'undefined') {
            $query->whereDate('message_bots.created_at', '>=', $request->created_from);
        }

        if ($request->filled('created_until') && $request->created_until !== 'undefined') {
            $query->whereDate('message_bots.created_at', '<=', $request->created_until);
        }
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $dir = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy(in_array($sort, $this->sortable, true) ? $sort : 'created_at', $dir);
    }
}

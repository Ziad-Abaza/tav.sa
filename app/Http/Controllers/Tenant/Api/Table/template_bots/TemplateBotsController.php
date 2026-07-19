<?php

namespace App\Http\Controllers\Tenant\Api\Table\template_bots;

use App\Enum\Tenant\WhatsAppTemplateRelationType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\TemplateBot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateBotsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.template_bot.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();

        $query = TemplateBot::query()
            ->where('tenant_id', $tenantId);

        $this->applyFilters($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);

        $paginator = $query->latest()->paginate($perPage);

        $items = collect($paginator->items())->map(function ($field, $index) use ($paginator) {

            return [
                'id' => $field->id,
                'sr_no' => $paginator->firstItem() + $index,
                'name' => $field->name,
                'reply_type' => ucfirst(
                    WhatsAppTemplateRelationType::getReplyType($field->reply_type) ?? ''
                ),
                'trigger_keyword' => $field->trigger,
                'relation_type' => $field->rel_type,
                'is_bot_active' => (bool) $field->is_bot_active,
                'created_at' => $field->created_at,
                'can_delete' => checkPermission(['tenant.template_bot.delete']),
                'can_edit' => checkPermission(['tenant.template_bot.edit']),
                'can_clone' => checkPermission(['tenant.template_bot.clone']),
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

    /* -------------------- FILTER LOGIC -------------------- */

    private function applyFilters(Builder $query, Request $request): void
    {
        // Reply Type filter
        if ($replyType = $request->reply_type) {
            $query->where('reply_type', $replyType);
        }

        // Relation Type filter
        if ($relationType = $request->relation_type) {
            $query->where('rel_type', $relationType);
        }

        // Created From
        if ($createdFrom = $request->created_from) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        // Created Until
        if ($createdUntil = $request->created_until) {
            $query->whereDate('created_at', '<=', $createdUntil);
        }

        // Global search (optional)
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search) {
                $q->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('trigger', 'like', "%{$search}%");
            });
        }
    }

    public function toggleActive(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.template_bot.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $customField = TemplateBot::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $customField->update(['is_bot_active' => ! $customField->is_bot_active]);

        return response()->json([
            'value' => (bool) $customField->is_bot_active,
            'message' => t('template_bot_updated_successfully'),
        ]);
    }
}
